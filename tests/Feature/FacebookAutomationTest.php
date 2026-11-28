<?php

use App\Enums\PurchaseStatus;
use App\Models\FacebookAutomation;
use App\Models\FacebookMessage;
use App\Models\Plan;
use App\Models\Profile;
use App\Models\Purchase;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * @param  array<string, mixed>  $attributes
 */
function facebookProfile(array $attributes = []): Profile
{
    $credentialKeys = [
        'facebook_enabled',
        'facebook_page_name',
        'facebook_page_id',
        'facebook_page_access_token',
    ];

    $profile = Profile::factory()->create([
        'business_type' => Profile::RESTAURANT_TYPE,
        'is_published' => true,
        ...array_diff_key($attributes, array_flip($credentialKeys)),
    ]);

    User::query()->findOrFail($profile->user_id)->update([
        'facebook_enabled' => true,
        'facebook_page_name' => 'OneNetly Kitchen',
        'facebook_page_id' => '1234567890',
        'facebook_page_access_token' => 'page-token',
        ...array_intersect_key($attributes, array_flip($credentialKeys)),
    ]);

    Purchase::factory()
        ->for(User::query()->findOrFail($profile->user_id))
        ->create([
            'plan_id' => Plan::factory()->tool('facebook')->create()->id,
            'status' => PurchaseStatus::Completed,
        ]);

    return $profile;
}

function facebookSignature(array $payload, string $secret): string
{
    return 'sha256='.hash_hmac('sha256', (string) json_encode($payload), $secret);
}

test('the facebook page shows the connection and default automations', function () {
    $owner = User::factory()->withAllTools()->create();
    $profile = facebookProfile(['user_id' => $owner->id]);

    $this->actingAs($owner->refresh())
        ->get(route('facebook.index', ['restaurant' => $profile->username]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('facebook/Index')
            ->where('profile.username', $profile->username)
            ->where('connection.enabled', true)
            ->has('automations', 3)
            ->has('stats'));
});

test('guests cannot open facebook automation', function () {
    $this->get(route('facebook.index'))->assertRedirect(route('login'));
});

test('owners can save facebook credentials and the token stays encrypted', function () {
    $owner = User::factory()->withAllTools()->create();
    $profile = facebookProfile([
        'user_id' => $owner->id,
        'facebook_page_access_token' => null,
    ]);

    $this->actingAs($owner)
        ->put(route('facebook.settings.update', ['restaurant' => $profile->username]), [
            'enabled' => true,
            'page_name' => 'OneNetly Kitchen',
            'page_id' => '999888777',
            'access_token' => 'facebook-secret',
        ])
        ->assertRedirect();

    $owner->refresh();

    expect($owner->facebook_page_access_token)->toBe('facebook-secret')
        ->and($owner->facebook_page_id)->toBe('999888777')
        ->and(DB::table('users')->where('id', $owner->id)->value('facebook_page_access_token'))
        ->not->toBe('facebook-secret');
});

test('saving facebook settings with a blank token keeps the saved token', function () {
    $owner = User::factory()->withAllTools()->create();
    $profile = facebookProfile(['user_id' => $owner->id]);

    $this->actingAs($owner)
        ->put(route('facebook.settings.update', ['restaurant' => $profile->username]), [
            'enabled' => true,
            'page_name' => 'OneNetly Kitchen',
            'page_id' => '1234567890',
            'access_token' => '',
        ])
        ->assertRedirect();

    expect($owner->refresh()->facebook_page_access_token)->toBe('page-token');

    $this->actingAs($owner)
        ->put(route('facebook.settings.update', ['restaurant' => $profile->username]), [
            'enabled' => false,
            'remove_token' => true,
        ])
        ->assertRedirect();

    expect($owner->refresh()->facebook_page_access_token)->toBeNull();
});

test('the facebook connection test calls the graph api', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response([
            'id' => '1234567890',
            'name' => 'OneNetly Kitchen',
        ]),
    ]);

    $owner = User::factory()->withAllTools()->create();
    $profile = facebookProfile(['user_id' => $owner->id]);

    $this->actingAs($owner->refresh())
        ->post(route('facebook.settings.test', ['restaurant' => $profile->username]))
        ->assertRedirect();

    Http::assertSent(fn ($request) => str_contains($request->url(), '/1234567890')
        && $request->hasHeader('Authorization', 'Bearer page-token'));
});

test('the facebook webhook verification challenge requires the configured token', function () {
    app(SettingsService::class)->set('facebook_verify_token', 'fb-verify');

    $this->get('/webhooks/facebook?hub_mode=subscribe&hub_verify_token=fb-verify&hub_challenge=xyz789')
        ->assertOk()
        ->assertSee('xyz789');

    $this->get('/webhooks/facebook?hub_mode=subscribe&hub_verify_token=nope&hub_challenge=xyz789')
        ->assertForbidden();
});

test('a signed inbound messenger webhook stores the message and sends a welcome', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response(['message_id' => 'm_welcome']),
    ]);

    app(SettingsService::class)->set('facebook_app_secret', 'fb-secret');

    $profile = facebookProfile();

    $payload = [
        'object' => 'page',
        'entry' => [[
            'id' => '1234567890',
            'messaging' => [[
                'sender' => ['id' => 'psid-1'],
                'recipient' => ['id' => '1234567890'],
                'message' => [
                    'mid' => 'mid.inbound',
                    'text' => 'Hello there',
                ],
            ]],
        ]],
    ];

    $this->call('POST', '/webhooks/facebook', [], [], [], [
        'HTTP_X_HUB_SIGNATURE_256' => facebookSignature($payload, 'fb-secret'),
        'CONTENT_TYPE' => 'application/json',
    ], json_encode($payload))->assertOk();

    $inbound = FacebookMessage::query()->where('external_id', 'mid.inbound')->first();

    expect($inbound?->direction)->toBe('inbound')
        ->and($inbound?->body)->toBe('Hello there')
        ->and(FacebookMessage::query()->where('external_id', 'm_welcome')->exists())->toBeTrue();

    Http::assertSent(fn ($request) => str_contains($request->url(), '/1234567890/messages')
        && $request['recipient']['id'] === 'psid-1'
        && str_contains((string) $request['message']['text'], 'thanks for messaging'));
});

test('keyword automations reply before the welcome message', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response(['message_id' => 'm_keyword']),
    ]);

    app(SettingsService::class)->set('facebook_app_secret', 'fb-secret');

    $profile = facebookProfile();

    FacebookAutomation::factory()->for($profile)->keyword()->create();

    $payload = [
        'object' => 'page',
        'entry' => [[
            'id' => '1234567890',
            'messaging' => [[
                'sender' => ['id' => 'psid-2'],
                'recipient' => ['id' => '1234567890'],
                'message' => ['mid' => 'mid.keyword', 'text' => 'Can I see the menu?'],
            ]],
        ]],
    ];

    $this->call('POST', '/webhooks/facebook', [], [], [], [
        'HTTP_X_HUB_SIGNATURE_256' => facebookSignature($payload, 'fb-secret'),
        'CONTENT_TYPE' => 'application/json',
    ], json_encode($payload))->assertOk();

    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => str_contains((string) $request['message']['text'], 'Here is our menu'));
});

test('new comments receive the comment automation reply', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response(['id' => 'comment_reply_1']),
    ]);

    app(SettingsService::class)->set('facebook_app_secret', 'fb-secret');

    $profile = facebookProfile();

    $payload = [
        'object' => 'page',
        'entry' => [[
            'id' => '1234567890',
            'changes' => [[
                'field' => 'feed',
                'value' => [
                    'item' => 'comment',
                    'verb' => 'add',
                    'comment_id' => 'comment_1',
                    'from' => ['id' => 'psid-3', 'name' => 'Ada'],
                    'message' => 'Lovely photos!',
                ],
            ]],
        ]],
    ];

    $this->call('POST', '/webhooks/facebook', [], [], [], [
        'HTTP_X_HUB_SIGNATURE_256' => facebookSignature($payload, 'fb-secret'),
        'CONTENT_TYPE' => 'application/json',
    ], json_encode($payload))->assertOk();

    expect(FacebookMessage::query()->where('external_id', 'comment_1')->exists())->toBeTrue();

    Http::assertSent(fn ($request) => str_contains($request->url(), '/comment_1/comments')
        && str_contains((string) $request['message'], 'Thanks for your comment, Ada'));
});

test('comment replies only answer matching keywords when keywords are set', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response(['id' => 'comment_reply_2']),
    ]);

    app(SettingsService::class)->set('facebook_app_secret', 'fb-secret');

    $profile = facebookProfile();

    $profile->facebookAutomations()->create([
        'key' => 'comment',
        'message' => 'Thanks for your comment, {customer}.',
        'keywords' => 'price, menu',
        'is_enabled' => true,
    ]);

    $payload = [
        'object' => 'page',
        'entry' => [[
            'id' => '1234567890',
            'changes' => [[
                'field' => 'feed',
                'value' => [
                    'item' => 'comment',
                    'verb' => 'add',
                    'comment_id' => 'comment_2',
                    'from' => ['id' => 'psid-6', 'name' => 'Ada'],
                    'message' => 'Lovely photos!',
                ],
            ]],
        ]],
    ];

    $this->call('POST', '/webhooks/facebook', [], [], [], [
        'HTTP_X_HUB_SIGNATURE_256' => facebookSignature($payload, 'fb-secret'),
        'CONTENT_TYPE' => 'application/json',
    ], json_encode($payload))->assertOk();

    Http::assertNothingSent();
});

test('matching comments receive a comment to dm private reply', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response(['id' => 'private_reply_1']),
    ]);

    app(SettingsService::class)->set('facebook_app_secret', 'fb-secret');

    $profile = facebookProfile();

    $profile->facebookAutomations()->create([
        'key' => 'comment',
        'message' => 'Thanks for your comment, {customer}.',
        'is_enabled' => false,
    ]);

    $profile->facebookAutomations()->create([
        'key' => 'comment_dm',
        'message' => 'Here is the link you asked for.',
        'keywords' => 'link',
        'is_enabled' => true,
    ]);

    $payload = [
        'object' => 'page',
        'entry' => [[
            'id' => '1234567890',
            'changes' => [[
                'field' => 'feed',
                'value' => [
                    'item' => 'comment',
                    'verb' => 'add',
                    'comment_id' => 'comment_3',
                    'from' => ['id' => 'psid-7', 'name' => 'Ada'],
                    'message' => 'Can you send the link?',
                ],
            ]],
        ]],
    ];

    $this->call('POST', '/webhooks/facebook', [], [], [], [
        'HTTP_X_HUB_SIGNATURE_256' => facebookSignature($payload, 'fb-secret'),
        'CONTENT_TYPE' => 'application/json',
    ], json_encode($payload))->assertOk();

    Http::assertSent(fn ($request) => str_contains($request->url(), '/comment_3/private_replies')
        && $request['message'] === 'Here is the link you asked for.');

    expect(FacebookMessage::query()->where('channel', 'private_reply')->exists())->toBeTrue();
});

test('a facebook webhook with a bad signature is rejected', function () {
    app(SettingsService::class)->set('facebook_app_secret', 'fb-secret');

    $profile = facebookProfile();

    $payload = [
        'object' => 'page',
        'entry' => [[
            'id' => '1234567890',
            'messaging' => [[
                'sender' => ['id' => 'psid-4'],
                'message' => ['mid' => 'mid.bad', 'text' => 'Hello'],
            ]],
        ]],
    ];

    $this->call('POST', '/webhooks/facebook', [], [], [], [
        'HTTP_X_HUB_SIGNATURE_256' => 'sha256=wrong',
        'CONTENT_TYPE' => 'application/json',
    ], json_encode($payload))->assertUnauthorized();

    expect(FacebookMessage::query()->where('external_id', 'mid.bad')->exists())->toBeFalse();
});

test('owners can reply from the facebook inbox', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response(['message_id' => 'm_inbox']),
    ]);

    $owner = User::factory()->withAllTools()->create();
    $profile = facebookProfile(['user_id' => $owner->id]);

    $this->actingAs($owner)
        ->post(route('facebook.inbox.send', ['restaurant' => $profile->username]), [
            'psid' => 'psid-5',
            'name' => 'Ada',
            'body' => 'Your table is ready.',
        ])
        ->assertRedirect();

    expect(FacebookMessage::query()->where('external_id', 'm_inbox')->exists())->toBeTrue();

    Http::assertSent(fn ($request) => $request['recipient']['id'] === 'psid-5'
        && $request['message']['text'] === 'Your table is ready.');
});

test('owners can update automations and manage keyword replies', function () {
    $owner = User::factory()->withAllTools()->create();
    $profile = facebookProfile(['user_id' => $owner->id]);

    $this->actingAs($owner)
        ->put(route('facebook.automations.update', ['restaurant' => $profile->username]), [
            'automations' => [[
                'key' => 'welcome',
                'is_enabled' => false,
                'message' => 'Hello {customer}, welcome to {page}.',
            ]],
        ])
        ->assertRedirect();

    $welcome = $profile->facebookAutomations()->where('key', 'welcome')->first();

    expect($welcome?->is_enabled)->toBeFalse()
        ->and($welcome?->message)->toBe('Hello {customer}, welcome to {page}.');

    $this->actingAs($owner)
        ->post(route('facebook.automations.keywords.store', ['restaurant' => $profile->username]), [
            'name' => 'Opening hours',
            'keywords' => 'hours, open',
            'message' => 'We are open from 9am to 10pm.',
        ])
        ->assertRedirect();

    $rule = $profile->facebookAutomations()->where('key', 'keyword')->first();

    expect($rule?->keywords)->toBe('hours, open');

    $this->actingAs($owner)
        ->patch(route('facebook.automations.keywords.update', [
            'restaurant' => $profile->username,
            'automation' => $rule?->id,
        ]), ['is_enabled' => false])
        ->assertRedirect();

    expect($rule?->refresh()->is_enabled)->toBeFalse();

    $this->actingAs($owner)
        ->delete(route('facebook.automations.keywords.destroy', [
            'restaurant' => $profile->username,
            'automation' => $rule?->id,
        ]))
        ->assertRedirect();

    expect(FacebookAutomation::query()->whereKey($rule?->id)->exists())->toBeFalse();
});
