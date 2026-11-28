<?php

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Profile;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @return array{0: Profile, 1: MenuItem}
 */
function checkoutRestaurant(array $attributes = []): array
{
    $profile = Profile::factory()->restaurant()->create([
        'is_published' => true,
        'menu_ordering_enabled' => true,
        'accepting_orders' => true,
        'delivery_enabled' => true,
        'delivery_fee_cents' => 300,
        'min_order_cents' => 0,
        'tax_rate' => 10,
        'service_rate' => 5,
        'menu_currency' => 'USD',
        ...$attributes,
    ]);

    $category = MenuCategory::factory()->for($profile)->create();

    $item = MenuItem::factory()->for($category, 'category')->create([
        'name' => 'Burger',
        'price' => '10.00',
        'is_available' => true,
    ]);

    return [$profile, $item];
}

test('delivery orders add delivery, service, tax and tip to the total', function () {
    [$profile, $item] = checkoutRestaurant();

    $this->post(route('restaurant.order', $profile->username), [
        'customer_name' => 'Maya Patel',
        'customer_phone' => '+1 555 0102',
        'order_type' => 'delivery',
        'delivery_address' => '12 Market Street, Apt 4',
        'tip_cents' => 200,
        'items' => [['id' => $item->id, 'quantity' => 2]],
    ])->assertRedirect()->assertSessionHasNoErrors();

    $order = Order::query()->sole();

    expect($order->subtotal_cents)->toBe(2000)
        ->and($order->delivery_fee_cents)->toBe(300)
        ->and($order->service_cents)->toBe(100)
        ->and($order->tax_cents)->toBe(240)
        ->and($order->tip_cents)->toBe(200)
        ->and($order->total_cents)->toBe(2840)
        ->and($order->delivery_address)->toBe('12 Market Street, Apt 4');
});

test('delivery orders require an address and an enabled delivery option', function () {
    [$profile, $item] = checkoutRestaurant();

    $this->post(route('restaurant.order', $profile->username), [
        'customer_name' => 'Maya Patel',
        'customer_phone' => '+1 555 0102',
        'order_type' => 'delivery',
        'items' => [['id' => $item->id, 'quantity' => 1]],
    ])->assertSessionHasErrors('delivery_address');

    $profile->update(['delivery_enabled' => false]);

    $this->post(route('restaurant.order', $profile->username), [
        'customer_name' => 'Maya Patel',
        'customer_phone' => '+1 555 0102',
        'order_type' => 'delivery',
        'delivery_address' => '12 Market Street',
        'items' => [['id' => $item->id, 'quantity' => 1]],
    ])->assertSessionHasErrors('order_type');

    expect(Order::query()->count())->toBe(0);
});

test('orders below the restaurant minimum are rejected', function () {
    [$profile, $item] = checkoutRestaurant(['min_order_cents' => 2500]);

    $this->post(route('restaurant.order', $profile->username), [
        'customer_name' => 'Maya Patel',
        'customer_phone' => '+1 555 0102',
        'order_type' => 'takeaway',
        'items' => [['id' => $item->id, 'quantity' => 1]],
    ])->assertSessionHasErrors('items');

    expect(Order::query()->count())->toBe(0);
});

test('guests can track an order by its reference', function () {
    [$profile, $item] = checkoutRestaurant();

    $this->post(route('restaurant.order', $profile->username), [
        'customer_name' => 'Maya Patel',
        'customer_phone' => '+1 555 0102',
        'order_type' => 'takeaway',
        'items' => [['id' => $item->id, 'quantity' => 1]],
    ]);

    $order = Order::query()->sole();

    $this->get(route('restaurant.track', [$profile->username, $order->reference]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('restaurant/Track')
            ->where('order.reference', $order->reference)
            ->where('order.status', 'new')
            ->where('order.total', '11.55 USD')
            ->has('order.items', 1)
            ->has('steps', 4));

    $other = Profile::factory()->restaurant()->create(['username' => 'other-kitchen']);

    $this->get(route('restaurant.track', [$other->username, $order->reference]))
        ->assertNotFound();
});

test('owners can configure checkout charges', function () {
    $user = User::factory()->create();
    $profile = Profile::factory()->for($user)->restaurant()->create();

    $this->actingAs($user)
        ->patch(route('restaurant.update', $profile), [
            'name' => 'Joe\'s Pizza',
            'delivery_enabled' => true,
            'delivery_fee' => '3.50',
            'min_order' => '12.00',
            'tax_rate' => '8.25',
            'service_rate' => '2.5',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($profile->refresh())
        ->delivery_enabled->toBeTrue()
        ->delivery_fee_cents->toBe(350)
        ->min_order_cents->toBe(1200)
        ->tax_rate->toBe(8.25)
        ->service_rate->toBe(2.5);
});
