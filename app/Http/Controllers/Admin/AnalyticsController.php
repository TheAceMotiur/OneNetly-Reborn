<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PurchaseStatus;
use App\Http\Controllers\Controller;
use App\Models\Link;
use App\Models\Profile;
use App\Models\ProfileView;
use App\Models\Purchase;
use App\Models\User;
use App\Services\AnalyticsService;
use Inertia\Inertia;
use Inertia\Response;

class AnalyticsController extends Controller
{
    /**
     * Show platform-wide analytics.
     */
    public function __invoke(AnalyticsService $analytics): Response
    {
        $daily = $analytics->dailyClicks(null, 30);

        $revenue = Purchase::query()
            ->where('status', PurchaseStatus::Completed)
            ->sum('amount');

        return Inertia::render('admin/analytics/Index', [
            'stats' => [
                'users' => User::count(),
                'profiles' => Profile::count(),
                'publishedProfiles' => Profile::where('is_published', true)->count(),
                'links' => Link::count(),
                'customDomains' => Profile::whereNotNull('custom_domain')->count(),
                'clicksAllTime' => (int) Link::sum('clicks'),
                'clicksLast30' => $analytics->totalFromDaily($daily),
                'viewsAllTime' => ProfileView::count(),
                'viewsLast30' => $analytics->totalViews(null, 30),
                'pendingPurchases' => Purchase::where('status', PurchaseStatus::Pending)->count(),
                'completedPurchases' => Purchase::where('status', PurchaseStatus::Completed)->count(),
                'revenue' => number_format((float) $revenue, 2).' USD',
            ],
            'daily' => $daily,
            'topProfiles' => Profile::query()
                ->with('user:id,name')
                ->withCount('links')
                ->withSum('links', 'clicks')
                ->orderByDesc('links_sum_clicks')
                ->take(8)
                ->get()
                ->map(fn (Profile $profile): array => [
                    'id' => $profile->id,
                    'username' => $profile->username,
                    'owner' => $profile->user->name,
                    'custom_domain' => $profile->custom_domain,
                    'links_count' => $profile->links_count,
                    'clicks' => (int) ($profile->links_sum_clicks ?? 0),
                ])
                ->all(),
            'topLinks' => Link::query()
                ->with('profile:id,username')
                ->orderByDesc('clicks')
                ->take(8)
                ->get()
                ->map(fn (Link $link): array => [
                    'id' => $link->id,
                    'title' => $link->title,
                    'username' => $link->profile->username,
                    'clicks' => $link->clicks,
                ])
                ->all(),
            'topReferrers' => $analytics->topReferrers(null, 30),
        ]);
    }
}
