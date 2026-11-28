<?php

use App\Models\LoyaltyAccount;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Profile;
use Illuminate\Support\Facades\Mail;

function loyaltyRestaurant(array $attributes = []): Profile
{
    $profile = Profile::factory()->restaurant()->create([
        'is_published' => true,
        'menu_ordering_enabled' => true,
        'accepting_orders' => true,
        ...$attributes,
    ]);

    $category = MenuCategory::factory()->for($profile)->create();
    MenuItem::factory()->for($category, 'category')->create([
        'price' => '12.50',
        'is_available' => true,
    ]);

    return $profile;
}

test('a phone order earns one point per currency unit', function () {
    Mail::fake();

    $profile = loyaltyRestaurant();
    $item = $profile->menuCategories()->sole()->items()->sole();

    $this->post(route('restaurant.order', $profile->username), [
        'customer_name' => 'Maya Patel',
        'customer_phone' => '+1 (555) 010-2',
        'order_type' => 'takeaway',
        'items' => [['id' => $item->id, 'quantity' => 2]],
    ])->assertRedirect();

    $account = LoyaltyAccount::query()->sole();

    expect($account->phone)->toBe('+15550102')
        ->and($account->points)->toBe(25)
        ->and($account->lifetime_points)->toBe(25)
        ->and($account->visits)->toBe(1)
        ->and($account->name)->toBe('Maya Patel');
});

test('repeat orders add to the same loyalty account', function () {
    Mail::fake();

    $profile = loyaltyRestaurant();
    $item = $profile->menuCategories()->sole()->items()->sole();

    foreach ([1, 2] as $quantity) {
        $this->post(route('restaurant.order', $profile->username), [
            'customer_name' => 'Maya Patel',
            'customer_phone' => '+15550102',
            'order_type' => 'takeaway',
            'items' => [['id' => $item->id, 'quantity' => $quantity]],
        ])->assertRedirect();
    }

    $account = LoyaltyAccount::query()->sole();

    expect($account->points)->toBe(37)
        ->and($account->visits)->toBe(2);
});

test('restaurants can turn loyalty points off', function () {
    Mail::fake();

    $profile = loyaltyRestaurant(['loyalty_enabled' => false]);
    $item = $profile->menuCategories()->sole()->items()->sole();

    $this->post(route('restaurant.order', $profile->username), [
        'customer_name' => 'Maya Patel',
        'customer_phone' => '+15550102',
        'order_type' => 'takeaway',
        'items' => [['id' => $item->id, 'quantity' => 1]],
    ])->assertRedirect();

    expect(LoyaltyAccount::query()->count())->toBe(0);
});

test('the tracking page shows the guest loyalty balance', function () {
    Mail::fake();

    $profile = loyaltyRestaurant();
    $item = $profile->menuCategories()->sole()->items()->sole();

    $this->post(route('restaurant.order', $profile->username), [
        'customer_name' => 'Maya Patel',
        'customer_phone' => '+15550102',
        'order_type' => 'takeaway',
        'items' => [['id' => $item->id, 'quantity' => 1]],
    ])->assertRedirect();

    $order = $profile->orders()->sole();

    $this->get(route('restaurant.track', [$profile->username, $order->reference]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('loyalty.points', 12)
            ->where('loyalty.visits', 1));
});
