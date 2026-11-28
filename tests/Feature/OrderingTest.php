<?php

use App\Enums\OrderStatus;
use App\Mail\NewOrderReceived;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

function orderingProfile(array $attributes = []): Profile
{
    $profile = Profile::factory()->restaurant()->create([
        'is_published' => true,
        'menu_ordering_enabled' => true,
        'menu_currency' => 'USD',
        ...$attributes,
    ]);

    $category = MenuCategory::factory()->for($profile)->create(['name' => 'Mains']);

    MenuItem::factory()->for($category, 'category')->create([
        'name' => 'Truffle pasta',
        'price' => '12.50',
        'is_available' => true,
    ]);

    return $profile;
}

test('owners can enable ordering and choose a currency', function () {
    $user = User::factory()->create();
    $profile = Profile::factory()->for($user)->create();

    $this->actingAs($user)
        ->patch(route('restaurant.menu.settings', $profile), [
            'ordering_enabled' => true,
            'currency' => 'EUR',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($profile->refresh())
        ->menu_ordering_enabled->toBeTrue()
        ->menu_currency->toBe('EUR');
});

test('a visitor can place a dine in order with server calculated totals', function () {
    Mail::fake();

    $profile = orderingProfile();
    $item = $profile->menuCategories()->sole()->items()->sole();

    $this->post(route('restaurant.order', $profile->username), [
        'customer_name' => 'Maya Patel',
        'customer_phone' => '+1 555 0102',
        'customer_email' => 'maya@example.com',
        'order_type' => 'dine_in',
        'table_number' => '7',
        'notes' => 'No parmesan',
        'items' => [
            ['id' => $item->id, 'quantity' => 2],
        ],
    ])->assertRedirect(route('restaurant.show', [
        'username' => $profile->username,
        'table' => '7',
    ]))->assertInertiaFlash('order.reference');

    $order = Order::query()->sole();

    expect($order->customer_name)->toBe('Maya Patel')
        ->and($order->order_type)->toBe('dine_in')
        ->and($order->table_number)->toBe('7')
        ->and($order->subtotal_cents)->toBe(2500)
        ->and($order->total_cents)->toBe(2500)
        ->and($order->status)->toBe(OrderStatus::New)
        ->and($order->items)->toHaveCount(1)
        ->and($order->items->first()->name)->toBe('Truffle pasta')
        ->and($order->items->first()->line_total_cents)->toBe(2500);

    Mail::assertSent(NewOrderReceived::class, fn (NewOrderReceived $mail): bool => $mail->order->is($order));
});

test('orders reject unavailable or foreign menu items', function () {
    $profile = orderingProfile();
    $item = $profile->menuCategories()->sole()->items()->sole();

    $otherProfile = orderingProfile(['username' => 'other-restaurant']);
    $foreignItem = $otherProfile->menuCategories()->sole()->items()->sole();

    $this->post(route('restaurant.order', $profile->username), [
        'customer_name' => 'Maya Patel',
        'customer_phone' => '+1 555 0102',
        'order_type' => 'takeaway',
        'items' => [
            ['id' => $foreignItem->id, 'quantity' => 1],
        ],
    ])->assertSessionHasErrors('items');

    $item->update(['is_available' => false]);

    $this->post(route('restaurant.order', $profile->username), [
        'customer_name' => 'Maya Patel',
        'customer_phone' => '+1 555 0102',
        'order_type' => 'takeaway',
        'items' => [
            ['id' => $item->id, 'quantity' => 1],
        ],
    ])->assertSessionHasErrors('items');

    expect(Order::query()->count())->toBe(0);
});

test('ordering is unavailable when the restaurant has not enabled it', function () {
    $profile = orderingProfile(['menu_ordering_enabled' => false]);
    $item = $profile->menuCategories()->sole()->items()->sole();

    $this->post(route('restaurant.order', $profile->username), [
        'customer_name' => 'Maya Patel',
        'customer_phone' => '+1 555 0102',
        'order_type' => 'takeaway',
        'items' => [
            ['id' => $item->id, 'quantity' => 1],
        ],
    ])->assertNotFound();

    expect(Order::query()->count())->toBe(0);
});

test('owners can view and update their orders but cannot manage another owners orders', function () {
    $owner = User::factory()->create();
    $profile = orderingProfile(['user_id' => $owner->id]);
    $order = Order::factory()->for($profile)->create();

    $this->actingAs($owner)
        ->get(route('restaurant.orders', $profile))
        ->assertInertia(fn (Assert $page) => $page
            ->component('orders/Index')
            ->has('orders.data', 1)
            ->where('orders.data.0.reference', $order->reference));

    $this->actingAs($owner)
        ->patch(route('restaurant.orders.update', [$profile, $order]), ['status' => 'preparing'])
        ->assertRedirect();

    expect($order->refresh()->status)->toBe(OrderStatus::Preparing);

    $intruder = User::factory()->create();

    $this->actingAs($intruder)
        ->patch(route('restaurant.orders.update', [$profile, $order]), ['status' => 'cancelled'])
        ->assertForbidden();

    expect($order->refresh()->status)->toBe(OrderStatus::Preparing);
});

test('menu items can have an uploaded image', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $profile = Profile::factory()->for($user)->create();
    $category = MenuCategory::factory()->for($profile)->create();

    $this->actingAs($user)
        ->post(route('restaurant.menu.items.store', [$profile, $category]), [
            'name' => 'Tomato soup',
            'price' => '6.00',
            'image' => UploadedFile::fake()->image('soup.jpg'),
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $item = $category->items()->sole();

    expect($item->image_path)->not->toBeNull();
    Storage::disk('public')->assertExists($item->image_path);
});

test('the public restaurant page exposes orderable prices and the table number', function () {
    $profile = orderingProfile();
    $item = $profile->menuCategories()->sole()->items()->sole();

    $this->get(route('restaurant.show', [
        'username' => $profile->username,
        'table' => '12',
    ]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('restaurant/Show')
            ->where('restaurant.ordering_enabled', true)
            ->where('restaurant.currency', 'USD')
            ->where('restaurant.table', '12')
            ->where('menu.0.items.0.price_cents', 1250)
            ->where('menu.0.items.0.image_url', null)
            ->where('menu.0.items.0.name', $item->name));
});
