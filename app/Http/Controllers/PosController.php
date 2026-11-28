<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\PosToken;
use App\Models\Profile;
use App\Services\OrderPlacementService;
use App\Services\PosWebhookDispatcher;
use App\Services\SmsNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PosController extends Controller
{
    /**
     * The full menu catalog for the token's restaurant.
     */
    public function menu(Request $request): JsonResponse
    {
        $profile = $this->profile($request)->load('menuCategories.items.optionGroups.options');

        return response()->json([
            'restaurant' => [
                'username' => $profile->username,
                'name' => $profile->restaurantName(),
                'currency' => $profile->menu_currency,
                'ordering_enabled' => $profile->menu_ordering_enabled,
                'accepting_orders' => $profile->accepting_orders,
                'menu_url' => route('restaurant.show', $profile->username),
            ],
            'categories' => $profile->menuCategories->map(fn ($category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'description' => $category->description,
                'is_active' => $category->is_active,
                'items' => $category->items->map(fn (MenuItem $item): array => $this->itemJson($item))->all(),
            ])->all(),
        ]);
    }

    /**
     * Orders for the token's restaurant, newest first.
     */
    public function orders(Request $request): JsonResponse
    {
        $profile = $this->profile($request);

        $data = $request->validate([
            'status' => ['nullable', Rule::enum(OrderStatus::class)],
            'since' => ['nullable', 'date'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $orders = $profile->orders()
            ->with('items')
            ->when($data['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($data['since'] ?? null, fn ($query, string $since) => $query->where('updated_at', '>=', $since))
            ->latest('id')
            ->limit($data['limit'] ?? 50)
            ->get();

        return response()->json([
            'data' => $orders->map(fn (Order $order): array => $this->orderJson($order))->all(),
        ]);
    }

    /**
     * Create an order from the POS.
     */
    public function storeOrder(Request $request, OrderPlacementService $orders): JsonResponse
    {
        $profile = $this->profile($request);

        $data = $request->validate([
            'customer_name' => ['nullable', 'string', 'max:80'],
            'customer_phone' => ['nullable', 'string', 'max:40'],
            'customer_email' => ['nullable', 'email', 'max:120'],
            'order_type' => ['nullable', 'string', Rule::in(array_keys(Order::TYPES))],
            'table_number' => ['nullable', 'string', 'max:20'],
            'delivery_address' => ['nullable', 'string', 'max:255', 'required_if:order_type,delivery'],
            'coupon_code' => ['nullable', 'string', 'max:40'],
            'redeem_points' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
            'tip_cents' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:20'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
            'items.*.options' => ['nullable', 'array', 'max:20'],
            'items.*.options.*' => ['integer'],
        ]);

        $data['order_type'] ??= 'takeaway';
        $data['customer_name'] = filled($data['customer_name'] ?? null) ? $data['customer_name'] : 'Counter order';
        $data['customer_phone'] = $data['customer_phone'] ?? '';

        $order = $orders->place($profile, $data, notify: false);

        return response()->json(['data' => $this->orderJson($order)], 201);
    }

    /**
     * Move an order to a new status from the POS.
     */
    public function updateOrder(Request $request, Order $order): JsonResponse
    {
        $profile = $this->profile($request);

        abort_unless($order->profile_id === $profile->id, 404);

        $data = $request->validate([
            'status' => ['required', Rule::enum(OrderStatus::class)],
        ]);

        $previous = $order->status->value;

        $order->update(['status' => $data['status']]);

        if ($previous !== $data['status']) {
            app()->terminating(function () use ($order, $previous): void {
                app(PosWebhookDispatcher::class)->orderUpdated($order, $previous);
                app(SmsNotifier::class)->orderStatusChanged($order, $previous);
            });
        }

        return response()->json(['data' => $this->orderJson($order->load('items'))]);
    }

    /**
     * Mark an item sold out or back in stock, or correct its price.
     */
    public function updateItem(Request $request, MenuItem $item): JsonResponse
    {
        $profile = $this->profile($request);

        abort_unless($item->category->profile_id === $profile->id, 404);

        $data = $request->validate([
            'is_available' => ['sometimes', 'boolean'],
            'price' => ['sometimes', 'nullable', 'string', 'max:30'],
        ]);

        $item->update($data);

        return response()->json(['data' => $this->itemJson($item)]);
    }

    /**
     * The restaurant behind the bearer token.
     */
    private function profile(Request $request): Profile
    {
        $token = $request->attributes->get('posToken');

        abort_unless($token instanceof PosToken, 401);

        return $token->profile;
    }

    /**
     * The serialized order returned by the API.
     *
     * @return array<string, mixed>
     */
    private function orderJson(Order $order): array
    {
        return [
            'id' => $order->id,
            'reference' => $order->reference,
            'status' => $order->status->value,
            'status_label' => $order->status->label(),
            'type' => $order->order_type,
            'type_label' => $order->typeLabel(),
            'table_number' => $order->table_number,
            'delivery_address' => $order->delivery_address,
            'coupon_code' => $order->coupon_code,
            'customer_name' => $order->customer_name,
            'customer_phone' => $order->customer_phone,
            'customer_email' => $order->customer_email,
            'notes' => $order->notes,
            'subtotal_cents' => $order->subtotal_cents,
            'discount_cents' => $order->discount_cents,
            'delivery_fee_cents' => $order->delivery_fee_cents,
            'service_cents' => $order->service_cents,
            'tax_cents' => $order->tax_cents,
            'tip_cents' => $order->tip_cents,
            'total_cents' => $order->total_cents,
            'currency' => $order->currency,
            'created_at' => $order->created_at?->toIso8601String(),
            'updated_at' => $order->updated_at?->toIso8601String(),
            'items' => $order->items->map(fn ($item): array => [
                'menu_item_id' => $item->menu_item_id,
                'name' => $item->name,
                'quantity' => $item->quantity,
                'unit_price_cents' => $item->unit_price_cents,
                'line_total_cents' => $item->line_total_cents,
                'notes' => $item->notes,
                'options' => $item->options ?? [],
            ])->all(),
        ];
    }

    /**
     * The serialized menu item returned by the API.
     *
     * @return array<string, mixed>
     */
    private function itemJson(MenuItem $item): array
    {
        return [
            'id' => $item->id,
            'name' => $item->name,
            'description' => $item->description,
            'price' => $item->price,
            'price_cents' => $item->priceInCents(),
            'dietary' => $item->dietary,
            'allergens' => $item->allergens ?? [],
            'is_available' => $item->is_available,
            'is_featured' => $item->is_featured,
            'option_groups' => $item->optionGroupsPayload(),
        ];
    }
}
