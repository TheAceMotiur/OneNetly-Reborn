<?php

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Profile;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('owners can add menu sections and items', function () {
    $user = User::factory()->create();
    $profile = Profile::factory()->for($user)->create();

    $this->actingAs($user)
        ->post(route('restaurant.menu.categories.store', $profile), [
            'name' => 'Breakfast',
            'description' => 'Served until 11am',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $category = $profile->menuCategories()->sole();

    $this->actingAs($user)
        ->post(route('restaurant.menu.items.store', [$profile, $category]), [
            'name' => 'Avocado toast',
            'description' => 'Sourdough, avocado and chili',
            'price' => '12.00',
            'dietary' => 'V',
            'allergens' => ['gluten', 'milk'],
            'is_featured' => true,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $item = $category->items()->sole();

    expect($category->name)->toBe('Breakfast')
        ->and($category->is_active)->toBeTrue()
        ->and($item->name)->toBe('Avocado toast')
        ->and($item->price)->toBe('12.00')
        ->and($item->is_available)->toBeTrue()
        ->and($item->is_featured)->toBeTrue()
        ->and($item->allergens)->toBe(['gluten', 'milk']);

    $this->actingAs($user)
        ->get(route('restaurant.menu', $profile))
        ->assertInertia(fn (Assert $page) => $page
            ->component('menu/Index')
            ->has('categories', 1)
            ->where('categories.0.name', 'Breakfast')
            ->has('categories.0.items', 1));
});

test('the public restaurant page exposes only visible available menu content', function () {
    $profile = Profile::factory()->restaurant()->create([
        'username' => 'menu-page',
        'is_published' => true,
    ]);

    $visible = MenuCategory::factory()->for($profile)->create([
        'name' => 'Drinks',
        'position' => 0,
    ]);
    MenuItem::factory()->for($visible, 'category')->create([
        'name' => 'Espresso',
        'is_available' => true,
    ]);
    MenuItem::factory()->for($visible, 'category')->unavailable()->create([
        'name' => 'Sold out latte',
    ]);
    MenuCategory::factory()->for($profile)->inactive()->create([
        'name' => 'Hidden section',
    ]);

    $this->get(route('restaurant.show', $profile->username))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('restaurant/Show')
            ->has('menu', 1)
            ->where('menu.0.name', 'Drinks')
            ->has('menu.0.items', 1)
            ->where('menu.0.items.0.name', 'Espresso'));
});

test('users cannot manage another page menu', function () {
    $owner = User::factory()->create();
    $profile = Profile::factory()->for($owner)->create();
    $category = MenuCategory::factory()->for($profile)->create();
    $item = MenuItem::factory()->for($category, 'category')->create();

    $intruder = User::factory()->create();

    $this->actingAs($intruder)
        ->patch(route('restaurant.menu.categories.update', [$profile, $category]), [
            'name' => 'Hijacked',
        ])
        ->assertForbidden();

    $this->actingAs($intruder)
        ->post(route('restaurant.menu.items.store', [$profile, $category]), [
            'name' => 'Injected item',
        ])
        ->assertForbidden();

    $this->actingAs($intruder)
        ->delete(route('restaurant.menu.items.destroy', [$profile, $item]))
        ->assertForbidden();

    expect($category->refresh()->name)->not->toBe('Hijacked')
        ->and($item->fresh())->not->toBeNull();
});

test('menu items require a name', function () {
    $user = User::factory()->create();
    $profile = Profile::factory()->for($user)->create();
    $category = MenuCategory::factory()->for($profile)->create();

    $this->actingAs($user)
        ->post(route('restaurant.menu.items.store', [$profile, $category]), [
            'name' => '',
            'price' => '5.00',
        ])
        ->assertSessionHasErrors('name');

    expect($category->items()->count())->toBe(0);
});
