<?php

use App\Models\LoyaltyAccount;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Profile;
use Illuminate\Support\Facades\Mail;

function redeemableRestaurant(): Profile
{
    $profile = Profile::factory()->restaurant()->create([
        'is_published' => true,
        'menu_ordering_enabled' => true,
        'accepting_orders' => true,
    ]);

    $category = MenuCategory::factory()->for($profile)->create();
    MenuItem::factory()->for($category, 'category')->create([
        'price' => '12.50',
        'is_available' => true,
    ]);

    return $profile;
}

test('a guest can redeem loyalty points at checkout', function () {
    Mail::fake();

    $profile = redeemableRestaurant();
    $item = $profile->menuCategories()->sole()->items()->sole();

    $account = LoyaltyAccount::factory()->for($profile)->create([
        'phone' => '+15550102',
        'points' => 500,
        'lifetime_points' => 500,
    ]);

    $this->post(route('restaurant.order', $profile->username), [
        'customer_name' => 'Maya Patel',
        'customer_phone' => '+1 555 0102',
        'order_type' => 'takeaway',
        'redeem_points' => true,
        'items' => [['id' => $item->id, 'quantity' => 1]],
    ])->assertRedirect();

    $order = $profile->orders()->sole();

    expect($order->points_redeemed)->toBe(500)
        ->and($order->loyalty_discount_cents)->toBe(500)
        ->and($order->total_cents)->toBe(750)
        ->and($account->refresh()->points)->toBe(7);
});

test('points are not redeemed unless the guest asks', function () {
    Mail::fake();

    $profile = redeemableRestaurant();
    $item = $profile->menuCategories()->sole()->items()->sole();

    LoyaltyAccount::factory()->for($profile)->create([
        'phone' => '+15550102',
        'points' => 500,
    ]);

    $this->post(route('restaurant.order', $profile->username), [
        'customer_name' => 'Maya Patel',
        'customer_phone' => '+15550102',
        'order_type' => 'takeaway',
        'items' => [['id' => $item->id, 'quantity' => 1]],
    ])->assertRedirect();

    $order = $profile->orders()->sole();

    expect($order->points_redeemed)->toBe(0)
        ->and($order->loyalty_discount_cents)->toBe(0);
});

test('redemption is capped at the order subtotal', function () {
    Mail::fake();

    $profile = redeemableRestaurant();
    $item = $profile->menuCategories()->sole()->items()->sole();

    $account = LoyaltyAccount::factory()->for($profile)->create([
        'phone' => '+15550102',
        'points' => 5000,
    ]);

    $this->post(route('restaurant.order', $profile->username), [
        'customer_name' => 'Maya Patel',
        'customer_phone' => '+15550102',
        'order_type' => 'takeaway',
        'redeem_points' => true,
        'items' => [['id' => $item->id, 'quantity' => 1]],
    ])->assertRedirect();

    expect($profile->orders()->sole()->points_redeemed)->toBe(1250)
        ->and($account->refresh()->points)->toBe(3750);
});
