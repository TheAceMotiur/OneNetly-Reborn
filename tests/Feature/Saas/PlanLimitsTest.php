<?php

use App\Enums\PurchaseStatus;
use App\Models\Link;
use App\Models\Plan;
use App\Models\Profile;
use App\Models\Purchase;
use App\Models\User;

test('the free plan blocks links beyond its limit', function () {
    $user = User::factory()->create();
    $profile = Profile::factory()->for($user)->create();
    Link::factory()->count(5)->for($profile)->create();

    $this->actingAs($user)->post(route('links.store'), [
        'title' => 'One too many',
        'url' => 'https://example.com',
    ])->assertSessionHasErrors('limit');

    expect($profile->links()->count())->toBe(5);
});

test('a paid plan with unlimited links can add more', function () {
    $user = User::factory()->create();
    $profile = Profile::factory()->for($user)->create();
    $plan = Plan::factory()->create(['plan_type' => 'pro', 'max_links' => null]);

    Purchase::factory()->for($user)->create([
        'plan_id' => $plan->id,
        'status' => PurchaseStatus::Completed,
    ]);

    Link::factory()->count(5)->for($profile)->create();

    $this->actingAs($user)->post(route('links.store'), [
        'title' => 'Sixth link',
        'url' => 'https://example.com',
    ])->assertRedirect(route('links.index'));

    expect($profile->links()->count())->toBe(6);
});

test('the links page reports the current limit', function () {
    $user = User::factory()->create();
    $profile = Profile::factory()->for($user)->create(['is_published' => true]);
    Link::factory()->count(2)->for($profile)->create();

    $this->actingAs($user)->get(route('links.index'))
        ->assertInertia(fn ($page) => $page
            ->where('limits.used', 2)
            ->where('limits.limit', 5));
});

test('the restaurant plan limit blocks extra restaurants', function () {
    $user = User::factory()->create();
    $plan = Plan::factory()->create(['plan_type' => 'pro', 'max_restaurants' => 1]);

    Purchase::factory()->for($user)->create([
        'plan_id' => $plan->id,
        'status' => PurchaseStatus::Completed,
    ]);

    Profile::factory()->for($user)->restaurant()->create();

    $this->actingAs($user)->post(route('restaurant.store'), [
        'name' => 'Second Kitchen',
        'username' => 'second-kitchen',
    ])->assertSessionHasErrors('name');

    expect($user->restaurants()->count())->toBe(1);
});

test('admins can set the restaurant limit on a plan', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)->post(route('admin.plans.store'), [
        'name' => 'Multi',
        'plan_type' => 'premium',
        'price' => '39.99',
        'currency' => 'USD',
        'features' => [],
        'is_active' => true,
        'has_custom_domain' => true,
        'has_analytics' => true,
        'position' => 1,
        'max_links' => null,
        'max_restaurants' => 5,
    ])->assertRedirect();

    expect(Plan::query()->where('name', 'Multi')->sole()->max_restaurants)->toBe(5);
});

test('admins can assign a plan to any user', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $user = User::factory()->create();
    $plan = Plan::factory()->create(['name' => 'Studio', 'plan_type' => 'premium']);

    $this->actingAs($admin)->post(route('admin.users.plan', $user), [
        'plan_id' => $plan->id,
    ])->assertRedirect();

    $purchase = Purchase::sole();

    expect($purchase->user_id)->toBe($user->id)
        ->and($purchase->plan_id)->toBe($plan->id)
        ->and($purchase->status)->toBe(PurchaseStatus::Completed)
        ->and($purchase->payment_date)->not->toBeNull()
        ->and($user->fresh()->currentPlan()?->id)->toBe($plan->id);
});

test('non admins cannot assign plans', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $plan = Plan::factory()->create();

    $this->actingAs($user)->post(route('admin.users.plan', $other), [
        'plan_id' => $plan->id,
    ])->assertForbidden();

    expect(Purchase::count())->toBe(0);
});
