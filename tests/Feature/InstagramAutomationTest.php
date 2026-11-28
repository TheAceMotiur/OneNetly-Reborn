<?php

use App\Enums\PurchaseStatus;
use App\Models\InstagramAutomation;
use App\Models\InstagramMessage;
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
function instagramProfile(array $attributes = []): Profile
{
    $credentialKeys = [
        'instagram_enabled',
        'instagram_username',
        'instagram_account_id',
        'instagram_access_token',
    ];

    $profile = Profile::factory()->create([
        'business_type' => Profile::RESTAURANT_TYPE,
        'is_published' => true,
        ...array_diff_key($attributes, array_flip($credentialKeys)),
    ]);

    User::query()->findOrFail($profile->user_id)->update([
        'instagram_enabled' => true,
        'instagram_username' => 'onenetlykitchen',
        'instagram_account_id' => '17841400000000000',
        'instagram_access_token' => 'instagram-token',
        ...array_intersect_key($attributes, array_flip($credentialKeys)),
    ]);

    Purchase::factory()
        ->for(User::query()->findOrFail($profile->user_id))
        ->create([
            'plan_id' => Plan::factory()->tool('instagram')->create()->id,
            'status' => PurchaseStatus::Completed,
        ]);

    return $profile;
}

function instagramSignature(array $payload, string $secret): string
{
    return 'sha256='.hash_hmac('sha256', (string) json_encode($payload), $secret);
}

test('the instagram page shows the connection and default automations', function () {
    $owner = User::factory()->withAllTools()->create();
    $profile = instagramProfile(['user_id' => $owner->id]);

    $this->actingAs($owner->refresh())
        ->get(route('instagram.index', ['restaurant' => $profile->username]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('instagram/Index')
            ->where('profile.username', $profile->username)
            ->where('connection.enabled', true)
            ->has('automations', 3)
            ->has('stats'));
});

test('guests cannot open instagram automation', function () {
    $this->get(route('instagram.index'))->assertRedirect(route('login'));
});

test('owners can save instagram credentials and the token stays encrypted', function () {
    $owner = User::factory()->withAllTools()->create();
    $profile = instagramProfile([
        'user_id' => $owner->id,
        'instagram_access_token' => null,
    ]);

    $this->actingAs($owner)
        ->put(route('instagram.settings.update', ['restaurant' => $profile->username]), [
            'enabled' => true,
            'username' => '@onenetlykitchen',
            'account_id' => '17841400000000001',
            'access_token' => 'instagram-secret',
        ])
        ->assertRedirect();

    $owner->refresh();

    expect($owner->instagram_access_token)->toBe('instagram-secret')
        ->and($owner->instagram_username)->toBe('onenetlykitchen')
        ->and($owner->instagram_account_id)->toBe('17841400000000001')
        ->and(DB::table('users')->where('id', $owner->id)->value('instagram_access_token'))
        ->not->toBe('instagram-secret');
});

test('saving instagram settings with a blank token keeps the saved token', function () {
    $owner = User::factory()->withAllTools()->create();
    $profile = instagramProfile(['user_id' => $owner->id]);

    $this->actingAs($owner)
        ->put(route('instagram.settings.update', ['restaurant' => $profile->username]), [
            'enabled' => true,
            'username' => 'onenetlykitchen',
            'account_id' => '17841400000000000',
            'access_token' => '',
        ])
        ->assertRedirect();

    expect($owner->refresh()->instagram_access_token)->toBe('instagram-token');

    $this->actingAs($owner)
        ->put(route('instagram.settings.update', ['restaurant' => $profile->username]), [
            'enabled' => false,
            'remove_token' => true,
        ])
        ->assertRedirect();

    expect($owner->refresh()->instagram_access_token)->toBeNull();
});

test('the instagram connection test calls the graph api', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response([
            'id' => '17841400000000000',
            'username' => 'onenetlykitchen',
        ]),
    ]);

    $owner = User::factory()->withAllTools()->create();
    $profile = instagramProfile(['user_id' => $owner->id]);

    $this->actingAs($owner->refresh())
        ->post(route('instagram.settings.test', ['restaurant' => $profile->username]))
        ->assertRedirect();

    Http::assertSent(fn ($request) => str_contains($request->url(), '/17841400000000000')
        && $request->hasHeader('Authorization', 'Bearer instagram-token'));
});

test('the instagram webhook verification challenge requires the configured token', function () {
    app(SettingsService::class)->set('instagram_verify_token', 'ig-verify');

    $this->get('/webhooks/instagram?hub_mode=subscribe&hub_verify_token=ig-verify&hub_challenge=xyz789')
        ->assertOk()
        ->assertSee('xyz789');

    $this->get('/webhooks/instagram?hub_mode=subscribe&hub_verify_token=nope&hub_challenge=xyz789')
        ->assertForbidden();
});

test('a signed inbound instagram webhook stores the message and sends a welcome', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response(['message_id' => 'm_welcome']),
    ]);

    app(SettingsService::class)->set('instagram_app_secret', 'ig-secret');

    $profile = instagramProfile();

    $payload = [
        'object' => 'instagram',
        'entry' => [[
            'id' => '17841400000000000',
            'messaging' => [[
                'sender' => ['id' => 'igsid-1'],
                'recipient' => ['id' => '17841400000000000'],
                'message' => [
                    'mid' => 'mid.inbound',
                    'text' => 'Hello there',
                ],
            ]],
        ]],
    ];

    $this->call('POST', '/webhooks/instagram', [], [], [], [
        'HTTP_X_HUB_SIGNATURE_256' => instagramSignature($payload, 'ig-secret'),
        'CONTENT_TYPE' => 'application/json',
    ], json_encode($payload))->assertOk();

    $inbound = InstagramMessage::query()->where('external_id', 'mid.inbound')->first();

    expect($inbound?->direction)->toBe('inbound')
        ->and($inbound?->body)->toBe('Hello there')
        ->and(InstagramMessage::query()->where('external_id', 'm_welcome')->exists())->toBeTrue();

    Http::assertSent(fn ($request) => str_contains($request->url(), '/17841400000000000/messages')
        && $request['recipient']['id'] === 'igsid-1'
        && str_contains((string) $request['message']['text'], 'thanks for messaging'));
});

test('instagram keyword automations reply before the welcome message', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response(['message_id' => 'm_keyword']),
    ]);

    app(SettingsService::class)->set('instagram_app_secret', 'ig-secret');

    $profile = instagramProfile();

    InstagramAutomation::factory()->for($profile)->keyword()->create();

    $payload = [
        'object' => 'instagram',
        'entry' => [[
            'id' => '17841400000000000',
            'messaging' => [[
                'sender' => ['id' => 'igsid-2'],
                'recipient' => ['id' => '17841400000000000'],
                'message' => ['mid' => 'mid.keyword', 'text' => 'Can I see the menu?'],
            ]],
        ]],
    ];

    $this->call('POST', '/webhooks/instagram', [], [], [], [
        'HTTP_X_HUB_SIGNATURE_256' => instagramSignature($payload, 'ig-secret'),
        'CONTENT_TYPE' => 'application/json',
    ], json_encode($payload))->assertOk();

    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => str_contains((string) $request['message']['text'], 'Here is our menu'));
});

test('new instagram comments receive the comment automation reply', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response(['id' => 'comment_reply_1']),
    ]);

    app(SettingsService::class)->set('instagram_app_secret', 'ig-secret');

    $profile = instagramProfile();

    $payload = [
        'object' => 'instagram',
        'entry' => [[
            'id' => '17841400000000000',
            'changes' => [[
                'field' => 'comments',
                'value' => [
                    'id' => 'comment_1',
                    'text' => 'Lovely photos!',
                    'from' => ['id' => 'igsid-3', 'username' => 'ada'],
                    'media' => ['id' => 'media_1'],
                ],
            ]],
        ]],
    ];

    $this->call('POST', '/webhooks/instagram', [], [], [], [
        'HTTP_X_HUB_SIGNATURE_256' => instagramSignature($payload, 'ig-secret'),
        'CONTENT_TYPE' => 'application/json',
    ], json_encode($payload))->assertOk();

    expect(InstagramMessage::query()->where('external_id', 'comment_1')->exists())->toBeTrue();

    Http::assertSent(fn ($request) => str_contains($request->url(), '/comment_1/replies')
        && str_contains((string) $request['message'], 'Thanks for your comment, ada'));
});

test('instagram comment replies only answer matching keywords when keywords are set', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response(['id' => 'comment_reply_2']),
    ]);

    app(SettingsService::class)->set('instagram_app_secret', 'ig-secret');

    $profile = instagramProfile();

    $profile->instagramAutomations()->create([
        'key' => 'comment',
        'message' => 'Thanks for your comment, {customer}.',
        'keywords' => 'price, menu',
        'is_enabled' => true,
    ]);

    $payload = [
        'object' => 'instagram',
        'entry' => [[
            'id' => '17841400000000000',
            'changes' => [[
                'field' => 'comments',
                'value' => [
                    'id' => 'comment_2',
                    'text' => 'Lovely photos!',
                    'from' => ['id' => 'igsid-6', 'username' => 'ada'],
                ],
            ]],
        ]],
    ];

    $this->call('POST', '/webhooks/instagram', [], [], [], [
        'HTTP_X_HUB_SIGNATURE_256' => instagramSignature($payload, 'ig-secret'),
        'CONTENT_TYPE' => 'application/json',
    ], json_encode($payload))->assertOk();

    Http::assertNothingSent();
});

test('matching instagram comments receive a comment to dm private reply', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response(['message_id' => 'ig_private_reply']),
    ]);

    app(SettingsService::class)->set('instagram_app_secret', 'ig-secret');

    $profile = instagramProfile();

    $profile->instagramAutomations()->create([
        'key' => 'comment',
        'message' => 'Thanks for your comment, {customer}.',
        'is_enabled' => false,
    ]);

    $profile->instagramAutomations()->create([
        'key' => 'comment_dm',
        'message' => 'Here is the link you asked for.',
        'keywords' => 'link',
        'is_enabled' => true,
    ]);

    $payload = [
        'object' => 'instagram',
        'entry' => [[
            'id' => '17841400000000000',
            'changes' => [[
                'field' => 'comments',
                'value' => [
                    'id' => 'comment_3',
                    'text' => 'Can you send the link?',
                    'from' => ['id' => 'igsid-7', 'username' => 'ada'],
                ],
            ]],
        ]],
    ];

    $this->call('POST', '/webhooks/instagram', [], [], [], [
        'HTTP_X_HUB_SIGNATURE_256' => instagramSignature($payload, 'ig-secret'),
        'CONTENT_TYPE' => 'application/json',
    ], json_encode($payload))->assertOk();

    Http::assertSent(fn ($request) => str_contains($request->url(), '/17841400000000000/messages')
        && $request['recipient']['comment_id'] === 'comment_3'
        && $request['message']['text'] === 'Here is the link you asked for.');

    expect(InstagramMessage::query()->where('channel', 'private_reply')->exists())->toBeTrue();
});

test('an instagram webhook with a bad signature is rejected', function () {
    app(SettingsService::class)->set('instagram_app_secret', 'ig-secret');

    $profile = instagramProfile();

    $payload = [
        'object' => 'instagram',
        'entry' => [[
            'id' => '17841400000000000',
            'messaging' => [[
                'sender' => ['id' => 'igsid-4'],
                'message' => ['mid' => 'mid.bad', 'text' => 'Hello'],
            ]],
        ]],
    ];

    $this->call('POST', '/webhooks/instagram', [], [], [], [
        'HTTP_X_HUB_SIGNATURE_256' => 'sha256=wrong',
        'CONTENT_TYPE' => 'application/json',
    ], json_encode($payload))->assertUnauthorized();

    expect(InstagramMessage::query()->where('external_id', 'mid.bad')->exists())->toBeFalse();
});

test('owners can reply from the instagram inbox', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response(['message_id' => 'm_inbox']),
    ]);

    $owner = User::factory()->withAllTools()->create();
    $profile = instagramProfile(['user_id' => $owner->id]);

    $this->actingAs($owner)
        ->post(route('instagram.inbox.send', ['restaurant' => $profile->username]), [
            'igsid' => 'igsid-5',
            'name' => 'Ada',
            'body' => 'Your table is ready.',
        ])
        ->assertRedirect();

    expect(InstagramMessage::query()->where('external_id', 'm_inbox')->exists())->toBeTrue();

    Http::assertSent(fn ($request) => $request['recipient']['id'] === 'igsid-5'
        && $request['message']['text'] === 'Your table is ready.');
});

test('owners can update automations and manage keyword replies', function () {
    $owner = User::factory()->withAllTools()->create();
    $profile = instagramProfile(['user_id' => $owner->id]);

    $this->actingAs($owner)
        ->put(route('instagram.automations.update', ['restaurant' => $profile->username]), [
            'automations' => [[
                'key' => 'welcome',
                'is_enabled' => false,
                'message' => 'Hello {customer}, welcome to {account}.',
            ]],
        ])
        ->assertRedirect();

    $welcome = $profile->instagramAutomations()->where('key', 'welcome')->first();

    expect($welcome?->is_enabled)->toBeFalse()
        ->and($welcome?->message)->toBe('Hello {customer}, welcome to {account}.');

    $this->actingAs($owner)
        ->post(route('instagram.automations.keywords.store', ['restaurant' => $profile->username]), [
            'name' => 'Opening hours',
            'keywords' => 'hours, open',
            'message' => 'We are open from 9am to 10pm.',
        ])
        ->assertRedirect();

    $rule = $profile->instagramAutomations()->where('key', 'keyword')->first();

    expect($rule?->keywords)->toBe('hours, open');

    $this->actingAs($owner)
        ->patch(route('instagram.automations.keywords.update', [
            'restaurant' => $profile->username,
            'automation' => $rule?->id,
        ]), ['is_enabled' => false])
        ->assertRedirect();

    expect($rule?->refresh()->is_enabled)->toBeFalse();

    $this->actingAs($owner)
        ->delete(route('instagram.automations.keywords.destroy', [
            'restaurant' => $profile->username,
            'automation' => $rule?->id,
        ]))
        ->assertRedirect();

    expect(InstagramAutomation::query()->whereKey($rule?->id)->exists())->toBeFalse();
});
