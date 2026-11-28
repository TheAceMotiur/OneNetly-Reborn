<?php

use App\Models\MenuItem;
use App\Models\Profile;
use App\Models\Reservation;
use Illuminate\Support\Carbon;

afterEach(function () {
    Carbon::setTestNow();
});

test('a menu item charges its sale price only inside the sale window', function () {
    Carbon::setTestNow('2026-11-25 12:00:00');

    $item = MenuItem::factory()->create([
        'price' => '10.00',
        'sale_price' => '6.50',
        'sale_starts_at' => '11:00',
        'sale_ends_at' => '15:00',
    ]);

    expect($item->isOnSale())->toBeTrue()
        ->and($item->priceInCents())->toBe(650);

    Carbon::setTestNow('2026-11-25 17:00:00');

    expect($item->isOnSale())->toBeFalse()
        ->and($item->priceInCents())->toBe(1000);
});

test('overnight sale windows wrap past midnight', function () {
    Carbon::setTestNow('2026-11-25 01:00:00');

    $item = MenuItem::factory()->create([
        'price' => '8.00',
        'sale_price' => '4.00',
        'sale_starts_at' => '22:00',
        'sale_ends_at' => '02:00',
    ]);

    expect($item->isOnSale())->toBeTrue()
        ->and($item->priceInCents())->toBe(400);
});

test('reservation rules enforce party size and slot capacity', function () {
    $profile = Profile::factory()->restaurant()->create([
        'is_published' => true,
        'reservation_slot_capacity' => 1,
        'reservation_max_party' => 4,
        'reservation_auto_confirm' => true,
    ]);

    $booking = [
        'name' => 'Ada',
        'phone' => '+15550001111',
        'party_size' => 2,
        'reservation_date' => now()->addDay()->toDateString(),
        'reservation_time' => '19:00',
    ];

    $this->post(route('restaurant.reservation', $profile->username), $booking)
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(Reservation::query()->sole()->status)->toBe(Reservation::STATUS_CONFIRMED);

    $this->post(route('restaurant.reservation', $profile->username), $booking)
        ->assertSessionHasErrors('reservation_time');

    $this->post(route('restaurant.reservation', $profile->username), [
        ...$booking,
        'reservation_time' => '20:00',
        'party_size' => 5,
    ])->assertSessionHasErrors('party_size');

    expect(Reservation::query()->count())->toBe(1);
});
