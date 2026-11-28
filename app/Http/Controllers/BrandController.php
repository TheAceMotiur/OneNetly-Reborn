<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\Profile;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BrandController extends Controller
{
    /**
     * Compare every restaurant the user can access.
     */
    public function __invoke(Request $request): Response
    {
        $restaurants = $request->user()->accessibleRestaurants();
        $since = now()->subDays(29)->startOfDay();

        return Inertia::render('brand/Index', [
            'locations' => $restaurants->map(function (Profile $profile) use ($since): array {
                $orders = $profile->orders()->where('created_at', '>=', $since);

                return [
                    'username' => $profile->username,
                    'name' => $profile->restaurantName(),
                    'orders' => (clone $orders)->count(),
                    'revenue_cents' => (int) (clone $orders)->sum('total_cents'),
                    'new_orders' => $profile->orders()->where('status', OrderStatus::New)->count(),
                    'reservations' => $profile->reservations()
                        ->where('status', '!=', 'cancelled')
                        ->whereDate('reservation_date', '>=', today())
                        ->count(),
                    'customers' => $profile->loyaltyAccounts()->count(),
                    'currency' => $profile->menu_currency,
                    'menu_url' => route('restaurant.menu', $profile),
                    'orders_url' => route('restaurant.orders', $profile),
                    'reports_url' => route('restaurant.reports', $profile),
                ];
            })->all(),
            'totals' => [
                'locations' => $restaurants->count(),
                'orders' => $restaurants->sum(fn (Profile $profile): int => $profile->orders()
                    ->where('created_at', '>=', $since)->count()),
                'revenue_cents' => (int) $restaurants->sum(fn (Profile $profile): int => (int) $profile->orders()
                    ->where('created_at', '>=', $since)->sum('total_cents')),
                'customers' => $restaurants->sum(fn (Profile $profile): int => $profile->loyaltyAccounts()->count()),
            ],
        ]);
    }
}
