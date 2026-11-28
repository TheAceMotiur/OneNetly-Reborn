<?php

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Profile;
use App\Models\Reservation;
use App\Models\SmsMessage;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

function smsRestaurant(?User $owner = null): Profile
{
    $profile = Profile::factory()->for($owner ?? User::factory())->restaurant()->create([
        'is_published' => true,
        'menu_ordering_enabled' => true,
        'accepting_orders' => true,
        'sms_notifications_enabled' => true,
    ]);

    $category = MenuCategory::factory()->for($profile)->create();
    MenuItem::factory()->for($category, 'category')->create([
        'price' => '10.00',
        'is_available' => true,
    ]);

    return $profile;
}

function fakeTextBeeSuccess(): void
{
    Http::fake([
        'api.textbee.test/*' => Http::response([
            'data' => [
                'success' => true,
                'message' => 'SMS added to queue for processing',
                'smsBatchId' => 'batch-123',
                'recipientCount' => 1,
            ],
        ]),
    ]);
}

beforeEach(function () {
    Mail::fake();

    config([
        'services.textbee.api_key' => 'test-key',
        'services.textbee.base_url' => 'https://api.textbee.test/api/v1',
        'services.textbee.device_id' => null,
    ]);

    app(SettingsService::class)->set('sms_enabled', true);
});

test('a new guest order sends an SMS through TextBee', function () {
    fakeTextBeeSuccess();

    $profile = smsRestaurant();
    $item = $profile->menuCategories()->sole()->items()->sole();

    $this->post(route('restaurant.order', $profile->username), [
        'customer_name' => 'Maya Patel',
        'customer_phone' => '+1 555 0102',
        'order_type' => 'takeaway',
        'items' => [['id' => $item->id, 'quantity' => 1]],
    ])->assertRedirect();

    $message = SmsMessage::query()->sole();

    expect($message->status)->toBe('queued')
        ->and($message->sms_batch_id)->toBe('batch-123')
        ->and($message->recipient)->toBe('+15550102')
        ->and($message->order_id)->not->toBeNull();

    Http::assertSent(fn ($request): bool => $request->url() === 'https://api.textbee.test/api/v1/gateway/send-sms'
        && $request->hasHeader('x-api-key', 'test-key')
        && $request['recipients'] === ['+15550102']);
});

test('SMS is skipped when the restaurant has not opted in', function () {
    $profile = smsRestaurant();
    $profile->update(['sms_notifications_enabled' => false]);
    $item = $profile->menuCategories()->sole()->items()->sole();

    $this->post(route('restaurant.order', $profile->username), [
        'customer_name' => 'Maya Patel',
        'customer_phone' => '+1 555 0102',
        'order_type' => 'takeaway',
        'items' => [['id' => $item->id, 'quantity' => 1]],
    ])->assertRedirect();

    expect(SmsMessage::query()->count())->toBe(0);
    Http::assertNothingSent();
});

test('an order status change texts the guest', function () {
    fakeTextBeeSuccess();

    $owner = User::factory()->create();
    $profile = smsRestaurant($owner);
    $order = Order::factory()->for($profile)->create([
        'customer_phone' => '+1 555 0199',
        'status' => 'new',
    ]);

    $this->actingAs($owner)
        ->patch(route('restaurant.orders.update', [$profile, $order]), ['status' => 'preparing'])
        ->assertRedirect();

    $message = SmsMessage::query()->sole();

    expect($message->status)->toBe('queued')
        ->and($message->body)->toContain($order->reference);
});

test('a confirmed reservation texts the guest', function () {
    fakeTextBeeSuccess();

    $owner = User::factory()->create();
    $profile = smsRestaurant($owner);
    $reservation = Reservation::factory()->for($profile)->create([
        'phone' => '+1 555 0111',
        'status' => Reservation::STATUS_PENDING,
    ]);

    $this->actingAs($owner)
        ->patch(route('restaurant.reservations.update', [$profile, $reservation]), [
            'status' => Reservation::STATUS_CONFIRMED,
        ])
        ->assertRedirect();

    expect(SmsMessage::query()->sole()->recipient)->toBe('+15550111');
});

test('TextBee errors are recorded without failing the request', function () {
    Http::fake([
        'api.textbee.test/*' => Http::response([
            'success' => false,
            'error' => 'No enabled device found.',
        ], 400),
    ]);

    $profile = smsRestaurant();
    $item = $profile->menuCategories()->sole()->items()->sole();

    $this->post(route('restaurant.order', $profile->username), [
        'customer_name' => 'Maya Patel',
        'customer_phone' => '+1 555 0102',
        'order_type' => 'takeaway',
        'items' => [['id' => $item->id, 'quantity' => 1]],
    ])->assertRedirect();

    expect(SmsMessage::query()->sole())->status->toBe('failed')
        ->error->toBe('No enabled device found.');
});
