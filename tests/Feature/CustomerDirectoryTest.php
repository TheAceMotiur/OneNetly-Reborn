<?php

use App\Models\LoyaltyAccount;
use App\Models\Profile;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('owners can browse and search the customer directory', function () {
    $owner = User::factory()->create();
    $profile = Profile::factory()->for($owner)->restaurant()->create();
    $other = Profile::factory()->restaurant()->create();

    LoyaltyAccount::factory()->for($profile)->create([
        'name' => 'Maya Patel',
        'phone' => '+15550102',
        'total_spent_cents' => 5000,
    ]);
    LoyaltyAccount::factory()->for($other)->create(['name' => 'Other Guest']);

    $this->actingAs($owner)
        ->get(route('restaurant.customers', $profile))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('customers/Index')
            ->has('customers.data', 1)
            ->where('customers.data.0.name', 'Maya Patel')
            ->where('stats.customers', 1)
            ->where('stats.spent', '50.00 USD'));

    $this->actingAs($owner)
        ->get(route('restaurant.customers', ['profile' => $profile, 'q' => 'Maya']))
        ->assertInertia(fn (Assert $page) => $page->has('customers.data', 1));
});

test('owners can save an internal customer note', function () {
    $owner = User::factory()->create();
    $profile = Profile::factory()->for($owner)->restaurant()->create();
    $customer = LoyaltyAccount::factory()->for($profile)->create();

    $this->actingAs($owner)
        ->patch(route('restaurant.customers.update', [$profile, $customer]), [
            'notes' => 'Prefers no onions.',
        ])
        ->assertRedirect();

    expect($customer->refresh()->notes)->toBe('Prefers no onions.');
});

test('the customer directory is private to the restaurant team', function () {
    $profile = Profile::factory()->restaurant()->create();
    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->get(route('restaurant.customers', $profile))
        ->assertForbidden();
});
