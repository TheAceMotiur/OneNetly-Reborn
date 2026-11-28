<?php

use App\Models\Order;
use App\Models\Profile;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the kitchen board lists active orders only', function () {
    $owner = User::factory()->create();
    $profile = Profile::factory()->for($owner)->restaurant()->create();

    $preparing = Order::factory()->for($profile)->create(['status' => 'preparing']);
    Order::factory()->for($profile)->create(['status' => 'completed']);

    $this->actingAs($owner)
        ->get(route('restaurant.kitchen', $profile))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('kitchen/Index')
            ->has('orders', 1)
            ->where('orders.0.id', $preparing->id)
            ->where('orders.0.status', 'preparing'));
});

test('staff can use the kitchen board but outsiders cannot', function () {
    $owner = User::factory()->create();
    $staff = User::factory()->create();
    $profile = Profile::factory()->for($owner)->restaurant()->create();
    $profile->members()->attach($staff, ['role' => 'staff']);

    $this->actingAs($staff)
        ->get(route('restaurant.kitchen', $profile))
        ->assertOk();

    $this->actingAs(User::factory()->create())
        ->get(route('restaurant.kitchen', $profile))
        ->assertForbidden();
});
