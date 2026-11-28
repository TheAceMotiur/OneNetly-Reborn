<?php

use App\Models\Order;
use App\Models\Profile;
use App\Models\Reservation;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Services\PosWebhookDispatcher;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

test('the sitemap lists published pages and points robots at it', function () {
    Profile::factory()->create([
        'username' => 'published-page',
        'is_published' => true,
    ]);
    Profile::factory()->restaurant()->create([
        'username' => 'published-kitchen',
        'is_published' => true,
    ]);
    Profile::factory()->create([
        'username' => 'hidden-page',
        'is_published' => false,
    ]);

    $this->get(route('sitemap'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml')
        ->assertSee('published-page')
        ->assertSee('published-kitchen')
        ->assertDontSee('hidden-page');

    $this->get(route('robots'))
        ->assertOk()
        ->assertSee('Sitemap: '.route('sitemap'));
});

test('the brand overview compares accessible locations', function () {
    $owner = User::factory()->create();
    $first = Profile::factory()->for($owner)->restaurant()->create(['username' => 'one']);
    $second = Profile::factory()->for($owner)->restaurant()->create(['username' => 'two']);

    Order::factory()->for($first)->create(['total_cents' => 2500]);
    Order::factory()->for($second)->create(['total_cents' => 1500]);

    $this->actingAs($owner)
        ->get(route('restaurant.brand'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('brand/Index')
            ->has('locations', 2)
            ->where('totals.orders', 2)
            ->where('totals.revenue_cents', 4000));
});

test('reservation changes emit signed webhooks', function () {
    Http::fake(['https://pos.example/hooks' => Http::response(['ok' => true])]);

    $profile = Profile::factory()->restaurant()->create([
        'pos_webhook_url' => 'https://pos.example/hooks',
        'pos_webhook_secret' => 'secret',
    ]);
    $reservation = Reservation::factory()->for($profile)->create();

    app(PosWebhookDispatcher::class)->reservationUpdated($reservation, 'pending');

    expect(WebhookDelivery::query()->sole())
        ->event->toBe('reservation.updated')
        ->success->toBeTrue();

    Http::assertSent(fn ($request): bool => $request->hasHeader('X-OneNetly-Event', 'reservation.updated')
        && $request['reservation']['id'] === $reservation->id);
});
