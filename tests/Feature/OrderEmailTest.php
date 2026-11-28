<?php

use App\Mail\OrderConfirmation;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Profile;
use Illuminate\Support\Facades\Mail;

/**
 * @return array{0: Profile, 1: MenuItem}
 */
function orderEmailRestaurant(): array
{
    $profile = Profile::factory()->restaurant()->create([
        'is_published' => true,
        'menu_ordering_enabled' => true,
        'accepting_orders' => true,
    ]);

    $category = MenuCategory::factory()->for($profile)->create();

    $item = MenuItem::factory()->for($category, 'category')->create([
        'price' => '10.00',
        'is_available' => true,
    ]);

    return [$profile, $item];
}

test('guests who leave an email receive an order confirmation', function () {
    Mail::fake();

    [$profile, $item] = orderEmailRestaurant();

    $this->post(route('restaurant.order', $profile->username), [
        'customer_name' => 'Maya Patel',
        'customer_phone' => '+1 555 0102',
        'customer_email' => 'maya@example.com',
        'order_type' => 'takeaway',
        'items' => [['id' => $item->id, 'quantity' => 1]],
    ])->assertRedirect();

    Mail::assertSent(
        OrderConfirmation::class,
        fn (OrderConfirmation $mail): bool => $mail->hasTo('maya@example.com'),
    );
});

test('guests without an email receive no confirmation', function () {
    Mail::fake();

    [$profile, $item] = orderEmailRestaurant();

    $this->post(route('restaurant.order', $profile->username), [
        'customer_name' => 'Maya Patel',
        'customer_phone' => '+1 555 0102',
        'order_type' => 'takeaway',
        'items' => [['id' => $item->id, 'quantity' => 1]],
    ])->assertRedirect();

    Mail::assertNotSent(OrderConfirmation::class);
});
