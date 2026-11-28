<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Profile;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    /**
     * Show sales reporting for one restaurant.
     */
    public function index(Request $request, Profile $profile): Response
    {
        $this->authorizeProfile($request, $profile);

        [$from, $to] = $this->range($request);

        $orders = $profile->orders()
            ->whereBetween('created_at', [$from, $to])
            ->where('status', '!=', OrderStatus::Cancelled->value);

        $ordersCount = (clone $orders)->count();
        $revenue = (int) (clone $orders)->sum('total_cents');
        $itemsSold = (int) OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.profile_id', $profile->id)
            ->whereBetween('orders.created_at', [$from, $to])
            ->where('orders.status', '!=', OrderStatus::Cancelled->value)
            ->sum('order_items.quantity');

        return Inertia::render('reports/Index', [
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
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'stats' => [
                'orders' => $ordersCount,
                'revenue' => $this->money($revenue, $profile),
                'average_order' => $this->money($ordersCount > 0 ? intdiv($revenue, $ordersCount) : 0, $profile),
                'items_sold' => $itemsSold,
                'cancelled' => $profile->orders()
                    ->whereBetween('created_at', [$from, $to])
                    ->where('status', OrderStatus::Cancelled->value)
                    ->count(),
            ],
            'daily' => (clone $orders)
                ->selectRaw('DATE(created_at) as day, COUNT(*) as orders, SUM(total_cents) as revenue')
                ->groupBy('day')
                ->orderBy('day')
                ->get()
                ->map(fn (Order $order): array => [
                    'day' => (string) $order->getAttribute('day'),
                    'orders' => (int) $order->getAttribute('orders'),
                    'revenue' => $this->money((int) $order->getAttribute('revenue'), $profile),
                ])
                ->all(),
            'by_type' => (clone $orders)
                ->selectRaw('order_type, COUNT(*) as orders, SUM(total_cents) as revenue')
                ->groupBy('order_type')
                ->orderByDesc('revenue')
                ->get()
                ->map(fn (Order $order): array => [
                    'type' => $order->typeLabel(),
                    'orders' => (int) $order->getAttribute('orders'),
                    'revenue' => $this->money((int) $order->getAttribute('revenue'), $profile),
                ])
                ->all(),
            'top_items' => $this->topItems($profile, $from, $to),
            'export_url' => route('restaurant.reports.export', [
                'profile' => $profile,
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ]),
        ]);
    }

    /**
     * Download the orders in the selected range as CSV.
     */
    public function export(Request $request, Profile $profile): StreamedResponse
    {
        $this->authorizeProfile($request, $profile);

        [$from, $to] = $this->range($request);

        $orders = $profile->orders()
            ->with('items')
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('created_at')
            ->get();

        return response()->streamDownload(function () use ($orders): void {
            $output = fopen('php://output', 'w');

            if ($output === false) {
                return;
            }

            fputcsv($output, [
                'Reference', 'Date', 'Status', 'Type', 'Customer', 'Phone',
                'Items', 'Subtotal', 'Discount', 'Coupon', 'Delivery', 'Service', 'Tax', 'Tip', 'Total', 'Currency',
            ]);

            foreach ($orders as $order) {
                fputcsv($output, [
                    $order->reference,
                    $order->created_at?->toDateTimeString(),
                    $order->status->label(),
                    $order->typeLabel(),
                    $order->customer_name,
                    $order->customer_phone,
                    $order->items->map(fn (OrderItem $item): string => $item->quantity.' x '.$item->name)->implode(', '),
                    number_format($order->subtotal_cents / 100, 2, '.', ''),
                    number_format($order->discount_cents / 100, 2, '.', ''),
                    $order->coupon_code,
                    number_format($order->delivery_fee_cents / 100, 2, '.', ''),
                    number_format($order->service_cents / 100, 2, '.', ''),
                    number_format($order->tax_cents / 100, 2, '.', ''),
                    number_format($order->tip_cents / 100, 2, '.', ''),
                    number_format($order->total_cents / 100, 2, '.', ''),
                    $order->currency,
                ]);
            }

            fclose($output);
        }, 'sales-'.$from->toDateString().'-to-'.$to->toDateString().'.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * The selected reporting range, defaulting to the last 30 days.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function range(Request $request): array
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $from = Carbon::parse($data['from'] ?? now()->subDays(29)->toDateString())->startOfDay();
        $to = Carbon::parse($data['to'] ?? now()->toDateString())->endOfDay();

        if ($from->diffInDays($to) > 366) {
            $from = $to->copy()->subDays(366)->startOfDay();
        }

        return [$from, $to];
    }

    /**
     * Best selling items in the range.
     *
     * @return array<int, array{name: string, quantity: int, revenue: string}>
     */
    private function topItems(Profile $profile, Carbon $from, Carbon $to): array
    {
        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.profile_id', $profile->id)
            ->whereBetween('orders.created_at', [$from, $to])
            ->where('orders.status', '!=', OrderStatus::Cancelled->value)
            ->selectRaw('order_items.name, SUM(order_items.quantity) as quantity, SUM(order_items.line_total_cents) as revenue')
            ->groupBy('order_items.name')
            ->orderByDesc('quantity')
            ->limit(8)
            ->get()
            ->map(fn (OrderItem $item): array => [
                'name' => $item->name,
                'quantity' => (int) $item->getAttribute('quantity'),
                'revenue' => $this->money((int) $item->getAttribute('revenue'), $profile),
            ])
            ->all();
    }

    /**
     * Format cents with the restaurant currency.
     */
    private function money(int $cents, Profile $profile): string
    {
        return number_format($cents / 100, 2).' '.$profile->menu_currency;
    }

    /**
     * Ensure the signed-in user owns the restaurant.
     */
    private function authorizeProfile(Request $request, Profile $profile): void
    {
        abort_unless($profile->canManage($request->user()), 403);
    }
}
