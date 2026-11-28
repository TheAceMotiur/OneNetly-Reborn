<?php

use App\Enums\PurchaseStatus;
use App\Models\Plan;
use App\Models\Profile;
use App\Models\Purchase;
use App\Models\TwitterAutomation;
use App\Models\TwitterMention;
use App\Models\TwitterPost;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * @param  array<string, mixed>  $attributes
 */
function twitterProfile(array $attributes = []): Profile
{
    $credentialKeys = [
        'twitter_enabled',
        'twitter_username',
        'twitter_user_id',
        'twitter_access_token',
        'twitter_refresh_token',
        'twitter_token_expires_at',
    ];

    $profile = Profile::factory()->create([
        'business_type' => Profile::RESTAURANT_TYPE,
        'is_published' => true,
        ...array_diff_key($attributes, array_flip($credentialKeys)),
    ]);

    User::query()->findOrFail($profile->user_id)->update([
        'twitter_enabled' => true,
        'twitter_username' => 'onenetlykitchen',
        'twitter_user_id' => '1234567890',
        'twitter_access_token' => 'twitter-token',
        ...array_intersect_key($attributes, array_flip($credentialKeys)),
    ]);

    Purchase::factory()
        ->for(User::query()->findOrFail($profile->user_id))
        ->create([
            'plan_id' => Plan::factory()->tool('twitter')->create()->id,
            'status' => PurchaseStatus::Completed,
        ]);

    return $profile;
}

test('the twitter page shows the connection, posts and automations', function () {
    $owner = User::factory()->withAllTools()->create();
    $profile = twitterProfile(['user_id' => $owner->id]);

    TwitterPost::factory()->for($profile)->create(['body' => 'Hello from OneNetly']);

    $this->actingAs($owner->refresh())
        ->get(route('twitter.index', ['restaurant' => $profile->username]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('twitter/Index')
            ->where('profile.username', $profile->username)
            ->where('connection.enabled', true)
            ->where('connection.username', 'onenetlykitchen')
            ->has('automations', 1)
            ->has('posts', 1)
            ->has('stats'));
});

test('guests cannot open twitter automation', function () {
    $this->get(route('twitter.index'))->assertRedirect(route('login'));
});

test('owners can save twitter credentials and tokens stay encrypted', function () {
    $owner = User::factory()->withAllTools()->create();
    $profile = twitterProfile([
        'user_id' => $owner->id,
        'twitter_access_token' => null,
        'twitter_refresh_token' => null,
    ]);

    $this->actingAs($owner)
        ->put(route('twitter.settings.update', ['restaurant' => $profile->username]), [
            'enabled' => true,
            'username' => '@onenetlykitchen',
            'user_id' => '9876543210',
            'access_token' => 'access-secret',
            'refresh_token' => 'refresh-secret',
        ])
        ->assertRedirect();

    $owner->refresh();

    expect($owner->twitter_access_token)->toBe('access-secret')
        ->and($owner->twitter_refresh_token)->toBe('refresh-secret')
        ->and($owner->twitter_username)->toBe('onenetlykitchen')
        ->and($owner->twitter_user_id)->toBe('9876543210')
        ->and(DB::table('users')->where('id', $owner->id)->value('twitter_access_token'))
        ->not->toBe('access-secret');
});

test('saving twitter settings with a blank token keeps it and remove_token clears it', function () {
    $owner = User::factory()->withAllTools()->create();
    $profile = twitterProfile(['user_id' => $owner->id]);

    $this->actingAs($owner)
        ->put(route('twitter.settings.update', ['restaurant' => $profile->username]), [
            'enabled' => true,
            'username' => 'onenetlykitchen',
            'user_id' => '1234567890',
            'access_token' => '',
        ])
        ->assertRedirect();

    expect($owner->refresh()->twitter_access_token)->toBe('twitter-token');

    $this->actingAs($owner)
        ->put(route('twitter.settings.update', ['restaurant' => $profile->username]), [
            'enabled' => false,
            'remove_token' => true,
        ])
        ->assertRedirect();

    expect($owner->refresh()->twitter_access_token)->toBeNull()
        ->and($owner->twitter_refresh_token)->toBeNull();
});

test('the twitter connection test calls the users me endpoint', function () {
    Http::fake([
        'api.x.com/2/users/me*' => Http::response([
            'data' => ['id' => '1234567890', 'username' => 'onenetlykitchen'],
        ]),
    ]);

    $owner = User::factory()->withAllTools()->create();
    $profile = twitterProfile(['user_id' => $owner->id]);

    $this->actingAs($owner->refresh())
        ->post(route('twitter.settings.test', ['restaurant' => $profile->username]))
        ->assertRedirect();

    Http::assertSent(fn ($request) => str_contains($request->url(), '/2/users/me')
        && $request->hasHeader('Authorization', 'Bearer twitter-token'));
});

test('owners can publish a tweet immediately', function () {
    Http::fake([
        'api.x.com/2/tweets' => Http::response(['data' => ['id' => 'tweet_1']]),
    ]);

    $owner = User::factory()->withAllTools()->create();
    $profile = twitterProfile(['user_id' => $owner->id]);

    $this->actingAs($owner)
        ->post(route('twitter.posts.store', ['restaurant' => $profile->username]), [
            'body' => 'Hello from OneNetly',
            'publish_now' => true,
        ])
        ->assertRedirect();

    $post = $profile->twitterPosts()->first();

    expect($post?->status)->toBe('published')
        ->and($post?->external_id)->toBe('tweet_1');

    Http::assertSent(fn ($request) => $request['text'] === 'Hello from OneNetly');
});

test('the scheduled post command publishes due tweets only', function () {
    Http::fake([
        'api.x.com/2/tweets' => Http::response(['data' => ['id' => 'scheduled_1']]),
    ]);

    $profile = twitterProfile();

    $due = TwitterPost::factory()->for($profile)->scheduled(now()->subMinute()->toDateTimeString())->create();
    $later = TwitterPost::factory()->for($profile)->scheduled(now()->addHour()->toDateTimeString())->create();

    $this->artisan('twitter:publish-scheduled')->assertSuccessful();

    expect($due->refresh()->status)->toBe('published')
        ->and($due->external_id)->toBe('scheduled_1')
        ->and($later->refresh()->status)->toBe('scheduled');

    expect(TwitterPost::query()->whereKey($later->id)->value('external_id'))->toBeNull();
});

test('syncing mentions stores them and sends a keyword reply', function () {
    Http::fake([
        'api.x.com/2/users/*/mentions*' => Http::response([
            'data' => [[
                'id' => 'mention_1',
                'text' => 'Can I see the menu?',
                'author_id' => 'author_1',
                'created_at' => '2026-11-25T10:00:00Z',
            ]],
            'includes' => ['users' => [[
                'id' => 'author_1',
                'username' => 'ada',
                'name' => 'Ada',
            ]]],
        ]),
        'api.x.com/2/tweets' => Http::response(['data' => ['id' => 'reply_1']]),
    ]);

    $profile = twitterProfile();

    TwitterAutomation::factory()->for($profile)->keyword()->create();

    $this->artisan('twitter:sync-mentions')->assertSuccessful();

    $mention = TwitterMention::query()->where('tweet_id', 'mention_1')->first();

    expect($mention?->author_username)->toBe('ada')
        ->and($mention?->replied)->toBeTrue()
        ->and($mention?->reply_tweet_id)->toBe('reply_1')
        ->and($profile->twitterPosts()->where('external_id', 'reply_1')->exists())->toBeTrue();

    Http::assertSent(fn ($request) => str_contains($request->url(), '/2/tweets')
        && $request->method() === 'POST'
        && $request['text'] === 'Here is our menu: @onenetlykitchen'
        && $request['reply']['in_reply_to_tweet_id'] === 'mention_1');
});

test('the default mention automation replies when no keyword matches', function () {
    Http::fake([
        'api.x.com/2/users/*/mentions*' => Http::response([
            'data' => [[
                'id' => 'mention_2',
                'text' => 'Great photos!',
                'author_id' => 'author_2',
                'created_at' => '2026-11-25T10:00:00Z',
            ]],
            'includes' => ['users' => [[
                'id' => 'author_2',
                'username' => 'grace',
            ]]],
        ]),
        'api.x.com/2/tweets' => Http::response(['data' => ['id' => 'reply_2']]),
    ]);

    $profile = twitterProfile();

    $this->artisan('twitter:sync-mentions')->assertSuccessful();

    Http::assertSent(fn ($request) => str_contains($request->url(), '/2/tweets')
        && $request->method() === 'POST'
        && str_contains((string) $request['text'], 'Thanks for the mention, @grace'));
});

test('mentions are only answered once', function () {
    Http::fake([
        'api.x.com/2/users/*/mentions*' => Http::response([
            'data' => [[
                'id' => 'mention_3',
                'text' => 'Hello',
                'author_id' => 'author_3',
                'created_at' => '2026-11-25T10:00:00Z',
            ]],
            'includes' => ['users' => [['id' => 'author_3', 'username' => 'linus']]],
        ]),
        'api.x.com/2/tweets' => Http::response(['data' => ['id' => 'reply_3']]),
    ]);

    $profile = twitterProfile();

    TwitterAutomation::factory()->for($profile)->create([
        'key' => 'mention',
        'is_enabled' => true,
    ]);

    $this->artisan('twitter:sync-mentions')->assertSuccessful();
    $this->artisan('twitter:sync-mentions')->assertSuccessful();

    expect(TwitterMention::query()->where('tweet_id', 'mention_3')->count())->toBe(1)
        ->and(TwitterMention::query()->where('tweet_id', 'mention_3')->first()?->replied)->toBeTrue()
        ->and($profile->twitterPosts()->count())->toBe(1);
});

test('a disabled mention automation stores the mention without replying', function () {
    Http::fake([
        'api.x.com/2/users/*/mentions*' => Http::response([
            'data' => [[
                'id' => 'mention_4',
                'text' => 'Hello',
                'author_id' => 'author_4',
                'created_at' => '2026-11-25T10:00:00Z',
            ]],
            'includes' => ['users' => [['id' => 'author_4', 'username' => 'linus']]],
        ]),
        'api.x.com/2/tweets' => Http::response(['data' => ['id' => 'reply_4']]),
    ]);

    $profile = twitterProfile();

    TwitterAutomation::factory()->for($profile)->create([
        'key' => 'mention',
        'is_enabled' => false,
    ]);

    $this->artisan('twitter:sync-mentions')->assertSuccessful();

    expect(TwitterMention::query()->where('tweet_id', 'mention_4')->first()?->replied)->toBeFalse()
        ->and($profile->twitterPosts()->count())->toBe(0);

    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/2/tweets')
        && $request->method() === 'POST');
});

test('owners can update the mention automation and manage keyword replies', function () {
    $owner = User::factory()->withAllTools()->create();
    $profile = twitterProfile(['user_id' => $owner->id]);

    $this->actingAs($owner)
        ->put(route('twitter.automations.update', ['restaurant' => $profile->username]), [
            'automations' => [[
                'key' => 'mention',
                'is_enabled' => false,
                'message' => 'Thanks @{username}, we will reply soon.',
            ]],
        ])
        ->assertRedirect();

    $mention = $profile->twitterAutomations()->where('key', 'mention')->first();

    expect($mention?->is_enabled)->toBeFalse()
        ->and($mention?->message)->toBe('Thanks @{username}, we will reply soon.');

    $this->actingAs($owner)
        ->post(route('twitter.automations.keywords.store', ['restaurant' => $profile->username]), [
            'name' => 'Opening hours',
            'keywords' => 'hours, open',
            'message' => 'We are open from 9am to 10pm.',
        ])
        ->assertRedirect();

    $rule = $profile->twitterAutomations()->where('key', 'keyword')->first();

    expect($rule?->keywords)->toBe('hours, open');

    $this->actingAs($owner)
        ->patch(route('twitter.automations.keywords.update', [
            'restaurant' => $profile->username,
            'automation' => $rule?->id,
        ]), ['is_enabled' => false])
        ->assertRedirect();

    expect($rule?->refresh()->is_enabled)->toBeFalse();

    $this->actingAs($owner)
        ->delete(route('twitter.automations.keywords.destroy', [
            'restaurant' => $profile->username,
            'automation' => $rule?->id,
        ]))
        ->assertRedirect();

    expect(TwitterAutomation::query()->whereKey($rule?->id)->exists())->toBeFalse();
});

test('a page cannot publish or delete another pages tweet', function () {
    Http::fake();

    $owner = User::factory()->withAllTools()->create();
    $profile = twitterProfile(['user_id' => $owner->id]);
    $otherProfile = twitterProfile();
    $post = TwitterPost::factory()->for($otherProfile)->create();

    $this->actingAs($owner)
        ->post(route('twitter.posts.publish', [
            'restaurant' => $profile->username,
            'post' => $post->id,
        ]))
        ->assertNotFound();

    $this->actingAs($owner)
        ->delete(route('twitter.posts.destroy', [
            'restaurant' => $profile->username,
            'post' => $post->id,
        ]))
        ->assertNotFound();

    expect(TwitterPost::query()->whereKey($post->id)->exists())->toBeTrue();
    Http::assertNothingSent();
});

test('an expired access token is refreshed before publishing', function () {
    Http::fake([
        'api.x.com/2/oauth2/token' => Http::response([
            'access_token' => 'refreshed-token',
            'refresh_token' => 'rotated-refresh',
            'expires_in' => 7200,
        ]),
        'api.x.com/2/tweets' => Http::response(['data' => ['id' => 'tweet_2']]),
    ]);

    app(SettingsService::class)->set('twitter_client_id', 'client-id');
    app(SettingsService::class)->set('twitter_client_secret', 'client-secret');

    $owner = User::factory()->withAllTools()->create();
    $profile = twitterProfile([
        'user_id' => $owner->id,
        'twitter_refresh_token' => 'old-refresh',
        'twitter_token_expires_at' => now()->subMinute(),
    ]);

    $this->actingAs($owner)
        ->post(route('twitter.posts.store', ['restaurant' => $profile->username]), [
            'body' => 'Refreshed token post',
            'publish_now' => true,
        ])
        ->assertRedirect();

    $owner->refresh();

    expect($owner->twitter_access_token)->toBe('refreshed-token')
        ->and($owner->twitter_refresh_token)->toBe('rotated-refresh')
        ->and($owner->twitter_token_expires_at)->not->toBeNull();

    Http::assertSent(fn ($request) => str_contains($request->url(), '/2/oauth2/token')
        && $request['grant_type'] === 'refresh_token');

    Http::assertSent(fn ($request) => str_contains($request->url(), '/2/tweets')
        && $request->hasHeader('Authorization', 'Bearer refreshed-token'));
});
