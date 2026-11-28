<?php

use App\Models\Link;
use App\Models\LinkClick;
use App\Models\Plan;
use App\Models\Profile;
use App\Models\ProfileView;
use App\Models\Purchase;
use App\Models\User;

test('pro users can export their analytics as csv', function () {
    $user = User::factory()->create();
    $profile = Profile::factory()->for($user)->create();
    $link = Link::factory()->for($profile)->create([
        'title' => 'Newsletter',
        'url' => 'https://example.com/newsletter',
    ]);
    $plan = Plan::factory()->create(['has_analytics' => true]);
    Purchase::factory()->for($user)->create(['plan_id' => $plan->id]);

    LinkClick::factory()->for($link)->create([
        'clicked_at' => now()->subDay(),
        'referrer' => 'https://news.ycombinator.com/item?id=1',
    ]);
    ProfileView::factory()->for($profile)->create([
        'viewed_at' => now()->subDay(),
        'referrer' => null,
    ]);

    $response = $this->actingAs($user)->get(route('analytics.export'));

    $response->assertOk();
    $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    $response->assertDownload();

    $csv = $response->streamedContent();

    expect($csv)
        ->toContain('Type,Date,Link,Destination,Referrer')
        ->toContain('click')
        ->toContain('Newsletter')
        ->toContain('view')
        ->toContain('news.ycombinator.com');
});

test('free users cannot export analytics', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('analytics.export'))
        ->assertForbidden();
});
