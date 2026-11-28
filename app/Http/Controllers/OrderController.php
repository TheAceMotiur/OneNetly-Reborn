<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Profile;
use App\Services\AuditLogger;
use App\Services\PosWebhookDispatcher;
use App\Services\SmsNotifier;
use App\Services\WhatsAppNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class OrderController extends Controller
{
    /**
     * Show the orders for one of the user's restaurants.
     */
    public function index(Request $request, Profile $profile): Response
    {
        $this->authorizeProfile($request, $profile);

        $orders = $profile->orders()->with('items')->paginate(20);

        $today = Carbon::today();

        return Inertia::render('orders/Index', [
            'orders' => $orders->through(fn (Order $order): array => [
                'id' => $order->id,
                'reference' => $order->reference,
                'customer_name' => $order->customer_name,
                'customer_phone' => $order->customer_phone,
                'customer_email' => $order->customer_email,
                'type' => $order->order_type,
                'type_label' => $order->typeLabel(),
                'table_number' => $order->table_number,
                'delivery_address' => $order->delivery_address,
                'delivery_postcode' => $order->delivery_postcode,
                'scheduled_for' => $order->scheduled_for?->toDayDateTimeString(),
                'coupon_code' => $order->coupon_code,
                'notes' => $order->notes,
                'subtotal' => number_format($order->subtotal_cents / 100, 2).' '.$order->currency,
                'discount' => number_format($order->discount_cents / 100, 2).' '.$order->currency,
                'points_redeemed' => $order->points_redeemed,
                'loyalty_discount' => number_format($order->loyalty_discount_cents / 100, 2).' '.$order->currency,
                'delivery_fee' => number_format($order->delivery_fee_cents / 100, 2).' '.$order->currency,
                'service' => number_format($order->service_cents / 100, 2).' '.$order->currency,
                'tax' => number_format($order->tax_cents / 100, 2).' '.$order->currency,
                'tip' => number_format($order->tip_cents / 100, 2).' '.$order->currency,
                'total' => $order->displayTotal(),
                'status' => $order->status->value,
                'status_label' => $order->status->label(),
                'status_class' => $order->status->colorClass(),
                'created_at' => $order->created_at?->toDateTimeString(),
                'created_human' => $order->created_at?->diffForHumans(),
                'items' => $order->items->map(fn ($item): array => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'quantity' => $item->quantity,
                    'line_total' => number_format($item->line_total_cents / 100, 2).' '.$order->currency,
                    'notes' => $item->notes,
                    'options' => $item->options ?? [],
                ])->all(),
            ]),
            'stats' => [
                'today_orders' => $profile->orders()->whereDate('created_at', $today)->count(),
                'today_total' => number_format(
                    $profile->orders()->whereDate('created_at', $today)->sum('total_cents') / 100,
                    2,
                ).' '.$profile->menu_currency,
                'new_orders' => $profile->orders()->where('status', OrderStatus::New)->count(),
            ],
            'statuses' => OrderStatus::options(),
            'types' => Order::TYPES,
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
            'ordersUrl' => route('restaurant.orders', $profile),
            'menuUrl' => route('restaurant.menu', $profile),
            'pagination' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    /**
     * Move an order through the restaurant workflow.
     */
    public function update(Request $request, Profile $profile, Order $order): RedirectResponse
    {
        $this->authorizeProfile($request, $profile);

        abort_unless($order->profile_id === $profile->id, 404);

        $data = $request->validate([
            'status' => ['required', Rule::enum(OrderStatus::class)],
        ]);

        $previous = $order->status->value;

        $order->update(['status' => $data['status']]);

        app(AuditLogger::class)->log(
            $profile,
            'order.status',
            __('Order :reference changed to :status.', [
                'reference' => $order->reference,
                'status' => strtolower($order->status->label()),
            ]),
            $order,
        );

        if ($previous !== $data['status']) {
            app()->terminating(function () use ($order, $previous): void {
                app(PosWebhookDispatcher::class)->orderUpdated($order, $previous);
                app(SmsNotifier::class)->orderStatusChanged($order, $previous);

                try {
                    app(WhatsAppNotifier::class)->orderStatusChanged($order, $previous);
                } catch (Throwable $exception) {
                    report($exception);
                }
            });
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Order status updated.')]);

        return back();
    }

    /**
     * Ensure the restaurant belongs to the signed-in user.
     */
    private function authorizeProfile(Request $request, Profile $profile): void
    {
        abort_unless($profile->canOperate($request->user()), 403);
    }
}
