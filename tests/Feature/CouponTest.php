<?php

use App\Models\Coupon;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Profile;
use App\Models\User;

/**
 * @return array{0: Profile, 1: MenuItem}
 */
function couponRestaurant(array $attributes = []): array
{
    $profile = Profile::factory()->restaurant()->create([
        'is_published' => true,
        'menu_ordering_enabled' => true,
        'accepting_orders' => true,
        'tax_rate' => 0,
        'service_rate' => 0,
        'menu_currency' => 'USD',
        ...$attributes,
    ]);

    $category = MenuCategory::factory()->for($profile)->create();

    $item = MenuItem::factory()->for($category, 'category')->create([
        'price' => '10.00',
        'is_available' => true,
    ]);

    return [$profile, $item];
}

/**
 * @return array<string, mixed>
 */
function couponOrderPayload(MenuItem $item, string $code, int $quantity = 2): array
{
    return [
        'customer_name' => 'Maya Patel',
        'customer_phone' => '+1 555 0102',
        'order_type' => 'takeaway',
        'coupon_code' => $code,
        'items' => [['id' => $item->id, 'quantity' => $quantity]],
    ];
}

test('a percentage promo code discounts the order and records usage', function () {
    [$profile, $item] = couponRestaurant();

    $coupon = Coupon::factory()->for($profile)->create([
        'code' => 'WELCOME10',
        'type' => Coupon::TYPE_PERCENT,
        'value' => '10.00',
    ]);

    $this->post(route('restaurant.order', $profile->username), couponOrderPayload($item, 'welcome10'))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $order = Order::query()->sole();

    expect($order->subtotal_cents)->toBe(2000)
        ->and($order->discount_cents)->toBe(200)
        ->and($order->coupon_code)->toBe('WELCOME10')
        ->and($order->total_cents)->toBe(1800)
        ->and($coupon->refresh()->used_count)->toBe(1);
});

test('invalid, expired and exhausted promo codes are rejected', function () {
    [$profile, $item] = couponRestaurant();

    $this->post(route('restaurant.order', $profile->username), couponOrderPayload($item, 'NOPE'))
        ->assertSessionHasErrors('coupon_code');

    $expired = Coupon::factory()->for($profile)->create([
        'code' => 'OLD',
        'expires_at' => now()->subDay(),
    ]);

    $this->post(route('restaurant.order', $profile->username), couponOrderPayload($item, $expired->code))
        ->assertSessionHasErrors('coupon_code');

    $exhausted = Coupon::factory()->for($profile)->create([
        'code' => 'USEDUP',
        'max_uses' => 1,
        'used_count' => 1,
    ]);

    $this->post(route('restaurant.order', $profile->username), couponOrderPayload($item, $exhausted->code))
        ->assertSessionHasErrors('coupon_code');

    $minimum = Coupon::factory()->for($profile)->create([
        'code' => 'BIGORDER',
        'min_order_cents' => 5000,
    ]);

    $this->post(route('restaurant.order', $profile->username), couponOrderPayload($item, $minimum->code))
        ->assertSessionHasErrors('coupon_code');

    expect(Order::query()->count())->toBe(0);
});

test('owners can create, pause and delete promo codes', function () {
    $owner = User::factory()->create();
    $profile = Profile::factory()->for($owner)->restaurant()->create();

    $this->actingAs($owner)
        ->post(route('restaurant.promotions.store', $profile), [
            'code' => 'WELCOME10',
            'type' => 'percent',
            'value' => '10',
            'min_order' => '15.00',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $coupon = Coupon::query()->sole();

    expect($coupon->code)->toBe('WELCOME10')
        ->and($coupon->min_order_cents)->toBe(1500)
        ->and($coupon->is_active)->toBeTrue();

    $this->actingAs($owner)
        ->patch(route('restaurant.promotions.update', [$profile, $coupon]), [
            'is_active' => false,
        ])
        ->assertRedirect();

    expect($coupon->refresh()->is_active)->toBeFalse();

    $this->actingAs($owner)
        ->delete(route('restaurant.promotions.destroy', [$profile, $coupon]))
        ->assertRedirect();

    expect(Coupon::query()->count())->toBe(0);
});
