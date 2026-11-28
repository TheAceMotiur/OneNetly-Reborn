<?php

use App\Models\AuditLog;
use App\Models\Profile;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('team changes are recorded in the restaurant activity log', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $profile = Profile::factory()->restaurant()->for($owner)->create();

    $this->actingAs($owner)
        ->post(route('restaurant.team.store', $profile), [
            'email' => $member->email,
            'role' => 'manager',
        ])
        ->assertRedirect();

    $log = AuditLog::query()->where('action', 'team.added')->sole();

    expect($log->profile_id)->toBe($profile->id)
        ->and($log->user_id)->toBe($owner->id)
        ->and($log->description)->toContain($member->name);

    $this->actingAs($owner)
        ->get(route('restaurant.activity', $profile))
        ->assertInertia(fn (Assert $page) => $page
            ->component('activity/Index')
            ->has('logs', 1)
            ->where('logs.0.action', 'team.added')
            ->where('logs.0.user', $owner->name));

    $this->actingAs(User::factory()->create())
        ->get(route('restaurant.activity', $profile))
        ->assertForbidden();
});

test('menu and reservation changes land in the activity log', function () {
    $owner = User::factory()->create();
    $profile = Profile::factory()->restaurant()->for($owner)->create();

    $category = $profile->menuCategories()->create([
        'name' => 'Mains',
        'position' => 0,
        'is_active' => true,
    ]);

    $this->actingAs($owner)
        ->post(route('restaurant.menu.items.store', [$profile, $category]), [
            'name' => 'Tomato soup',
            'price' => '6.00',
        ])
        ->assertRedirect();

    expect(AuditLog::query()->where('action', 'menu.item_created')->exists())->toBeTrue();

    $reservation = $profile->reservations()->create([
        'name' => 'Ada',
        'phone' => '+15550001111',
        'party_size' => 2,
        'reservation_date' => now()->addDay()->toDateString(),
        'reservation_time' => '19:00',
        'status' => 'pending',
    ]);

    $this->actingAs($owner)
        ->patch(route('restaurant.reservations.update', [$profile, $reservation]), [
            'status' => 'confirmed',
        ])
        ->assertRedirect();

    expect(AuditLog::query()->where('action', 'reservation.status')->exists())->toBeTrue();
});

test('a user language preference is applied to the interface', function () {
    $user = User::factory()->create(['locale' => 'es']);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('locale', 'es'));
});

test('a supported language query parameter applies for the session', function () {
    $this->get(route('pricing.index', ['lang' => 'fr']))
        ->assertInertia(fn (Assert $page) => $page->where('locale', 'fr'));

    expect(session('locale'))->toBe('fr');
});

test('an unsupported language query parameter is ignored', function () {
    $this->get(route('pricing.index', ['lang' => 'zz']))
        ->assertInertia(fn (Assert $page) => $page->where('locale', 'en'));
});

test('the profile settings page saves the language preference', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'locale' => 'fr',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($user->refresh()->locale)->toBe('fr');
});
