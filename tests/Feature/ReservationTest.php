<?php

use App\Models\Profile;
use App\Models\Reservation;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function reservableRestaurant(array $attributes = []): Profile
{
    return Profile::factory()->restaurant()->create([
        'is_published' => true,
        ...$attributes,
    ]);
}

test('guests can request a table from the public menu', function () {
    $profile = reservableRestaurant();

    $this->post(route('restaurant.reservation', $profile->username), [
        'name' => 'Maya Patel',
        'phone' => '+1 555 0102',
        'email' => 'maya@example.com',
        'party_size' => 4,
        'reservation_date' => now()->addDay()->toDateString(),
        'reservation_time' => '19:30',
        'notes' => 'Birthday, high chair please',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $reservation = Reservation::query()->sole();

    expect($reservation->name)->toBe('Maya Patel')
        ->and($reservation->party_size)->toBe(4)
        ->and($reservation->reservation_time)->toBe('19:30')
        ->and($reservation->status)->toBe(Reservation::STATUS_PENDING);
});

test('reservation requests validate the party, date and contact details', function () {
    $profile = reservableRestaurant();

    $this->post(route('restaurant.reservation', $profile->username), [
        'name' => 'Maya Patel',
        'phone' => '',
        'party_size' => 40,
        'reservation_date' => now()->subDay()->toDateString(),
        'reservation_time' => '25:00',
    ])->assertSessionHasErrors([
        'phone',
        'party_size',
        'reservation_date',
        'reservation_time',
    ]);

    expect(Reservation::query()->count())->toBe(0);
});

test('reservations are unavailable for unpublished restaurants', function () {
    $profile = reservableRestaurant(['is_published' => false]);

    $this->post(route('restaurant.reservation', $profile->username), [
        'name' => 'Maya Patel',
        'phone' => '+1 555 0102',
        'party_size' => 2,
        'reservation_date' => now()->addDay()->toDateString(),
        'reservation_time' => '19:00',
    ])->assertNotFound();
});

test('owners can confirm reservations and other users cannot', function () {
    $owner = User::factory()->create();
    $profile = reservableRestaurant(['user_id' => $owner->id]);
    $reservation = Reservation::factory()->for($profile)->create();

    $this->actingAs($owner)
        ->get(route('restaurant.reservations', $profile))
        ->assertInertia(fn (Assert $page) => $page
            ->component('reservations/Index')
            ->has('reservations', 1)
            ->where('reservations.0.id', $reservation->id));

    $this->actingAs($owner)
        ->patch(route('restaurant.reservations.update', [$profile, $reservation]), [
            'status' => 'confirmed',
        ])
        ->assertRedirect();

    expect($reservation->refresh()->status)->toBe(Reservation::STATUS_CONFIRMED);

    $intruder = User::factory()->create();

    $this->actingAs($intruder)
        ->patch(route('restaurant.reservations.update', [$profile, $reservation]), [
            'status' => 'cancelled',
        ])
        ->assertForbidden();

    expect($reservation->refresh()->status)->toBe(Reservation::STATUS_CONFIRMED);
});
