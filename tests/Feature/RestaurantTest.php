<?php

use App\Enums\OrderStatus;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Profile;
use App\Models\ProfileView;
use App\Models\ServiceRequest;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the restaurant hub lists every restaurant the user runs', function () {
    $user = User::factory()->create();
    Profile::factory()->for($user)->restaurant()->create([
        'username' => 'first-kitchen',
        'display_name' => 'First Kitchen',
    ]);
    Profile::factory()->for($user)->restaurant()->create([
        'username' => 'second-kitchen',
        'display_name' => 'Second Kitchen',
    ]);

    $this->actingAs($user)
        ->get(route('restaurant.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('restaurant/Index')
            ->has('restaurants', 2)
            ->where('restaurants.0.username', 'first-kitchen')
            ->where('restaurants.1.username', 'second-kitchen')
            ->where('selected.username', 'first-kitchen')
            ->where('totals.restaurants', 2)
            ->has('templates', count(Profile::RESTAURANT_TEMPLATES)));

    $this->actingAs($user)
        ->get(route('restaurant.index', ['restaurant' => 'second-kitchen']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('selected.username', 'second-kitchen'));
});

test('owners can add another restaurant with its own menu', function () {
    $user = User::factory()->create();
    $primary = Profile::factory()->for($user)->restaurant()->create(['username' => 'primary']);

    $this->actingAs($user)
        ->post(route('restaurant.store'), [
            'name' => 'Downtown Pizza',
            'username' => 'Downtown-Pizza',
            'description' => 'Slices by the square',
        ])
        ->assertRedirect(route('restaurant.index', [
            'restaurant' => 'downtown-pizza',
        ]))
        ->assertSessionHasNoErrors();

    $restaurant = Profile::query()->where('username', 'downtown-pizza')->sole();

    expect($restaurant->user_id)->toBe($user->id)
        ->and($restaurant->restaurantName())->toBe('Downtown Pizza')
        ->and($restaurant->business_type)->toBe('restaurant')
        ->and($restaurant->is_published)->toBeTrue()
        ->and($restaurant->menu_ordering_enabled)->toBeFalse();

    $this->actingAs($user)
        ->post(route('restaurant.menu.categories.store', $restaurant), [
            'name' => 'Slices',
        ])
        ->assertSessionHasNoErrors();

    $this->actingAs($user)
        ->post(route('restaurant.menu.categories.store', $primary), [
            'name' => 'Coffee',
        ])
        ->assertSessionHasNoErrors();

    $this->actingAs($user)
        ->get(route('restaurant.menu', $restaurant))
        ->assertInertia(fn (Assert $page) => $page
            ->component('menu/Index')
            ->where('restaurant.username', 'downtown-pizza')
            ->has('categories', 1)
            ->where('categories.0.name', 'Slices'));

    $this->actingAs($user)
        ->patch(route('restaurant.update', $restaurant), [
            'name' => 'Downtown Slice Co',
            'address' => '12 Main Street',
            'phone' => '+1 555 0110',
        ])
        ->assertSessionHasNoErrors();

    expect($restaurant->refresh()->restaurantName())->toBe('Downtown Slice Co')
        ->and($restaurant->address)->toBe('12 Main Street');
});

test('new restaurant addresses must be unique and not reserved', function () {
    $user = User::factory()->create();
    Profile::factory()->create(['username' => 'taken-name']);

    $this->actingAs($user)
        ->post(route('restaurant.store'), [
            'name' => 'Copycat',
            'username' => 'taken-name',
        ])
        ->assertSessionHasErrors('username');

    $this->actingAs($user)
        ->post(route('restaurant.store'), [
            'name' => 'Reserved',
            'username' => 'restaurant',
        ])
        ->assertSessionHasErrors('username');

    expect($user->profiles()->count())->toBe(0);
});

test('restaurant pages are scoped to the signed-in owner', function () {
    $owner = User::factory()->create();
    $restaurant = Profile::factory()->for($owner)->restaurant()->create([
        'username' => 'owner-place',
        'display_name' => 'Owner Place',
    ]);

    $intruder = User::factory()->create();

    $this->actingAs($intruder)
        ->get(route('restaurant.menu', $restaurant))
        ->assertForbidden();

    $this->actingAs($intruder)
        ->get(route('restaurant.orders', $restaurant))
        ->assertForbidden();

    $this->actingAs($intruder)
        ->patch(route('restaurant.update', $restaurant), ['name' => 'Hijacked'])
        ->assertForbidden();

    $this->actingAs($intruder)
        ->get(route('restaurant.qr', $restaurant))
        ->assertForbidden();

    expect($restaurant->refresh()->display_name)->toBe('Owner Place');
});

test('orders stay with the restaurant that received them', function () {
    $user = User::factory()->create();
    $first = Profile::factory()->for($user)->restaurant()->create(['username' => 'first-place']);
    $second = Profile::factory()->for($user)->restaurant()->create(['username' => 'second-place']);
    $order = Order::factory()->for($first)->create();

    $this->actingAs($user)
        ->get(route('restaurant.orders', $first))
        ->assertInertia(fn (Assert $page) => $page
            ->component('orders/Index')
            ->has('orders.data', 1)
            ->where('orders.data.0.reference', $order->reference));

    $this->actingAs($user)
        ->get(route('restaurant.orders', $second))
        ->assertInertia(fn (Assert $page) => $page
            ->has('orders.data', 0));

    $this->actingAs($user)
        ->patch(route('restaurant.orders.update', [$second, $order]), [
            'status' => 'cancelled',
        ])
        ->assertNotFound();

    expect($order->refresh()->status)->toBe(OrderStatus::New);
});

test('the legacy menu and orders urls redirect to the restaurant pages', function () {
    $user = User::factory()->create();
    $profile = Profile::factory()->for($user)->restaurant()->create(['username' => 'legacy-place']);

    $this->actingAs($user)
        ->get('/menu')
        ->assertRedirect(route('restaurant.menu', $profile));

    $this->actingAs($user)
        ->get('/orders')
        ->assertRedirect(route('restaurant.orders', $profile));
});

test('restaurant menus live on their own public page', function () {
    $profile = Profile::factory()->restaurant()->create([
        'username' => 'split-menu',
        'is_published' => true,
    ]);

    $category = MenuCategory::factory()->for($profile)->create(['name' => 'Mains']);
    MenuItem::factory()->for($category, 'category')->create([
        'name' => 'Ramen',
        'allergens' => ['milk'],
    ]);

    $this->get(route('restaurant.show', $profile->username))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('restaurant/Show')
            ->where('restaurant.username', 'split-menu')
            ->has('menu', 1)
            ->where('menu.0.items.0.name', 'Ramen')
            ->where('menu.0.items.0.allergens', ['milk']));

    $this->get(route('bio.show', $profile->username))
        ->assertRedirect(route('restaurant.show', $profile->username));
});

test('restaurants can choose a template and feature dishes on the public menu', function () {
    $user = User::factory()->create();
    $restaurant = Profile::factory()->for($user)->restaurant()->create([
        'username' => 'templated',
    ]);

    $this->actingAs($user)
        ->patch(route('restaurant.update', $restaurant), [
            'name' => 'Templated Kitchen',
            'template' => 'elegant',
        ])
        ->assertSessionHasNoErrors();

    expect($restaurant->refresh()->restaurant_template)->toBe('elegant');

    $category = MenuCategory::factory()->for($restaurant)->create(['name' => 'Dinner']);
    $featured = MenuItem::factory()->for($category, 'category')->create(['name' => 'Lamb ragu']);
    MenuItem::factory()->for($category, 'category')->create(['name' => 'House salad']);

    $this->actingAs($user)
        ->patch(route('restaurant.menu.items.update', [$restaurant, $featured]), [
            'name' => 'Lamb ragu',
            'price' => '24.00',
            'is_featured' => true,
        ])
        ->assertSessionHasNoErrors();

    $this->get(route('restaurant.show', $restaurant->username))
        ->assertInertia(fn (Assert $page) => $page
            ->where('restaurant.template', 'elegant')
            ->where('menu.0.items.0.is_featured', true)
            ->where('menu.0.items.1.is_featured', false));
});

test('guests can call a waiter and the team can complete the request', function () {
    $user = User::factory()->create();
    $restaurant = Profile::factory()->for($user)->restaurant()->create([
        'username' => 'service-place',
    ]);

    $this->post(route('restaurant.service-request', $restaurant->username), [
        'type' => 'waiter',
        'table_number' => '4',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $serviceRequest = ServiceRequest::query()->sole();

    expect($serviceRequest->type)->toBe('waiter')
        ->and($serviceRequest->table_number)->toBe('4')
        ->and($serviceRequest->status)->toBe(ServiceRequest::STATUS_PENDING);

    $this->actingAs($user)
        ->get(route('restaurant.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('serviceRequests', 1)
            ->where('serviceRequests.0.type_label', 'Call waiter')
            ->where('totals.pending_requests', 1));

    $this->actingAs($user)
        ->patch(route('restaurant.requests.complete', [$restaurant, $serviceRequest]))
        ->assertRedirect();

    expect($serviceRequest->refresh()->status)->toBe(ServiceRequest::STATUS_DONE);
});

test('service requests reject unknown types and stay scoped to the owner', function () {
    $restaurant = Profile::factory()->restaurant()->create(['username' => 'scoped-service']);
    $serviceRequest = ServiceRequest::factory()->for($restaurant)->create();

    $this->post(route('restaurant.service-request', $restaurant->username), [
        'type' => 'fire',
    ])->assertSessionHasErrors('type');

    $intruder = User::factory()->create();

    $this->actingAs($intruder)
        ->patch(route('restaurant.requests.complete', [$restaurant, $serviceRequest]))
        ->assertForbidden();

    expect($serviceRequest->refresh()->status)->toBe(ServiceRequest::STATUS_PENDING);
});

test('the hub counts menu views', function () {
    $user = User::factory()->create();
    $restaurant = Profile::factory()->for($user)->restaurant()->create();

    ProfileView::factory()->for($restaurant)->create(['viewed_at' => now()]);

    $this->actingAs($user)
        ->get(route('restaurant.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('totals.views_today', 1)
            ->where('totals.views_week', 1));
});

test('the biolink stays separate from the restaurants a user runs', function () {
    $user = User::factory()->create();
    $restaurant = Profile::factory()->for($user)->restaurant()->create();

    $this->actingAs($user)
        ->get(route('bio.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->missing('businessTypes.restaurant'));

    $biolink = Profile::query()
        ->where('user_id', $user->id)
        ->whereKeyNot($restaurant->id)
        ->sole();

    expect($biolink->isRestaurant())->toBeFalse()
        ->and($user->profile?->is($biolink))->toBeTrue()
        ->and($user->restaurants()->count())->toBe(1);
});
