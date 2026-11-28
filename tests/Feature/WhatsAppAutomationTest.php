<?php

use App\Enums\OrderStatus;
use App\Enums\PurchaseStatus;
use App\Models\AutomationActivity;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Profile;
use App\Models\Purchase;
use App\Models\User;
use App\Models\WhatsAppAutomation;
use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppMessage;
use App\Services\SettingsService;
use App\Services\WhatsAppCampaignSender;
use App\Services\WhatsAppNotifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * @param  array<string, mixed>  $attributes
 */
function whatsappProfile(array $attributes = []): Profile
{
    $credentialKeys = [
        'whatsapp_enabled',
        'whatsapp_display_number',
        'whatsapp_phone_number_id',
        'whatsapp_business_account_id',
        'whatsapp_access_token',
        'whatsapp_order_updates',
        'whatsapp_reservation_updates',
    ];

    $profile = Profile::factory()->create([
        'business_type' => Profile::RESTAURANT_TYPE,
        'is_published' => true,
        ...array_diff_key($attributes, array_flip($credentialKeys)),
    ]);

    User::query()->findOrFail($profile->user_id)->update([
        'whatsapp_enabled' => true,
        'whatsapp_display_number' => '+15550001111',
        'whatsapp_phone_number_id' => '1234567890',
        'whatsapp_access_token' => 'test-token',
        'whatsapp_order_updates' => true,
        'whatsapp_reservation_updates' => true,
        ...array_intersect_key($attributes, array_flip($credentialKeys)),
    ]);

    Purchase::factory()
        ->for(User::query()->findOrFail($profile->user_id))
        ->create([
            'plan_id' => Plan::factory()->tool('whatsapp')->create()->id,
            'status' => PurchaseStatus::Completed,
        ]);

    return $profile;
}

function whatsappWebhookPayload(string $phoneNumberId, array $value): array
{
    return [
        'entry' => [[
            'changes' => [[
                'value' => [
                    'metadata' => ['phone_number_id' => $phoneNumberId],
                    ...$value,
                ],
            ]],
        ]],
    ];
}

test('the whatsapp page shows the connection and default automations', function () {
    $owner = User::factory()->withAllTools()->create();
    $profile = whatsappProfile(['user_id' => $owner->id]);

    $this->actingAs($owner->refresh())
        ->get(route('whatsapp.index', ['restaurant' => $profile->username]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('whatsapp/Index')
            ->where('profile.username', $profile->username)
            ->where('connection.enabled', true)
            ->has('automations', 6)
            ->has('stats'));
});

test('guests cannot open whatsapp automation', function () {
    $this->get(route('whatsapp.index'))->assertRedirect(route('login'));
});

test('owners can save whatsapp credentials and the token stays encrypted', function () {
    $owner = User::factory()->withAllTools()->create();
    $profile = whatsappProfile(['user_id' => $owner->id, 'whatsapp_access_token' => null]);

    $this->actingAs($owner)
        ->put(route('whatsapp.settings.update', ['restaurant' => $profile->username]), [
            'enabled' => true,
            'display_number' => '+15550002222',
            'phone_number_id' => '999888',
            'business_account_id' => '777',
            'access_token' => 'secret-token',
            'order_updates' => true,
            'reservation_updates' => false,
        ])
        ->assertRedirect();

    $owner->refresh();

    expect($owner->whatsapp_access_token)->toBe('secret-token')
        ->and($owner->whatsapp_reservation_updates)->toBeFalse()
        ->and(DB::table('users')->where('id', $owner->id)->value('whatsapp_access_token'))
        ->not->toBe('secret-token');
});

test('saving settings with a blank token keeps the saved token', function () {
    $owner = User::factory()->withAllTools()->create();
    $profile = whatsappProfile(['user_id' => $owner->id]);

    $this->actingAs($owner)
        ->put(route('whatsapp.settings.update', ['restaurant' => $profile->username]), [
            'enabled' => true,
            'display_number' => '+15550001111',
            'phone_number_id' => '1234567890',
            'business_account_id' => '',
            'access_token' => '',
            'order_updates' => true,
            'reservation_updates' => true,
        ])
        ->assertRedirect();

    expect($owner->refresh()->whatsapp_access_token)->toBe('test-token');

    $this->actingAs($owner)
        ->put(route('whatsapp.settings.update', ['restaurant' => $profile->username]), [
            'enabled' => false,
            'order_updates' => true,
            'reservation_updates' => true,
            'remove_token' => true,
        ])
        ->assertRedirect();

    expect($owner->refresh()->whatsapp_access_token)->toBeNull();
});

test('the connection test calls the graph api', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response([
            'verified_name' => 'OneNetly Kitchen',
            'display_phone_number' => '+15550001111',
        ]),
    ]);

    $owner = User::factory()->withAllTools()->create();
    $profile = whatsappProfile(['user_id' => $owner->id]);

    $this->actingAs($owner->refresh())
        ->post(route('whatsapp.settings.test', ['restaurant' => $profile->username]))
        ->assertRedirect();

    Http::assertSent(fn ($request) => str_contains($request->url(), '/1234567890')
        && $request->hasHeader('Authorization', 'Bearer test-token'));
});

test('the webhook verification challenge requires the configured token', function () {
    app(SettingsService::class)->set('whatsapp_verify_token', 'verify-me');

    $this->get('/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=verify-me&hub_challenge=abc123')
        ->assertOk()
        ->assertSee('abc123');

    $this->get('/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=wrong&hub_challenge=abc123')
        ->assertForbidden();
});

test('a signed inbound webhook stores the contact and message', function () {
    Http::fake();

    app(SettingsService::class)->set('whatsapp_app_secret', 'app-secret');

    $profile = whatsappProfile();

    $payload = json_encode(whatsappWebhookPayload($profile->user?->whatsapp_phone_number_id ?? '', [
        'contacts' => [['profile' => ['name' => 'Ada']]],
        'messages' => [[
            'from' => '15551234567',
            'id' => 'wamid.inbound',
            'type' => 'text',
            'text' => ['body' => 'Do you have a table tonight?'],
        ]],
    ]));

    $signature = 'sha256='.hash_hmac('sha256', (string) $payload, 'app-secret');

    $this->call('POST', '/webhooks/whatsapp', [], [], [], [
        'HTTP_X_HUB_SIGNATURE_256' => $signature,
        'CONTENT_TYPE' => 'application/json',
    ], $payload)->assertOk();

    $contact = WhatsAppContact::query()->where('phone', '15551234567')->first();
    $message = WhatsAppMessage::query()->where('external_id', 'wamid.inbound')->first();

    expect($contact?->name)->toBe('Ada')
        ->and($contact?->source)->toBe('whatsapp')
        ->and($message?->direction)->toBe('inbound')
        ->and($message?->body)->toBe('Do you have a table tonight?');
});

test('a webhook with a bad signature is rejected', function () {
    app(SettingsService::class)->set('whatsapp_app_secret', 'app-secret');

    $profile = whatsappProfile();

    $payload = json_encode(whatsappWebhookPayload($profile->user?->whatsapp_phone_number_id ?? '', [
        'messages' => [[
            'from' => '15551234567',
            'id' => 'wamid.bad',
            'type' => 'text',
            'text' => ['body' => 'Hello'],
        ]],
    ]));

    $this->call('POST', '/webhooks/whatsapp', [], [], [], [
        'HTTP_X_HUB_SIGNATURE_256' => 'sha256=wrong',
        'CONTENT_TYPE' => 'application/json',
    ], $payload)->assertUnauthorized();

    expect(WhatsAppMessage::query()->where('external_id', 'wamid.bad')->exists())->toBeFalse();
});

test('delivery status webhooks update the outbound message', function () {
    app(SettingsService::class)->set('whatsapp_app_secret', 'app-secret');

    $message = WhatsAppMessage::factory()->create([
        'external_id' => 'wamid.outbound',
        'status' => 'sent',
    ]);

    $payload = json_encode([
        'entry' => [[
            'changes' => [[
                'value' => [
                    'statuses' => [[
                        'id' => 'wamid.outbound',
                        'status' => 'delivered',
                    ]],
                ],
            ]],
        ]],
    ]);

    $signature = 'sha256='.hash_hmac('sha256', (string) $payload, 'app-secret');

    $this->call('POST', '/webhooks/whatsapp', [], [], [], [
        'HTTP_X_HUB_SIGNATURE_256' => $signature,
        'CONTENT_TYPE' => 'application/json',
    ], $payload)->assertOk();

    expect($message->refresh()->status)->toBe('delivered');
});

test('order automations send a whatsapp message with placeholders replaced', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.out']]]),
    ]);

    $profile = whatsappProfile();
    $order = Order::factory()->for($profile)->create([
        'customer_name' => 'Ada',
        'customer_phone' => '15551234567',
        'status' => OrderStatus::New,
    ]);

    app(WhatsAppNotifier::class)->orderPlaced($order);

    Http::assertSent(fn ($request) => str_contains($request->url(), '/1234567890/messages')
        && $request['to'] === '15551234567'
        && str_contains((string) $request['text']['body'], 'Ada'));

    expect(WhatsAppMessage::query()->where('external_id', 'wamid.out')->exists())->toBeTrue();
});

test('disabled automations do not send messages', function () {
    Http::fake();

    $profile = whatsappProfile();
    WhatsAppAutomation::factory()->for($profile)->create([
        'key' => 'order_received',
        'is_enabled' => false,
    ]);

    $order = Order::factory()->for($profile)->create([
        'customer_phone' => '15551234567',
        'status' => OrderStatus::New,
    ]);

    app(WhatsAppNotifier::class)->orderPlaced($order);

    Http::assertNothingSent();
});

test('campaigns send only to opted-in contacts and count the results', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.campaign']]]),
    ]);

    $profile = whatsappProfile();

    WhatsAppContact::factory()->for($profile)->create([
        'phone' => '15550000001',
        'name' => 'Ada',
        'opted_in' => true,
    ]);
    WhatsAppContact::factory()->for($profile)->create([
        'phone' => '15550000002',
        'opted_in' => false,
    ]);

    $campaign = WhatsAppCampaign::factory()->for($profile)->create([
        'message' => 'Hi {name}, a table is ready.',
        'status' => 'draft',
    ]);

    $campaign = app(WhatsAppCampaignSender::class)->send($campaign);

    expect($campaign->sent_count)->toBe(1)
        ->and($campaign->failed_count)->toBe(0)
        ->and($campaign->status)->toBe('sent');

    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => $request['to'] === '15550000001'
        && str_contains((string) $request['text']['body'], 'Hi Ada'));
});

test('owners can create campaigns and update automations', function () {
    $owner = User::factory()->withAllTools()->create();
    $profile = whatsappProfile(['user_id' => $owner->id]);

    $this->actingAs($owner)
        ->post(route('whatsapp.campaigns.store', ['restaurant' => $profile->username]), [
            'name' => 'Weekend special',
            'message' => 'Hi {name}, 20% off this weekend.',
        ])
        ->assertRedirect();

    expect($profile->whatsappCampaigns()->where('name', 'Weekend special')->exists())->toBeTrue();

    $this->actingAs($owner)
        ->put(route('whatsapp.automations.update', ['restaurant' => $profile->username]), [
            'automations' => [[
                'key' => 'order_status',
                'is_enabled' => false,
                'message' => 'Order {reference} is {status}.',
                'template_name' => '',
                'language' => 'en_US',
            ]],
        ])
        ->assertRedirect();

    $automation = $profile->whatsappAutomations()->where('key', 'order_status')->first();

    expect($automation?->is_enabled)->toBeFalse()
        ->and($automation?->message)->toBe('Order {reference} is {status}.');
});

test('owners can manage whatsapp keyword replies', function () {
    $owner = User::factory()->withAllTools()->create();
    $profile = whatsappProfile(['user_id' => $owner->id]);

    $this->actingAs($owner)
        ->post(route('whatsapp.automations.keywords.store', ['restaurant' => $profile->username]), [
            'name' => 'Menu',
            'keywords' => 'menu, food',
            'message' => 'Here is our menu: {restaurant}',
        ])
        ->assertRedirect();

    $rule = $profile->whatsappAutomations()->where('key', 'keyword')->first();

    expect($rule?->keywords)->toBe('menu, food');

    $this->actingAs($owner)
        ->patch(route('whatsapp.automations.keywords.update', [
            'restaurant' => $profile->username,
            'automation' => $rule?->id,
        ]), ['is_enabled' => false])
        ->assertRedirect();

    expect($rule?->refresh()->is_enabled)->toBeFalse();

    $this->actingAs($owner)
        ->delete(route('whatsapp.automations.keywords.destroy', [
            'restaurant' => $profile->username,
            'automation' => $rule?->id,
        ]))
        ->assertRedirect();

    expect(WhatsAppAutomation::query()->whereKey($rule?->id)->exists())->toBeFalse();
});

test('inbound keywords are answered before the welcome automation', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.keyword']]]),
    ]);

    app(SettingsService::class)->set('whatsapp_app_secret', 'app-secret');

    $profile = whatsappProfile();

    $profile->whatsappAutomations()->create([
        'key' => 'keyword',
        'name' => 'Menu',
        'keywords' => 'menu',
        'message' => 'Our menu is at {restaurant}',
        'is_enabled' => true,
    ]);

    $payload = json_encode(whatsappWebhookPayload($profile->user?->whatsapp_phone_number_id ?? '', [
        'contacts' => [['profile' => ['name' => 'Ada']]],
        'messages' => [[
            'from' => '15551234567',
            'id' => 'wamid.keyword',
            'type' => 'text',
            'text' => ['body' => 'Can I see the menu?'],
        ]],
    ]));

    $signature = 'sha256='.hash_hmac('sha256', (string) $payload, 'app-secret');

    $this->call('POST', '/webhooks/whatsapp', [], [], [], [
        'HTTP_X_HUB_SIGNATURE_256' => $signature,
        'CONTENT_TYPE' => 'application/json',
    ], $payload)->assertOk();

    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => str_contains((string) $request['text']['body'], 'Our menu is at'));

    expect(AutomationActivity::query()->where('automation_key', 'keyword')->where('status', 'sent')->exists())->toBeTrue();
});

test('contact sync imports phone numbers from orders', function () {
    $owner = User::factory()->withAllTools()->create();
    $profile = whatsappProfile(['user_id' => $owner->id]);

    Order::factory()->for($profile)->create([
        'customer_name' => 'Ada',
        'customer_phone' => '+1 (555) 000-0003',
    ]);

    $this->actingAs($owner)
        ->post(route('whatsapp.contacts.sync', ['restaurant' => $profile->username]))
        ->assertRedirect();

    $contact = WhatsAppContact::query()->where('phone', '15550000003')->first();

    expect($contact?->name)->toBe('Ada')
        ->and($contact?->source)->toBe('order')
        ->and($contact?->opted_in)->toBeTrue();
});
