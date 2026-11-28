<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Profile;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('owners can see sales reports for a date range', function () {
    $owner = User::factory()->create();
    $profile = Profile::factory()->for($owner)->restaurant()->create();

    Order::factory()->for($profile)->create([
        'total_cents' => 5000,
        'subtotal_cents' => 5000,
        'created_at' => now(),
    ]);
    Order::factory()->for($profile)->create([
        'total_cents' => 7000,
        'subtotal_cents' => 7000,
        'status' => OrderStatus::Cancelled,
        'created_at' => now(),
    ]);

    $this->actingAs($owner)
        ->get(route('restaurant.reports', [
            'profile' => $profile,
            'from' => now()->subDays(7)->toDateString(),
            'to' => now()->toDateString(),
        ]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/Index')
            ->where('stats.orders', 1)
            ->where('stats.revenue', '50.00 USD')
            ->where('stats.cancelled', 1)
            ->has('daily', 1));
});

test('users cannot see another restaurant reports', function () {
    $profile = Profile::factory()->restaurant()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('restaurant.reports', $profile))
        ->assertForbidden();
});

test('owners can export sales as CSV', function () {
    $owner = User::factory()->create();
    $profile = Profile::factory()->for($owner)->restaurant()->create();
    $order = Order::factory()->for($profile)->create([
        'reference' => 'ORD-EXPORT',
        'total_cents' => 4321,
        'subtotal_cents' => 4000,
        'created_at' => now(),
    ]);

    $response = $this->actingAs($owner)
        ->get(route('restaurant.reports.export', [
            'profile' => $profile,
            'from' => now()->subDay()->toDateString(),
            'to' => now()->toDateString(),
        ]))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');

    $csv = $response->streamedContent();

    expect($csv)->toContain('Reference')
        ->and($csv)->toContain($order->reference)
        ->and($csv)->toContain('43.21');
});
