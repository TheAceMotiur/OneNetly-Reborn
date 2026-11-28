<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Profile;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class KitchenController extends Controller
{
    /**
     * Show the live kitchen board.
     */
    public function __invoke(Request $request, Profile $profile): Response
    {
        abort_unless($profile->canOperate($request->user()), 403);

        $orders = $profile->orders()
            ->with('items')
            ->whereIn('status', [OrderStatus::New, OrderStatus::Preparing, OrderStatus::Ready])
            ->oldest('id')
            ->limit(60)
            ->get();

        return Inertia::render('kitchen/Index', [
            'orders' => $orders->map(fn (Order $order): array => [
                'id' => $order->id,
                'reference' => $order->reference,
                'status' => $order->status->value,
                'status_label' => $order->status->label(),
                'status_class' => $order->status->colorClass(),
                'type_label' => $order->typeLabel(),
                'table_number' => $order->table_number,
                'customer_name' => $order->customer_name,
                'notes' => $order->notes,
                'created_at' => $order->created_at?->toIso8601String(),
                'waiting_minutes' => (int) $order->created_at?->diffInMinutes(now()),
                'items' => $order->items->map(fn ($item): array => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'quantity' => $item->quantity,
                    'notes' => $item->notes,
                    'options' => $item->options ?? [],
                ])->all(),
                'update_url' => route('restaurant.orders.update', [$profile, $order]),
            ])->all(),
            'restaurant' => [
                'username' => $profile->username,
                'name' => $profile->restaurantName(),
            ],
            'restaurants' => $request->user()->accessibleRestaurants()
                ->map(fn (Profile $restaurant): array => [
                    'username' => $restaurant->username,
                    'name' => $restaurant->restaurantName(),
                ])
                ->all(),
            'refresh_url' => route('restaurant.kitchen', $profile),
        ]);
    }
}
