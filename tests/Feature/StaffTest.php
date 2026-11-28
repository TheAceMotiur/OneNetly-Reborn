<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Profile;
use App\Models\User;

function staffedRestaurant(User $owner): Profile
{
    return Profile::factory()->for($owner)->restaurant()->create([
        'is_published' => true,
        'menu_ordering_enabled' => true,
        'accepting_orders' => true,
    ]);
}

test('managers can manage the restaurant and staff can operate orders', function () {
    $owner = User::factory()->create();
    $manager = User::factory()->create();
    $staff = User::factory()->create();
    $profile = staffedRestaurant($owner);

    $profile->members()->attach($manager, ['role' => 'manager']);
    $profile->members()->attach($staff, ['role' => 'staff']);

    $order = Order::factory()->for($profile)->create();

    $this->actingAs($manager)
        ->get(route('restaurant.menu', $profile))
        ->assertOk();

    $this->actingAs($manager)
        ->patch(route('restaurant.update', $profile), ['name' => 'Managed Kitchen'])
        ->assertRedirect();

    $this->actingAs($staff)
        ->get(route('restaurant.orders', $profile))
        ->assertOk();

    $this->actingAs($staff)
        ->patch(route('restaurant.orders.update', [$profile, $order]), ['status' => 'preparing'])
        ->assertRedirect();

    expect($order->refresh()->status)->toBe(OrderStatus::Preparing);

    $this->actingAs($staff)
        ->get(route('restaurant.menu', $profile))
        ->assertForbidden();

    $this->actingAs($staff)
        ->get(route('restaurant.reports', $profile))
        ->assertForbidden();
});

test('shared restaurants appear in the accessible restaurant list', function () {
    $owner = User::factory()->create();
    $manager = User::factory()->create();
    $profile = staffedRestaurant($owner);

    $profile->members()->attach($manager, ['role' => 'manager']);

    expect($manager->accessibleRestaurants()->pluck('id')->all())->toBe([$profile->id]);
});

test('owners can add and remove team members', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $profile = staffedRestaurant($owner);

    $this->actingAs($owner)
        ->post(route('restaurant.team.store', $profile), [
            'email' => $member->email,
            'role' => 'staff',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($profile->members()->whereKey($member->id)->exists())->toBeTrue();

    $this->actingAs($owner)
        ->delete(route('restaurant.team.destroy', [$profile, $member]))
        ->assertRedirect();

    expect($profile->members()->whereKey($member->id)->exists())->toBeFalse();
});

test('managers cannot manage the team', function () {
    $owner = User::factory()->create();
    $manager = User::factory()->create();
    $profile = staffedRestaurant($owner);

    $profile->members()->attach($manager, ['role' => 'manager']);

    $this->actingAs($manager)
        ->post(route('restaurant.team.store', $profile), [
            'email' => User::factory()->create()->email,
            'role' => 'staff',
        ])
        ->assertForbidden();
});
