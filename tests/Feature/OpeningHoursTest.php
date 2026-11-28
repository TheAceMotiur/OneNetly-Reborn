<?php

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Support\Carbon;

afterEach(fn () => Carbon::setTestNow());

/**
 * @return array{0: Profile, 1: MenuItem}
 */
function hoursRestaurant(array $hours, array $attributes = []): array
{
    $profile = Profile::factory()->restaurant()->create([
        'is_published' => true,
        'menu_ordering_enabled' => true,
        'accepting_orders' => true,
        'opening_hours' => $hours,
        'timezone' => 'UTC',
        ...$attributes,
    ]);

    $category = MenuCategory::factory()->for($profile)->create();

    $item = MenuItem::factory()->for($category, 'category')->create([
        'price' => '10.00',
        'is_available' => true,
    ]);

    return [$profile, $item];
}

function weekOfHours(array $open): array
{
    $hours = array_fill(0, 7, ['closed' => true, 'open' => '09:00', 'close' => '17:00']);

    foreach ($open as $day => $range) {
        $hours[$day] = ['closed' => false, 'open' => $range[0], 'close' => $range[1]];
    }

    return $hours;
}

test('orders are accepted during opening hours and rejected outside them', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-25 12:00:00', 'UTC'));

    [$profile, $item] = hoursRestaurant(weekOfHours([3 => ['09:00', '17:00']]));

    $this->post(route('restaurant.order', $profile->username), [
        'customer_name' => 'Maya Patel',
        'customer_phone' => '+1 555 0102',
        'order_type' => 'takeaway',
        'items' => [['id' => $item->id, 'quantity' => 1]],
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect(Order::query()->count())->toBe(1);

    Carbon::setTestNow(Carbon::parse('2026-11-25 20:00:00', 'UTC'));

    $this->post(route('restaurant.order', $profile->username), [
        'customer_name' => 'Maya Patel',
        'customer_phone' => '+1 555 0102',
        'order_type' => 'takeaway',
        'items' => [['id' => $item->id, 'quantity' => 1]],
    ])->assertNotFound();

    expect(Order::query()->count())->toBe(1);
});

test('opening hours that pass midnight stay open after midnight', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-25 01:00:00', 'UTC'));

    $profile = Profile::factory()->restaurant()->create([
        'opening_hours' => weekOfHours([2 => ['22:00', '02:00']]),
        'timezone' => 'UTC',
    ]);

    expect($profile->isOpenNow())->toBeTrue()
        ->and($profile->todayHoursLabel())->toBe('Closed today');

    Carbon::setTestNow(Carbon::parse('2026-11-25 03:00:00', 'UTC'));

    expect($profile->isOpenNow())->toBeFalse();
});

test('restaurants without hours remain open', function () {
    $profile = Profile::factory()->restaurant()->create(['opening_hours' => null]);

    expect($profile->isOpenNow())->toBeTrue()
        ->and($profile->todayHoursLabel())->toBeNull();
});

test('owners can save opening hours and a timezone', function () {
    $user = User::factory()->create();
    $profile = Profile::factory()->for($user)->restaurant()->create();

    $hours = weekOfHours(array_fill_keys([1, 2, 3, 4, 5], ['09:00', '17:00']));

    $this->actingAs($user)
        ->patch(route('restaurant.update', $profile), [
            'name' => 'Joe\'s Pizza',
            'timezone' => 'Europe/London',
            'opening_hours' => $hours,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($profile->refresh())
        ->timezone->toBe('Europe/London')
        ->opening_hours->toBe($hours);
});
