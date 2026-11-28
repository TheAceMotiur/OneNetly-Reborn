<?php

use App\Models\Link;
use App\Models\LinkClick;
use App\Models\Profile;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function bioBrowserAgent(): string
{
    return 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36';
}

test('a visitor click is recorded with its referrer and user agent', function () {
    $profile = Profile::factory()->create(['is_published' => true]);
    $link = Link::factory()->for($profile)->create(['url' => 'https://example.com/landing']);

    $this->withHeaders([
        'User-Agent' => bioBrowserAgent(),
        'Referer' => 'https://news.ycombinator.com/item?id=1',
    ])->get(route('links.redirect', $link))->assertRedirect('https://example.com/landing');

    $click = LinkClick::sole();

    expect($link->refresh()->clicks)->toBe(1)
        ->and($click->link_id)->toBe($link->id)
        ->and($click->referrer)->toBe('https://news.ycombinator.com/item?id=1')
        ->and($click->user_agent)->toBe(bioBrowserAgent());
});

test('automated clients are not counted as clicks', function (string $agent) {
    $profile = Profile::factory()->create(['is_published' => true]);
    $link = Link::factory()->for($profile)->create();

    $this->withHeaders(['User-Agent' => $agent])->get(route('links.redirect', $link));

    expect($link->refresh()->clicks)->toBe(0)
        ->and(LinkClick::count())->toBe(0);
})->with([
    'googlebot' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
    'slack preview' => 'Slackbot-LinkExpanding 1.0 (+https://api.slack.com/robots)',
    'curl' => 'curl/8.4.0',
]);

test('prefetch requests are not counted as clicks', function () {
    $profile = Profile::factory()->create(['is_published' => true]);
    $link = Link::factory()->for($profile)->create();

    $this->withHeaders([
        'User-Agent' => bioBrowserAgent(),
        'Sec-Purpose' => 'prefetch',
    ])->get(route('links.redirect', $link));

    expect($link->refresh()->clicks)->toBe(0)
        ->and(LinkClick::count())->toBe(0);
});

test('the owner opening their own link is not counted', function () {
    $user = User::factory()->create();
    $profile = Profile::factory()->for($user)->create(['is_published' => true]);
    $link = Link::factory()->for($profile)->create();

    $this->actingAs($user)
        ->withHeaders(['User-Agent' => bioBrowserAgent()])
        ->get(route('links.redirect', $link))
        ->assertRedirect();

    expect($link->refresh()->clicks)->toBe(0)
        ->and(LinkClick::count())->toBe(0);
});

test('a burst of clicks still redirects after the recording limit', function () {
    $profile = Profile::factory()->create(['is_published' => true]);
    $link = Link::factory()->for($profile)->create(['url' => 'https://example.com/burst']);

    for ($i = 0; $i < 65; $i++) {
        $this->withHeaders(['User-Agent' => bioBrowserAgent()])
            ->get(route('links.redirect', $link))
            ->assertRedirect('https://example.com/burst');
    }

    expect($link->refresh()->clicks)->toBe(60)
        ->and(LinkClick::count())->toBe(60);
});

test('the dashboard reports a seven day click series', function () {
    $user = User::factory()->create();
    $profile = Profile::factory()->for($user)->create();
    $link = Link::factory()->for($profile)->create();

    LinkClick::factory()->for($link)->create(['clicked_at' => now()->subDays(2)]);
    LinkClick::factory()->for($link)->create(['clicked_at' => now()->subDays(9)]);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('daily', 7)
            ->where('stats.clicksWeek', 1));
});

test('the links page reports weekly clicks and link status', function () {
    $user = User::factory()->create();
    $profile = Profile::factory()->for($user)->create();
    $link = Link::factory()->for($profile)->create();

    LinkClick::factory()->for($link)->create(['clicked_at' => now()->subDay()]);

    $this->actingAs($user)->get(route('links.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('links/Index')
            ->where('stats.clicksWeek', 1)
            ->where('links.0.clicks_week', 1)
            ->where('links.0.status', 'live')
            ->has('icons'));
});

test('scheduled links stay hidden until their start time', function () {
    $profile = Profile::factory()->create([
        'username' => 'scheduled-page',
        'is_published' => true,
    ]);
    $link = Link::factory()->for($profile)->create(['starts_at' => now()->addDay()]);

    $this->get(route('bio.show', $profile->username))
        ->assertInertia(fn (Assert $page) => $page->has('links', 0));

    $this->withHeaders(['User-Agent' => bioBrowserAgent()])
        ->get(route('links.redirect', $link))
        ->assertNotFound();

    expect($link->refresh()->clicks)->toBe(0);
});

test('expired links stop showing and redirecting', function () {
    $profile = Profile::factory()->create([
        'username' => 'expired-page',
        'is_published' => true,
    ]);
    $link = Link::factory()->for($profile)->create(['ends_at' => now()->subMinute()]);

    $this->get(route('bio.show', $profile->username))
        ->assertInertia(fn (Assert $page) => $page->has('links', 0));

    $this->get(route('links.redirect', $link))->assertNotFound();
});

test('links inside their schedule window are public', function () {
    $profile = Profile::factory()->create([
        'username' => 'window-page',
        'is_published' => true,
    ]);
    $link = Link::factory()->for($profile)->create([
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addHour(),
        'url' => 'https://example.com/scheduled',
    ]);

    $this->get(route('bio.show', $profile->username))
        ->assertInertia(fn (Assert $page) => $page
            ->has('links', 1)
            ->where('links.0.title', $link->title));

    $this->withHeaders(['User-Agent' => bioBrowserAgent()])
        ->get(route('links.redirect', $link))
        ->assertRedirect('https://example.com/scheduled');
});

test('link icons are detected from the destination when none is chosen', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('links.store'), [
        'title' => 'Repository',
        'url' => 'https://github.com/laravel/laravel',
    ])->assertRedirect(route('links.index'));

    expect($user->refresh()->profile->links()->sole()->resolvedIcon())->toBe('code');
});

test('a chosen icon is stored and unknown icons are rejected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('links.store'), [
        'title' => 'Playlist',
        'url' => 'https://example.com/playlist',
        'icon' => 'music',
    ])->assertRedirect(route('links.index'));

    expect($user->refresh()->profile->links()->sole()->resolvedIcon())->toBe('music');

    $this->actingAs($user)->post(route('links.store'), [
        'title' => 'Unsafe icon',
        'url' => 'https://example.com/other',
        'icon' => 'skull',
    ])->assertSessionHasErrors('icon');

    expect(Link::count())->toBe(1);
});

test('the page template is saved and exposed publicly', function () {
    $user = User::factory()->create();
    Profile::factory()->for($user)->create(['username' => 'templated', 'is_published' => true]);

    $this->actingAs($user)->patch(route('bio.update'), [
        'username' => 'templated',
        'theme' => 'indigo',
        'template' => 'aurora',
        'is_published' => true,
    ])->assertRedirect(route('bio.edit'));

    expect($user->refresh()->profile->template)->toBe('aurora');

    $this->get(route('bio.show', 'templated'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('profile.template', 'aurora')
            ->where('profile.share_url', route('bio.show', 'templated')));
});

test('an unknown page template is rejected', function () {
    $user = User::factory()->create();
    Profile::factory()->for($user)->create();

    $this->actingAs($user)->patch(route('bio.update'), [
        'username' => $user->profile->username,
        'theme' => 'indigo',
        'template' => 'neon',
        'is_published' => true,
    ])->assertSessionHasErrors('template');
});

test('the owner can render a qr code for their page', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('bio.qr'));

    $response->assertOk()->assertHeader('Content-Type', 'image/svg+xml');

    expect($response->getContent())->toContain('<svg');
});

test('guests cannot render a qr code', function () {
    $this->get(route('bio.qr'))->assertRedirect(route('login'));
});
