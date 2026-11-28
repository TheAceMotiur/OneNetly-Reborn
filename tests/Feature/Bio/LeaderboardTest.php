<?php

use App\Models\Profile;
use App\Models\ProfileView;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

test('the leaderboard ranks published pages by the last seven days of views', function () {
    $leader = Profile::factory()->create(['display_name' => 'Ada Leader']);
    $runnerUp = Profile::factory()->create(['display_name' => 'Sam Runner']);
    $quiet = Profile::factory()->create();
    Profile::factory()->unpublished()->create();

    ProfileView::factory()->count(5)->for($leader, 'profile')->create(['viewed_at' => now()->subDay()]);
    ProfileView::factory()->count(2)->for($runnerUp, 'profile')->create(['viewed_at' => now()->subHours(2)]);
    ProfileView::factory()->count(9)->for($quiet, 'profile')->create(['viewed_at' => now()->subDays(10)]);

    $this->get(route('leaderboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('leaderboard/Index')
            ->has('entries', 2)
            ->where('entries.0.rank', 1)
            ->where('entries.0.username', $leader->username)
            ->where('entries.0.display_name', 'Ada Leader')
            ->where('entries.0.views', 5)
            ->where('entries.1.rank', 2)
            ->where('entries.1.username', $runnerUp->username)
            ->where('entries.1.views', 2)
            ->where('totals.views', 7)
            ->where('totals.pages', 2));
});

test('the leaderboard compares each page with the week before', function () {
    $growing = Profile::factory()->create();
    $fresh = Profile::factory()->create();

    ProfileView::factory()->count(3)->for($growing, 'profile')->create(['viewed_at' => now()->subDay()]);
    ProfileView::factory()->count(2)->for($growing, 'profile')->create(['viewed_at' => now()->subDays(9)]);
    ProfileView::factory()->for($fresh, 'profile')->create(['viewed_at' => now()->subHours(5)]);

    $this->get(route('leaderboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('entries.0.username', $growing->username)
            ->where('entries.0.change', 50)
            ->where('entries.1.username', $fresh->username)
            ->where('entries.1.change', null));
});

test('password protected and untouched pages stay off the leaderboard', function () {
    $protected = Profile::factory()->create(['access_password' => Hash::make('secret')]);
    $untouched = Profile::factory()->create();

    ProfileView::factory()->count(4)->for($protected, 'profile')->create(['viewed_at' => now()->subDay()]);
    ProfileView::factory()->count(3)->for($untouched, 'profile')->create(['viewed_at' => now()->subDays(8)]);

    $this->get(route('leaderboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('entries', 0)
            ->where('totals.views', 0)
            ->where('totals.pages', 0));
});
