<?php

use App\Models\Profile;
use App\Models\Reservation;
use App\Models\User;

test('a full slot offers the waitlist and stores waitlisted requests', function () {
    $profile = Profile::factory()->restaurant()->create([
        'is_published' => true,
        'reservation_slot_capacity' => 1,
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
        ->assertSessionHasNoErrors();

    $this->post(route('restaurant.reservation', $profile->username), $booking)
        ->assertSessionHasErrors('reservation_time');

    $this->post(route('restaurant.reservation', $profile->username), [
        ...$booking,
        'join_waitlist' => true,
    ])->assertSessionHasNoErrors();

    $waitlisted = Reservation::query()->where('status', Reservation::STATUS_WAITLISTED)->sole();

    expect($waitlisted->name)->toBe('Ada')
        ->and(Reservation::query()->count())->toBe(2);
});

test('operators see the waitlist count and can promote a waitlisted booking', function () {
    $owner = User::factory()->create();
    $profile = Profile::factory()->restaurant()->for($owner)->create();
    $waitlisted = $profile->reservations()->create([
        'name' => 'Ada',
        'phone' => '+15550001111',
        'party_size' => 2,
        'reservation_date' => now()->addDay()->toDateString(),
        'reservation_time' => '19:00',
        'status' => Reservation::STATUS_WAITLISTED,
    ]);

    $this->actingAs($owner)
        ->get(route('restaurant.reservations', $profile))
        ->assertInertia(fn ($page) => $page
            ->where('stats.waitlist', 1)
            ->where('reservations.0.status', Reservation::STATUS_WAITLISTED));

    $this->actingAs($owner)
        ->patch(route('restaurant.reservations.update', [$profile, $waitlisted]), [
            'status' => Reservation::STATUS_CONFIRMED,
        ])
        ->assertRedirect();

    expect($waitlisted->refresh()->status)->toBe(Reservation::STATUS_CONFIRMED);
});

test('the reminder command messages confirmed bookings inside the lead time once', function () {
    $profile = Profile::factory()->restaurant()->create([
        'reservation_reminder_hours' => 24,
    ]);

    $upcoming = $profile->reservations()->create([
        'name' => 'Ada',
        'phone' => '+15550001111',
        'party_size' => 2,
        'reservation_date' => now()->addHours(2)->toDateString(),
        'reservation_time' => now()->addHours(2)->format('H:i'),
        'status' => Reservation::STATUS_CONFIRMED,
    ]);

    $farAway = $profile->reservations()->create([
        'name' => 'Grace',
        'phone' => '+15550002222',
        'party_size' => 2,
        'reservation_date' => now()->addDays(5)->toDateString(),
        'reservation_time' => '19:00',
        'status' => Reservation::STATUS_CONFIRMED,
    ]);

    $pending = $profile->reservations()->create([
        'name' => 'Linus',
        'phone' => '+15550003333',
        'party_size' => 2,
        'reservation_date' => now()->addHours(2)->toDateString(),
        'reservation_time' => now()->addHours(2)->format('H:i'),
        'status' => Reservation::STATUS_PENDING,
    ]);

    $this->artisan('reservations:send-reminders')->assertSuccessful();

    expect($upcoming->refresh()->reminder_sent_at)->not->toBeNull()
        ->and($farAway->refresh()->reminder_sent_at)->toBeNull()
        ->and($pending->refresh()->reminder_sent_at)->toBeNull();

    $sentAt = $upcoming->reminder_sent_at;

    $this->artisan('reservations:send-reminders')->assertSuccessful();

    expect($upcoming->refresh()->reminder_sent_at->equalTo($sentAt))->toBeTrue();
});

test('reminders stay off when the lead time is zero', function () {
    $profile = Profile::factory()->restaurant()->create([
        'reservation_reminder_hours' => 0,
    ]);

    $reservation = $profile->reservations()->create([
        'name' => 'Ada',
        'phone' => '+15550001111',
        'party_size' => 2,
        'reservation_date' => now()->addHours(2)->toDateString(),
        'reservation_time' => now()->addHours(2)->format('H:i'),
        'status' => Reservation::STATUS_CONFIRMED,
    ]);

    $this->artisan('reservations:send-reminders')->assertSuccessful();

    expect($reservation->refresh()->reminder_sent_at)->toBeNull();
});
