<?php

use App\Models\Link;
use App\Models\LinkClick;
use App\Models\Plan;
use App\Models\Profile;
use App\Models\Purchase;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('a free user sees the analytics page without access', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('analytics'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('analytics/Index')
            ->where('canViewAnalytics', false));
});

test('a pro purchase unlocks analytics and reports clicks', function () {
    $user = User::factory()->create();
    $profile = Profile::factory()->for($user)->create();
    $link = Link::factory()->for($profile)->create();
    $plan = Plan::factory()->create([
        'plan_type' => 'pro',
        'has_analytics' => true,
        'has_custom_domain' => true,
    ]);

    Purchase::factory()->for($user)->create(['plan_id' => $plan->id]);
    LinkClick::factory()->for($link)->create([
        'clicked_at' => now()->subDay(),
        'referrer' => null,
    ]);
    LinkClick::factory()->for($link)->create([
        'clicked_at' => now()->subDay(),
        'referrer' => 'https://news.ycombinator.com/item?id=1',
    ]);

    $this->actingAs($user)->get(route('analytics'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('analytics/Index')
            ->where('canViewAnalytics', true)
            ->where('stats.clicksLast30', 2)
            ->has('daily', 30)
            ->has('topLinks', 1)
            ->where('topReferrers.0.host', 'news.ycombinator.com'));
});

test('admins always have analytics access', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)->get(route('analytics'))
        ->assertInertia(fn (Assert $page) => $page->where('canViewAnalytics', true));
});

test('the purchase history lists only the signed-in user purchases', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    Purchase::factory()->for($user)->create();
    Purchase::factory()->for($other)->create();

    $this->actingAs($user)->get(route('purchases.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('purchases/Index')
            ->has('purchases', 1));
});
