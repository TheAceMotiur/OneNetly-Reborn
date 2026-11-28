<?php

namespace App\Http\Controllers;

use App\Models\Link;
use App\Models\Profile;
use App\Services\AnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class AnalyticsController extends Controller
{
    /**
     * Show the click analytics of the signed-in user.
     */
    public function __invoke(Request $request, AnalyticsService $analytics): Response
    {
        $user = $request->user();
        $profile = Profile::forUser($user)->load('links');

        /** @var Collection<int, int> $linkIds */
        $linkIds = $profile->links->pluck('id');

        $analyticsEnabled = (bool) setting('analytics_enabled');

        $daily = $analytics->dailyClicks($linkIds, 30);
        $weekClicks = $analytics->clicksPerLink($linkIds, 7);
        $clicksLast30 = $analytics->totalFromDaily($daily);
        $viewsLast30 = $analytics->totalViews($profile->id, 30);

        return Inertia::render('analytics/Index', [
            'analyticsEnabled' => $analyticsEnabled,
            'canViewAnalytics' => $analyticsEnabled && $user->hasPlanFeature('has_analytics'),
            'stats' => [
                'clicksAllTime' => (int) $profile->links->sum('clicks'),
                'clicksLast30' => $clicksLast30,
                'clicksThisWeek' => (int) $weekClicks->sum(),
                'links' => $profile->links->count(),
                'averagePerDay' => (int) round($clicksLast30 / 30),
                'viewsLast30' => $viewsLast30,
                'viewsAll' => $profile->views()->count(),
                'clickRate' => $viewsLast30 > 0 ? (int) round($clicksLast30 / $viewsLast30 * 100) : 0,
            ],
            'daily' => $daily,
            'topLinks' => $profile->links
                ->sortByDesc('clicks')
                ->take(5)
                ->map(fn (Link $link): array => [
                    'id' => $link->id,
                    'title' => $link->title,
                    'clicks' => $link->clicks,
                    'clicks_week' => (int) ($weekClicks[$link->id] ?? 0),
                    'is_active' => $link->is_active,
                ])
                ->values()
                ->all(),
            'topReferrers' => $analytics->topReferrers($linkIds, 30),
        ]);
    }
}
