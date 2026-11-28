<?php

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuOption;
use App\Models\MenuOptionGroup;
use App\Models\Order;
use App\Models\Profile;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @return array{0: User, 1: Profile, 2: MenuItem}
 */
function optionRestaurant(): array
{
    $user = User::factory()->create();

    $restaurant = Profile::factory()->for($user)->restaurant()->create([
        'is_published' => true,
        'menu_ordering_enabled' => true,
        'accepting_orders' => true,
        'menu_currency' => 'USD',
    ]);

    $category = MenuCategory::factory()->for($restaurant)->create([
        'name' => 'Mains',
        'is_active' => true,
    ]);

    $item = MenuItem::factory()->for($category, 'category')->create([
        'name' => 'Burger',
        'price' => '10.00',
        'is_available' => true,
    ]);

    return [$user, $restaurant, $item];
}

test('option choices are priced into the order total', function () {
    [, $restaurant, $item] = optionRestaurant();

    $size = MenuOptionGroup::factory()->for($item, 'item')->required()->create([
        'name' => 'Size',
    ]);
    $large = MenuOption::factory()->for($size, 'group')->create([
        'name' => 'Large',
        'price' => '2.00',
    ]);

    $extras = MenuOptionGroup::factory()->for($item, 'item')->multiple()->create([
        'name' => 'Extras',
    ]);
    $cheese = MenuOption::factory()->for($extras, 'group')->create([
        'name' => 'Cheese',
        'price' => '1.50',
    ]);

    $this->post(route('restaurant.order', $restaurant->username), [
        'customer_name' => 'Maya',
        'customer_phone' => '+1 555 0102',
        'order_type' => 'takeaway',
        'items' => [[
            'id' => $item->id,
            'quantity' => 2,
            'options' => [$large->id, $cheese->id],
        ]],
    ])->assertRedirect()->assertSessionHasNoErrors();

    $order = Order::query()->sole();

    expect($order->total_cents)->toBe(2700)
        ->and($order->items()->sole()->unit_price_cents)->toBe(1350)
        ->and($order->items()->sole()->options)->toBe([
            ['group' => 'Size', 'name' => 'Large', 'price_cents' => 200],
            ['group' => 'Extras', 'name' => 'Cheese', 'price_cents' => 150],
        ]);
});

test('required option groups must be chosen', function () {
    [, $restaurant, $item] = optionRestaurant();

    $group = MenuOptionGroup::factory()->for($item, 'item')->required()->create([
        'name' => 'Size',
    ]);

    MenuOption::factory()->for($group, 'group')->create(['name' => 'Small']);
    MenuOption::factory()->for($group, 'group')->create(['name' => 'Large']);

    $this->post(route('restaurant.order', $restaurant->username), [
        'customer_name' => 'Maya',
        'customer_phone' => '+1 555 0102',
        'order_type' => 'takeaway',
        'items' => [['id' => $item->id, 'quantity' => 1, 'options' => []]],
    ])->assertSessionHasErrors('items');

    expect(Order::query()->count())->toBe(0);
});

test('options from another item are rejected', function () {
    [, $restaurant, $item] = optionRestaurant();

    $other = MenuItem::factory()->for($item->category, 'category')->create();
    $foreignGroup = MenuOptionGroup::factory()->for($other, 'item')->create();
    $foreignOption = MenuOption::factory()->for($foreignGroup, 'group')->create();

    $this->post(route('restaurant.order', $restaurant->username), [
        'customer_name' => 'Maya',
        'customer_phone' => '+1 555 0102',
        'order_type' => 'takeaway',
        'items' => [[
            'id' => $item->id,
            'quantity' => 1,
            'options' => [$foreignOption->id],
        ]],
    ])->assertSessionHasErrors('items');

    expect(Order::query()->count())->toBe(0);
});

test('owners can manage option groups and choices on an item', function () {
    [$user, $restaurant, $item] = optionRestaurant();

    $this->actingAs($user)
        ->post(route('restaurant.menu.option-groups.store', [$restaurant, $item]), [
            'name' => 'Size',
            'is_required' => true,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $group = MenuOptionGroup::query()->sole();

    $this->actingAs($user)
        ->post(route('restaurant.menu.options.store', [$restaurant, $item, $group]), [
            'name' => 'Large',
            'price' => '1.25',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->actingAs($user)
        ->get(route('restaurant.menu', $restaurant))
        ->assertInertia(fn (Assert $page) => $page
            ->where('categories.0.items.0.option_groups.0.name', 'Size')
            ->where('categories.0.items.0.option_groups.0.is_required', true)
            ->where('categories.0.items.0.option_groups.0.options.0.name', 'Large')
            ->where('categories.0.items.0.option_groups.0.options.0.price_cents', 125));

    $this->actingAs($user)
        ->delete(route('restaurant.menu.option-groups.destroy', [$restaurant, $item, $group]))
        ->assertRedirect();

    expect(MenuOptionGroup::query()->count())->toBe(0);
});
