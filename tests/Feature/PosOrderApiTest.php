<?php

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\PosToken;
use App\Models\Profile;
use App\Models\WebhookDelivery;
use App\Services\PosWebhookDispatcher;
use Illuminate\Support\Facades\Http;

/**
 * @return array{0: Profile, 1: MenuItem, 2: string}
 */
function posOrderRestaurant(): array
{
    $restaurant = Profile::factory()->restaurant()->create([
        'is_published' => true,
        'menu_ordering_enabled' => true,
        'accepting_orders' => true,
        'menu_currency' => 'USD',
    ]);

    $category = MenuCategory::factory()->for($restaurant)->create();
    $item = MenuItem::factory()->for($category, 'category')->create([
        'name' => 'Ramen',
        'price' => '12.00',
        'is_available' => true,
    ]);

    [, $plain] = PosToken::issue($restaurant, 'Counter');

    return [$restaurant, $item, $plain];
}

test('a POS can create a priced order', function () {
    [$restaurant, $item, $plain] = posOrderRestaurant();

    $this->withToken($plain)
        ->postJson('/api/pos/orders', [
            'customer_name' => 'Walk-in',
            'order_type' => 'takeaway',
            'items' => [['id' => $item->id, 'quantity' => 2]],
        ])
        ->assertCreated()
        ->assertJsonPath('data.total_cents', 2400)
        ->assertJsonPath('data.status', 'new')
        ->assertJsonPath('data.items.0.name', 'Ramen');

    $order = Order::query()->sole();

    expect($order->profile_id)->toBe($restaurant->id)
        ->and($order->customer_name)->toBe('Walk-in')
        ->and($order->subtotal_cents)->toBe(2400);
});

test('POS orders use the same item validation as the public menu', function () {
    [, , $plain] = posOrderRestaurant();

    $this->withToken($plain)
        ->postJson('/api/pos/orders', [
            'items' => [['id' => 999999, 'quantity' => 1]],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('items');

    expect(Order::query()->count())->toBe(0);
});

test('POS status changes emit an order.updated webhook', function () {
    Http::fake(['https://pos.example/hooks' => Http::response(['ok' => true], 200)]);

    [$restaurant] = posOrderRestaurant();
    $restaurant->update([
        'pos_webhook_url' => 'https://pos.example/hooks',
        'pos_webhook_secret' => 'secret-123',
    ]);

    $order = Order::factory()->for($restaurant)->create();

    app(PosWebhookDispatcher::class)->orderUpdated($order, 'new');

    $delivery = WebhookDelivery::query()->sole();

    expect($delivery->event)->toBe('order.updated')
        ->and($delivery->success)->toBeTrue();

    Http::assertSent(function ($request): bool {
        $payload = json_decode($request->body(), true);

        return $request->hasHeader('X-OneNetly-Event', 'order.updated')
            && ($payload['previous_status'] ?? null) === 'new'
            && ($payload['event'] ?? null) === 'order.updated';
    });
});
