<?php

use App\Models\User;

test('guests are redirected to the login page', function () {
    $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
});

test('non admin users cannot access the admin panel', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('admin.dashboard'))->assertForbidden();
    $this->actingAs($user)->get(route('admin.users.index'))->assertForbidden();
});

test('admin users can access the admin panel', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
    $this->actingAs($admin)->get(route('admin.users.index'))->assertOk();
});

test('admins can grant and revoke admin access', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $user = User::factory()->create();

    $this->actingAs($admin)
        ->patch(route('admin.users.update', $user), ['is_admin' => true])
        ->assertRedirect();

    expect($user->refresh()->is_admin)->toBeTrue();

    $this->actingAs($admin)
        ->patch(route('admin.users.update', $user), ['is_admin' => false])
        ->assertRedirect();

    expect($user->refresh()->is_admin)->toBeFalse();
});

test('non admin users cannot change admin access', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('admin.users.update', $other), ['is_admin' => true])
        ->assertForbidden();

    expect($other->refresh()->is_admin)->toBeFalse();
});

test('the last administrator cannot be demoted', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)
        ->patch(route('admin.users.update', $admin), ['is_admin' => false])
        ->assertForbidden();

    expect($admin->refresh()->is_admin)->toBeTrue();
});
