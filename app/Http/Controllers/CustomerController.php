<?php

namespace App\Http\Controllers;

use App\Models\LoyaltyAccount;
use App\Models\Profile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerController extends Controller
{
    /**
     * Show the guest directory for one restaurant.
     */
    public function index(Request $request, Profile $profile): Response
    {
        abort_unless($profile->canManage($request->user()), 403);

        $search = trim((string) $request->query('q', ''));

        $customers = $profile->loyaltyAccounts()
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%');
            }))
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('customers/Index', [
            'customers' => $customers->through(fn (LoyaltyAccount $account): array => [
                'id' => $account->id,
                'name' => $account->name,
                'phone' => $account->phone,
                'email' => $account->email,
                'points' => $account->points,
                'lifetime_points' => $account->lifetime_points,
                'visits' => $account->visits,
                'total_spent' => number_format($account->total_spent_cents / 100, 2).' '.$profile->menu_currency,
                'notes' => $account->notes,
                'last_order_human' => $account->last_order_at?->diffForHumans(),
            ]),
            'stats' => [
                'customers' => $profile->loyaltyAccounts()->count(),
                'repeat' => $profile->loyaltyAccounts()->where('visits', '>', 1)->count(),
                'points' => (int) $profile->loyaltyAccounts()->sum('points'),
                'spent' => number_format((int) $profile->loyaltyAccounts()->sum('total_spent_cents') / 100, 2).' '.$profile->menu_currency,
            ],
            'search' => $search,
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
            'exportUrl' => route('restaurant.customers.export', $profile),
        ]);
    }

    /**
     * Save an internal note about a guest.
     */
    public function update(Request $request, Profile $profile, LoyaltyAccount $customer): RedirectResponse
    {
        abort_unless($profile->canManage($request->user()), 403);
        abort_unless($customer->profile_id === $profile->id, 404);

        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $customer->update($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Customer note saved.')]);

        return back();
    }

    /**
     * Download the guest directory as CSV.
     */
    public function export(Request $request, Profile $profile): StreamedResponse
    {
        abort_unless($profile->canManage($request->user()), 403);

        return HttpResponse::streamDownload(function () use ($profile): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, ['Name', 'Phone', 'Email', 'Points', 'Lifetime points', 'Visits', 'Total spent', 'Last order', 'Notes']);

            $profile->loyaltyAccounts()->chunk(200, function ($accounts) use ($handle): void {
                foreach ($accounts as $account) {
                    fputcsv($handle, [
                        $account->name,
                        $account->phone,
                        $account->email,
                        $account->points,
                        $account->lifetime_points,
                        $account->visits,
                        number_format($account->total_spent_cents / 100, 2),
                        $account->last_order_at?->toDateTimeString(),
                        $account->notes,
                    ]);
                }
            });

            fclose($handle);
        }, 'customers-'.$profile->username.'.csv');
    }
}
