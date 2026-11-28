<?php

use App\Models\Link;
use App\Models\LinkClick;
use App\Models\Plan;
use App\Models\Profile;
use App\Models\ProfileView;
use App\Models\Purchase;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('a published bio page records a visitor view', function () {
    $profile = Profile::factory()->create();

    $this->get(route('bio.show', $profile->username))->assertOk();

    expect(ProfileView::where('profile_id', $profile->id)->count())->toBe(1);
});

test('the page owner does not record a view', function () {
    $user = User::factory()->create();
    $profile = Profile::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('bio.show', $profile->username))
        ->assertOk();

    expect(ProfileView::count())->toBe(0);
});

test('bots do not record a view', function () {
    $profile = Profile::factory()->create();

    $this->withHeader('User-Agent', 'Googlebot/2.1 (+http://www.google.com/bot.html)')
        ->get(route('bio.show', $profile->username))
        ->assertOk();

    expect(ProfileView::count())->toBe(0);
});

test('hidden pages are not tracked', function () {
    $profile = Profile::factory()->unpublished()->create();

    $this->get(route('bio.show', $profile->username))->assertNotFound();

    expect(ProfileView::count())->toBe(0);
});

test('analytics reports page views and click rate', function () {
    $user = User::factory()->create();
    $profile = Profile::factory()->for($user)->create();
    $link = Link::factory()->for($profile)->create();
    $plan = Plan::factory()->create(['has_analytics' => true]);
    Purchase::factory()->for($user)->create(['plan_id' => $plan->id]);

    ProfileView::factory()->for($profile)->count(4)->create([
        'viewed_at' => now()->subDay(),
    ]);
    LinkClick::factory()->for($link)->count(2)->create([
        'clicked_at' => now()->subDay(),
        'referrer' => null,
    ]);

    $this->actingAs($user)
        ->get(route('analytics'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('analytics/Index')
            ->where('stats.viewsLast30', 4)
            ->where('stats.viewsAll', 4)
            ->where('stats.clickRate', 50));
});

test('the dashboard reports page views and click rate', function () {
    $user = User::factory()->create();
    $profile = Profile::factory()->for($user)->create();
    $link = Link::factory()->for($profile)->create();

    ProfileView::factory()->for($profile)->count(3)->create([
        'viewed_at' => now()->subDay(),
    ]);
    LinkClick::factory()->for($link)->create([
        'clicked_at' => now()->subDay(),
        'referrer' => null,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('stats.views', 3)
            ->where('stats.viewsWeek', 3)
            ->where('stats.clickRate', 33));
});
