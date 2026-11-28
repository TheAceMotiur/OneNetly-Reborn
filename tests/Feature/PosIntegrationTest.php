<?php

use App\Enums\OrderStatus;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\PosToken;
use App\Models\Profile;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Services\PosWebhookDispatcher;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @return array{0: User, 1: Profile, 2: string}
 */
function posRestaurant(array $attributes = []): array
{
    $user = User::factory()->create();

    $restaurant = Profile::factory()->for($user)->restaurant()->create([
        'is_published' => true,
        'menu_ordering_enabled' => true,
        ...$attributes,
    ]);

    [$token, $plain] = PosToken::issue($restaurant, 'Test POS');

    return [$user, $restaurant, $plain];
}

test('owners can issue and revoke POS api tokens', function () {
    $user = User::factory()->create();
    $restaurant = Profile::factory()->for($user)->restaurant()->create();

    $this->actingAs($user)
        ->post(route('developer.pos.tokens.store', $restaurant), ['name' => 'Bar POS'])
        ->assertRedirect()
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('pos_token');

    $token = $restaurant->posTokens()->sole();

    expect($token->name)->toBe('Bar POS')
        ->and($token->token_prefix)->toStartWith('pos_')
        ->and($token->token_hash)->toHaveLength(64)
        ->and($token->last_used_at)->toBeNull();

    $this->actingAs($user)
        ->delete(route('developer.pos.tokens.destroy', [$restaurant, $token]))
        ->assertRedirect();

    expect($restaurant->posTokens()->count())->toBe(0);
});

test('the POS api requires a valid bearer token', function () {
    $this->getJson('/api/pos/menu')->assertUnauthorized();
    $this->withToken('pos_not-a-real-token')->getJson('/api/pos/menu')->assertUnauthorized();
});

test('a POS token reads only its own restaurant menu', function () {
    [, $restaurant, $plain] = posRestaurant(['username' => 'pos-place']);

    $category = MenuCategory::factory()->for($restaurant)->create(['name' => 'Mains']);
    MenuItem::factory()->for($category, 'category')->create([
        'name' => 'Ramen',
        'price' => '12.00',
        'allergens' => ['gluten'],
    ]);

    $this->withToken($plain)
        ->getJson('/api/pos/menu')
        ->assertOk()
        ->assertJsonPath('restaurant.username', 'pos-place')
        ->assertJsonPath('categories.0.name', 'Mains')
        ->assertJsonPath('categories.0.items.0.name', 'Ramen')
        ->assertJsonPath('categories.0.items.0.price_cents', 1200)
        ->assertJsonPath('categories.0.items.0.allergens', ['gluten']);

    expect($restaurant->posTokens()->sole()->last_used_at)->not->toBeNull();
});

test('a POS can pull and update orders for its restaurant', function () {
    [, $restaurant, $plain] = posRestaurant(['username' => 'pos-orders']);

    $order = Order::factory()->for($restaurant)->create(['status' => OrderStatus::New]);
    $foreign = Order::factory()->for(Profile::factory()->restaurant())->create();

    $this->withToken($plain)
        ->getJson('/api/pos/orders?status=new')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.reference', $order->reference);

    $this->withToken($plain)
        ->patchJson('/api/pos/orders/'.$order->reference, ['status' => 'preparing'])
        ->assertOk()
        ->assertJsonPath('data.status', 'preparing');

    expect($order->refresh()->status)->toBe(OrderStatus::Preparing);

    $this->withToken($plain)
        ->patchJson('/api/pos/orders/'.$foreign->reference, ['status' => 'cancelled'])
        ->assertNotFound();

    expect($foreign->refresh()->status)->toBe(OrderStatus::New);
});

test('a POS can mark an item sold out', function () {
    [, $restaurant, $plain] = posRestaurant(['username' => 'pos-stock']);

    $category = MenuCategory::factory()->for($restaurant)->create();
    $item = MenuItem::factory()->for($category, 'category')->create(['is_available' => true]);
    $foreignItem = MenuItem::factory()->create();

    $this->withToken($plain)
        ->patchJson('/api/pos/items/'.$item->id, ['is_available' => false])
        ->assertOk()
        ->assertJsonPath('data.is_available', false);

    expect($item->refresh()->is_available)->toBeFalse();

    $this->withToken($plain)
        ->patchJson('/api/pos/items/'.$foreignItem->id, ['is_available' => false])
        ->assertNotFound();
});

test('new orders are posted to the POS webhook with a signature', function () {
    Http::fake(['https://pos.example/hooks' => Http::response(['ok' => true], 200)]);

    [, $restaurant] = posRestaurant(['username' => 'pos-hook']);
    $restaurant->update([
        'pos_webhook_url' => 'https://pos.example/hooks',
        'pos_webhook_secret' => 'secret-123',
    ]);

    $order = Order::factory()->for($restaurant)->create();
    $order->items()->create([
        'name' => 'Ramen',
        'unit_price_cents' => 1200,
        'quantity' => 2,
        'line_total_cents' => 2400,
    ]);

    app(PosWebhookDispatcher::class)->orderCreated($order);

    $delivery = WebhookDelivery::query()->sole();

    expect($delivery->success)->toBeTrue()
        ->and($delivery->event)->toBe('order.created')
        ->and($delivery->attempts)->toBe(1)
        ->and($delivery->response_status)->toBe(200);

    Http::assertSent(function ($request): bool {
        $payload = json_decode($request->body(), true);

        return $request->url() === 'https://pos.example/hooks'
            && $request->hasHeader('X-OneNetly-Event', 'order.created')
            && $request->hasHeader('X-OneNetly-Signature', hash_hmac('sha256', $request->body(), 'secret-123'))
            && ($payload['order']['items'][0]['name'] ?? null) === 'Ramen';
    });
});

test('failed webhook deliveries are retried and logged', function () {
    Http::fake(['https://pos.example/broken' => Http::response('nope', 500)]);

    [, $restaurant] = posRestaurant(['username' => 'pos-broken-hook']);
    $restaurant->update([
        'pos_webhook_url' => 'https://pos.example/broken',
        'pos_webhook_secret' => 'secret-123',
    ]);

    app(PosWebhookDispatcher::class)->test($restaurant);

    $delivery = WebhookDelivery::query()->sole();

    expect($delivery->success)->toBeFalse()
        ->and($delivery->attempts)->toBe(2)
        ->and($delivery->response_status)->toBe(500)
        ->and($delivery->error)->toBe('HTTP 500');
});

test('pausing orders keeps the menu online but rejects new orders', function () {
    [$user, $restaurant] = posRestaurant(['username' => 'paused-place']);

    $category = MenuCategory::factory()->for($restaurant)->create();
    $item = MenuItem::factory()->for($category, 'category')->create(['price' => '9.00']);

    $this->actingAs($user)
        ->patch(route('developer.pos.update', $restaurant), [
            'webhook_url' => null,
            'accepting_orders' => false,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($restaurant->refresh()->accepting_orders)->toBeFalse();

    $this->post(route('restaurant.order', $restaurant->username), [
        'customer_name' => 'Maya',
        'customer_phone' => '+1 555 0102',
        'order_type' => 'takeaway',
        'items' => [['id' => $item->id, 'quantity' => 1]],
    ])->assertNotFound();

    $this->get(route('restaurant.show', $restaurant->username))
        ->assertInertia(fn (Assert $page) => $page
            ->where('restaurant.ordering_enabled', true)
            ->where('restaurant.accepting_orders', false));
});

test('saving a webhook generates a signing secret and the hub exposes POS settings', function () {
    [$user, $restaurant] = posRestaurant();

    $this->actingAs($user)
        ->patch(route('developer.pos.update', $restaurant), [
            'webhook_url' => 'https://pos.example/hooks',
            'webhook_secret' => '',
            'accepting_orders' => true,
        ])
        ->assertSessionHasNoErrors();

    $restaurant->refresh();

    expect($restaurant->pos_webhook_url)->toBe('https://pos.example/hooks')
        ->and($restaurant->pos_webhook_secret)->not->toBeNull()
        ->and(strlen((string) $restaurant->pos_webhook_secret))->toBe(40);

    $this->actingAs($user)
        ->get(route('developer.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('pos.webhook_url', 'https://pos.example/hooks')
            ->where('pos.has_secret', true));
});

test('the developer console scopes credentials to the selected restaurant', function () {
    $user = User::factory()->create();
    $first = Profile::factory()->for($user)->restaurant()->create(['username' => 'first-place']);
    $second = Profile::factory()->for($user)->restaurant()->create(['username' => 'second-place']);

    PosToken::issue($first, 'First key');
    PosToken::issue($second, 'Second key');

    $this->actingAs($user)
        ->get(route('developer.index', ['restaurant' => 'second-place']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('developers/Index')
            ->where('selected.username', 'second-place')
            ->where('pos.tokens.0.name', 'Second key')
            ->has('restaurants', 2));

    $this->actingAs($user)
        ->get(route('restaurant.index'))
        ->assertInertia(fn (Assert $page) => $page->missing('pos'));
});

test('the developer console is public and prompts guests to sign in', function () {
    $this->get(route('developer.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('developers/Index')
            ->has('restaurants', 0)
            ->where('selected', null)
            ->where('pos', null));
});
