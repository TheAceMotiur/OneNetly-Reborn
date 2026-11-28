<?php

use App\Models\Order;
use App\Models\Profile;
use App\Models\Reservation;
use App\Models\User;

test('an owner with restaurant orders cannot delete their account', function () {
    $owner = User::factory()->create(['password' => 'password']);
    $profile = Profile::factory()->for($owner)->restaurant()->create();
    Order::factory()->for($profile)->create();

    $this->actingAs($owner)
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertSessionHasErrors('password');

    expect(User::find($owner->id))->not->toBeNull();
});

test('admins cannot delete a page that has orders', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $profile = Profile::factory()->restaurant()->create();
    Order::factory()->for($profile)->create();

    $this->actingAs($admin)
        ->delete(route('admin.profiles.destroy', $profile))
        ->assertRedirect();

    expect(Profile::find($profile->id))->not->toBeNull();
});

test('an owner with restaurant bookings cannot delete their account', function () {
    $owner = User::factory()->create(['password' => 'password']);
    $profile = Profile::factory()->for($owner)->restaurant()->create();
    Reservation::factory()->for($profile)->create();

    $this->actingAs($owner)
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertSessionHasErrors('password');

    expect(User::find($owner->id))->not->toBeNull();
});

test('accounts without orders can still be deleted', function () {
    $user = User::factory()->create(['password' => 'password']);
    Profile::factory()->for($user)->create();

    $this->actingAs($user)
        ->delete(route('profile.destroy'), ['password' => 'password']);

    expect(User::find($user->id))->toBeNull();
});
