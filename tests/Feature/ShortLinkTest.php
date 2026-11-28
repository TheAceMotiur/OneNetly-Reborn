<?php

use App\Models\ShortLink;
use App\Models\ShortLinkClick;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function shortLinkVisitorAgent(): string
{
    return 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36';
}

test('a user can create a short link with a generated slug', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('short-links.store'), [
            'title' => 'Launch post',
            'destination' => 'https://example.com/launch',
            'utm_source' => 'newsletter',
            'utm_medium' => 'email',
        ])
        ->assertRedirect(route('short-links.index'));

    $link = ShortLink::sole();

    expect($link->user_id)->toBe($user->id)
        ->and($link->slug)->toHaveLength(7)
        ->and($link->utm_source)->toBe('newsletter')
        ->and($link->utm_medium)->toBe('email')
        ->and($link->is_active)->toBeTrue();
});

test('custom slugs are lowercased and must be unique', function () {
    $user = User::factory()->create();
    ShortLink::factory()->for($user)->create(['slug' => 'taken']);

    $this->actingAs($user)
        ->post(route('short-links.store'), [
            'destination' => 'https://example.com/one',
            'slug' => 'Taken',
        ])
        ->assertSessionHasErrors('slug');

    $this->actingAs($user)
        ->post(route('short-links.store'), [
            'destination' => 'https://example.com/two',
            'slug' => 'Launch-2026',
        ])
        ->assertRedirect();

    expect(ShortLink::query()->where('slug', 'launch-2026')->exists())->toBeTrue();
});

test('the dashboard lists the links with their weekly traffic', function () {
    $user = User::factory()->create();
    $link = ShortLink::factory()->for($user)->create(['clicks' => 5]);
    ShortLink::factory()->for($user)->paused()->create();

    ShortLinkClick::factory()->create([
        'short_link_id' => $link->id,
        'clicked_at' => now(),
        'visitor_hash' => 'visitor-a',
    ]);

    $this->actingAs($user)
        ->get(route('short-links.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('short-links/Index')
            ->has('links', 2)
            ->where('stats.links', 2)
            ->where('stats.live', 1)
            ->where('stats.clicks', 5)
            ->where('stats.clicks_week', 1)
            ->where('stats.visitors_week', 1));
});

test('a visitor is redirected and the click is recorded', function () {
    $link = ShortLink::factory()->create(['destination' => 'https://example.com/target']);

    $this->withHeaders([
        'User-Agent' => shortLinkVisitorAgent(),
        'Referer' => 'https://twitter.com/',
        'CF-IPCountry' => 'GB',
    ])
        ->get(route('short-links.redirect', $link->slug))
        ->assertRedirect('https://example.com/target');

    $click = ShortLinkClick::sole();

    expect($link->fresh()->clicks)->toBe(1)
        ->and($link->fresh()->last_clicked_at)->not->toBeNull()
        ->and($click->short_link_id)->toBe($link->id)
        ->and($click->referrer)->toBe('https://twitter.com/')
        ->and($click->device)->toBe('desktop')
        ->and($click->country)->toBe('GB')
        ->and($click->ip_hash)->not->toBeNull();
});

test('the owners own clicks are not counted', function () {
    $link = ShortLink::factory()->create();

    $this->actingAs($link->user)
        ->withHeaders(['User-Agent' => shortLinkVisitorAgent()])
        ->get(route('short-links.redirect', $link->slug))
        ->assertRedirect($link->destination);

    expect(ShortLinkClick::count())->toBe(0)
        ->and($link->fresh()->clicks)->toBe(0);
});

test('bot and preview requests are not counted', function () {
    $link = ShortLink::factory()->create();

    $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'])
        ->get(route('short-links.redirect', $link->slug))
        ->assertRedirect();

    $this->withHeaders([
        'User-Agent' => shortLinkVisitorAgent(),
        'Purpose' => 'prefetch',
    ])
        ->get(route('short-links.redirect', $link->slug))
        ->assertRedirect();

    expect(ShortLinkClick::count())->toBe(0);
});

test('paused links are unavailable while expired and exhausted links are gone', function () {
    $paused = ShortLink::factory()->paused()->create();
    $expired = ShortLink::factory()->expired()->create();
    $exhausted = ShortLink::factory()->create(['max_clicks' => 2, 'clicks' => 2]);

    $headers = ['User-Agent' => shortLinkVisitorAgent()];

    $this->withHeaders($headers)
        ->get(route('short-links.redirect', $paused->slug))
        ->assertNotFound();

    $this->withHeaders($headers)
        ->get(route('short-links.redirect', $expired->slug))
        ->assertStatus(410);

    $this->withHeaders($headers)
        ->get(route('short-links.redirect', $exhausted->slug))
        ->assertStatus(410);
});

test('a password protected link asks for the password before counting the click', function () {
    $link = ShortLink::factory()->passwordProtected('open-sesame')->create([
        'destination' => 'https://example.com/private',
    ]);

    $this->withHeaders(['User-Agent' => shortLinkVisitorAgent()])
        ->get(route('short-links.redirect', $link->slug))
        ->assertInertia(fn (Assert $page) => $page
            ->component('short-links/Password')
            ->where('slug', $link->slug));

    expect(ShortLinkClick::count())->toBe(0);

    $this->withHeaders(['User-Agent' => shortLinkVisitorAgent()])
        ->post(route('short-links.unlock', $link->slug), ['password' => 'wrong'])
        ->assertSessionHasErrors('password');

    $this->withHeaders(['User-Agent' => shortLinkVisitorAgent()])
        ->post(route('short-links.unlock', $link->slug), ['password' => 'open-sesame'])
        ->assertRedirect(route('short-links.redirect', $link->slug));

    $this->withHeaders(['User-Agent' => shortLinkVisitorAgent()])
        ->get(route('short-links.redirect', $link->slug))
        ->assertRedirect('https://example.com/private');

    expect(ShortLinkClick::sole()->short_link_id)->toBe($link->id);
});

test('campaign parameters are appended without losing the existing query', function () {
    $link = ShortLink::factory()->create([
        'destination' => 'https://example.com/shop?ref=poster#deals',
        'utm_source' => 'newsletter',
        'utm_medium' => 'email',
    ]);

    $this->withHeaders(['User-Agent' => shortLinkVisitorAgent()])
        ->get(route('short-links.redirect', $link->slug))
        ->assertRedirect('https://example.com/shop?ref=poster&utm_source=newsletter&utm_medium=email#deals');
});

test('phones and tablets receive the mobile destination', function () {
    $link = ShortLink::factory()->create([
        'destination' => 'https://example.com/desktop',
        'mobile_destination' => 'https://example.com/mobile',
    ]);

    $this->withHeaders([
        'User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1',
    ])
        ->get(route('short-links.redirect', $link->slug))
        ->assertRedirect('https://example.com/mobile');
});

test('the analytics page summarizes clicks for the owner only', function () {
    $link = ShortLink::factory()->create(['clicks' => 3]);

    ShortLinkClick::factory()->count(3)->create([
        'short_link_id' => $link->id,
        'clicked_at' => now(),
        'visitor_hash' => 'same-visitor',
        'device' => 'mobile',
        'country' => 'US',
        'referrer' => 'https://www.instagram.com/p/abc',
    ]);

    $this->actingAs($link->user)
        ->get(route('short-links.analytics', $link))
        ->assertInertia(fn (Assert $page) => $page
            ->component('short-links/Analytics')
            ->where('totals.clicks', 3)
            ->where('totals.visitors', 1)
            ->where('totals.today', 3)
            ->where('devices.0.device', 'mobile')
            ->where('countries.0.country', 'US')
            ->where('referrers.0.source', 'instagram.com'));

    $this->actingAs(User::factory()->create())
        ->get(route('short-links.analytics', $link))
        ->assertForbidden();
});

test('the owner can download a QR code for a short link', function () {
    $link = ShortLink::factory()->create();

    $this->actingAs($link->user)
        ->get(route('short-links.qr', $link))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/svg+xml');

    $this->actingAs(User::factory()->create())
        ->get(route('short-links.qr', $link))
        ->assertForbidden();
});

test('a user can update and delete their own short link only', function () {
    $link = ShortLink::factory()->create(['destination' => 'https://example.com/old']);

    $this->actingAs($link->user)
        ->patch(route('short-links.update', $link), [
            'title' => 'Updated title',
            'destination' => 'https://example.com/new',
            'is_active' => false,
        ])
        ->assertRedirect();

    $link->refresh();

    expect($link->title)->toBe('Updated title')
        ->and($link->destination)->toBe('https://example.com/new')
        ->and($link->is_active)->toBeFalse();

    $this->actingAs(User::factory()->create())
        ->patch(route('short-links.update', $link), [
            'destination' => 'https://example.com/hijack',
        ])
        ->assertForbidden();

    $this->actingAs($link->user)
        ->delete(route('short-links.destroy', $link))
        ->assertRedirect(route('short-links.index'));

    expect(ShortLink::count())->toBe(0);
});
