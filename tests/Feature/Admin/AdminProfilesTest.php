<?php

use App\Models\Link;
use App\Models\Profile;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected away from the pages list', function () {
    $this->get(route('admin.profiles.index'))->assertRedirect(route('login'));
});

test('non admin users cannot manage bio pages', function () {
    $user = User::factory()->create();
    $profile = Profile::factory()->create();

    $this->actingAs($user)->get(route('admin.profiles.index'))->assertForbidden();
    $this->actingAs($user)
        ->patch(route('admin.profiles.update', $profile), ['is_published' => false])
        ->assertForbidden();
    $this->actingAs($user)
        ->delete(route('admin.profiles.destroy', $profile))
        ->assertForbidden();

    expect($profile->refresh())->not->toBeNull()
        ->and($profile->is_published)->toBeTrue();
});

test('admins can see every bio page', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    Profile::factory()->count(2)->create();

    $this->actingAs($admin)->get(route('admin.profiles.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/profiles/Index')
            ->has('profiles.data', 2)
            ->where('stats.total', 2));
});

test('admins can hide a published page', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $profile = Profile::factory()->create([
        'username' => 'moderated',
        'is_published' => true,
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.profiles.update', $profile), ['is_published' => false])
        ->assertRedirect();

    expect($profile->refresh()->is_published)->toBeFalse();

    $this->get(route('bio.show', 'moderated'))->assertNotFound();
});

test('admins can delete a page and its links', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $profile = Profile::factory()->create();
    Link::factory()->count(2)->for($profile)->create();

    $this->actingAs($admin)
        ->delete(route('admin.profiles.destroy', $profile))
        ->assertRedirect();

    expect(Profile::find($profile->id))->toBeNull()
        ->and(Link::where('profile_id', $profile->id)->count())->toBe(0);
});
