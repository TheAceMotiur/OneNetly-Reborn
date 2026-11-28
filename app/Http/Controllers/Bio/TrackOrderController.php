<?php

namespace App\Http\Controllers\Bio;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Profile;
use App\Services\LoyaltyService;
use Inertia\Inertia;
use Inertia\Response;

class TrackOrderController extends Controller
{
    /**
     * Show the public status page for one guest order.
     */
    public function __invoke(string $username, string $reference, LoyaltyService $loyalty): Response
    {
        $profile = Profile::query()
            ->where('username', $username)
            ->where('is_published', true)
            ->firstOrFail();

        $order = $profile->orders()
            ->with('items')
            ->where('reference', $reference)
            ->firstOrFail();

        return Inertia::render('restaurant/Track', [
            'restaurant' => [
                'username' => $profile->username,
                'name' => $profile->restaurantName(),
                'address' => $profile->address,
                'phone' => $profile->phone,
                'currency' => $profile->menu_currency,
            ],
            'order' => [
                'reference' => $order->reference,
                'status' => $order->status->value,
                'status_label' => $order->status->label(),
                'status_class' => $order->status->colorClass(),
                'type' => $order->order_type,
                'type_label' => $order->typeLabel(),
                'table_number' => $order->table_number,
                'delivery_address' => $order->delivery_address,
                'coupon_code' => $order->coupon_code,
                'customer_name' => $order->customer_name,
                'notes' => $order->notes,
                'created_at' => $order->created_at?->format('j M Y, H:i'),
                'created_human' => $order->created_at?->diffForHumans(),
                'items' => $order->items->map(fn ($item): array => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'quantity' => $item->quantity,
                    'unit_price' => $this->money($item->unit_price_cents, $order->currency),
                    'line_total' => $this->money($item->line_total_cents, $order->currency),
                    'notes' => $item->notes,
                    'options' => $item->options ?? [],
                ])->all(),
                'subtotal' => $this->money($order->subtotal_cents, $order->currency),
                'discount' => $this->money($order->discount_cents, $order->currency),
                'points_redeemed' => $order->points_redeemed,
                'loyalty_discount' => $this->money($order->loyalty_discount_cents, $order->currency),
                'delivery_fee' => $this->money($order->delivery_fee_cents, $order->currency),
                'service' => $this->money($order->service_cents, $order->currency),
                'tax' => $this->money($order->tax_cents, $order->currency),
                'tip' => $this->money($order->tip_cents, $order->currency),
                'total' => $this->money($order->total_cents, $order->currency),
                'currency' => $order->currency,
            ],
            'loyalty' => ($account = $loyalty->forPhone($profile->id, $order->customer_phone)) !== null
                ? [
                    'points' => $account->points,
                    'visits' => $account->visits,
                ]
                : null,
            'steps' => [
                ['key' => 'new', 'label' => 'Order received'],
                ['key' => 'preparing', 'label' => 'In the kitchen'],
                ['key' => 'ready', 'label' => 'Ready'],
                ['key' => 'completed', 'label' => 'Completed'],
            ],
        ]);
    }

    /**
     * Format a cent amount with the order currency.
     */
    private function money(int $cents, string $currency): string
    {
        return number_format($cents / 100, 2).' '.$currency;
    }
}
