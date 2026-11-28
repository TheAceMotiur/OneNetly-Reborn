<?php

use App\Models\AuditLog;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

function deliveryProfile(array $attributes = []): Profile
{
    $profile = Profile::factory()->restaurant()->create([
        'is_published' => true,
        'menu_ordering_enabled' => true,
        'accepting_orders' => true,
        'delivery_enabled' => true,
        'delivery_fee_cents' => 100,
        'menu_currency' => 'USD',
        ...$attributes,
    ]);

    $category = MenuCategory::factory()->for($profile)->create(['name' => 'Mains']);

    MenuItem::factory()->for($category, 'category')->create([
        'name' => 'Truffle pasta',
        'price' => '12.50',
        'is_available' => true,
    ]);

    return $profile;
}

test('owners manage delivery zones and the change is logged', function () {
    $owner = User::factory()->create();
    $profile = deliveryProfile(['user_id' => $owner->id]);

    $this->actingAs($owner)
        ->post(route('restaurant.delivery.store', $profile), [
            'name' => 'City centre',
            'postcodes' => '1000, 1001, ab1',
            'fee' => '3.50',
            'min_order' => '15.00',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->actingAs($owner)
        ->get(route('restaurant.delivery', $profile))
        ->assertInertia(fn ($page) => $page
            ->component('delivery/Index')
            ->has('zones', 1)
            ->where('restaurant.username', $profile->username));

    $zone = $profile->deliveryZones()->sole();

    expect($zone->postcodes)->toBe(['1000', '1001', 'AB1'])
        ->and($zone->fee_cents)->toBe(350)
        ->and($zone->min_order_cents)->toBe(1500)
        ->and(AuditLog::query()->where('action', 'delivery.zone_created')->exists())->toBeTrue();

    $this->actingAs($owner)
        ->delete(route('restaurant.delivery.destroy', [$profile, $zone]))
        ->assertRedirect();

    expect($profile->deliveryZones()->count())->toBe(0);
});

test('a delivery order must match a zone and uses that zone fee', function () {
    Mail::fake();

    $profile = deliveryProfile();
    $item = $profile->menuCategories()->sole()->items()->sole();

    $profile->deliveryZones()->create([
        'name' => 'Nearby',
        'postcodes' => ['20'],
        'fee_cents' => 300,
        'min_order_cents' => 0,
        'is_active' => true,
    ]);

    $order = [
        'customer_name' => 'Maya Patel',
        'customer_phone' => '+1 555 0102',
        'order_type' => 'delivery',
        'delivery_address' => '12 Sample street',
        'items' => [
            ['id' => $item->id, 'quantity' => 1],
        ],
    ];

    $this->post(route('restaurant.order', $profile->username), [
        ...$order,
        'delivery_postcode' => '999',
    ])->assertSessionHasErrors('delivery_postcode');

    $this->post(route('restaurant.order', $profile->username), [
        ...$order,
        'delivery_postcode' => '2000 AB',
    ])->assertRedirect();

    $placed = Order::query()->sole();

    expect($placed->delivery_postcode)->toBe('2000 AB')
        ->and($placed->delivery_fee_cents)->toBe(300);
});

test('a zone minimum order is enforced', function () {
    Mail::fake();

    $profile = deliveryProfile();
    $item = $profile->menuCategories()->sole()->items()->sole();

    $profile->deliveryZones()->create([
        'name' => 'Far away',
        'postcodes' => ['30'],
        'fee_cents' => 500,
        'min_order_cents' => 5000,
        'is_active' => true,
    ]);

    $this->post(route('restaurant.order', $profile->username), [
        'customer_name' => 'Maya Patel',
        'customer_phone' => '+1 555 0102',
        'order_type' => 'delivery',
        'delivery_address' => '12 Sample street',
        'delivery_postcode' => '3000',
        'items' => [
            ['id' => $item->id, 'quantity' => 1],
        ],
    ])->assertSessionHasErrors('items');

    expect(Order::query()->count())->toBe(0);
});

test('orders can be scheduled and the time is stored', function () {
    Mail::fake();

    $profile = deliveryProfile();
    $item = $profile->menuCategories()->sole()->items()->sole();
    $scheduledFor = now()->addDay()->setHour(18)->setMinute(30);

    $this->post(route('restaurant.order', $profile->username), [
        'customer_name' => 'Maya Patel',
        'customer_phone' => '+1 555 0102',
        'order_type' => 'takeaway',
        'scheduled_for' => $scheduledFor->toDateTimeString(),
        'items' => [
            ['id' => $item->id, 'quantity' => 1],
        ],
    ])->assertRedirect();

    expect(Order::query()->sole()->scheduled_for?->toDateTimeString())
        ->toBe($scheduledFor->toDateTimeString());
});

test('orders cannot be scheduled more than two weeks ahead', function () {
    Mail::fake();

    $profile = deliveryProfile();
    $item = $profile->menuCategories()->sole()->items()->sole();

    $this->post(route('restaurant.order', $profile->username), [
        'customer_name' => 'Maya Patel',
        'customer_phone' => '+1 555 0102',
        'order_type' => 'takeaway',
        'scheduled_for' => now()->addDays(20)->toDateTimeString(),
        'items' => [
            ['id' => $item->id, 'quantity' => 1],
        ],
    ])->assertSessionHasErrors('scheduled_for');

    expect(Order::query()->count())->toBe(0);
});
