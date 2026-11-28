<?php

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Profile;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('owners can assign a menu section to a daypart', function () {
    $owner = User::factory()->create();
    $profile = Profile::factory()->for($owner)->restaurant()->create();

    $this->actingAs($owner)
        ->post(route('restaurant.menu.categories.store', $profile), [
            'name' => 'Lunch bowls',
            'description' => 'Served from noon',
            'daypart' => 'lunch',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $category = $profile->menuCategories()->sole();

    expect($category->daypart)->toBe('lunch');

    $this->actingAs($owner)
        ->patch(route('restaurant.menu.categories.update', [$profile, $category]), [
            'name' => 'Evening bowls',
            'daypart' => 'dinner',
        ])
        ->assertSessionHasNoErrors();

    expect($category->refresh()->daypart)->toBe('dinner');
});

test('unknown dayparts are rejected', function () {
    $owner = User::factory()->create();
    $profile = Profile::factory()->for($owner)->restaurant()->create();

    $this->actingAs($owner)
        ->post(route('restaurant.menu.categories.store', $profile), [
            'name' => 'Brunch',
            'daypart' => 'midnight',
        ])
        ->assertSessionHasErrors('daypart');

    expect(MenuCategory::query()->count())->toBe(0);
});

test('the public menu exposes each section daypart', function () {
    $profile = Profile::factory()->restaurant()->create([
        'is_published' => true,
    ]);

    $category = MenuCategory::factory()->for($profile)->create([
        'daypart' => 'breakfast',
    ]);

    MenuItem::factory()->for($category, 'category')->create([
        'is_available' => true,
    ]);

    $this->get(route('restaurant.show', $profile->username))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('restaurant/Show')
            ->where('menu.0.daypart', 'breakfast')
            ->where('dayparts.breakfast', 'Breakfast'));
});
