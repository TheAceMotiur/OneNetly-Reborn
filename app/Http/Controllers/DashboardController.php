<?php

namespace App\Http\Controllers;

use App\Models\Link;
use App\Models\Profile;
use App\Services\AnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Show the signed-in user's bio page overview.
     */
    public function __invoke(Request $request, AnalyticsService $analytics): Response
    {
        $profile = Profile::forUser($request->user())->load('links');

        /** @var Collection<int, int> $linkIds */
        $linkIds = $profile->links->pluck('id');

        $daily = $analytics->dailyClicks($linkIds, 7, 'D');
        $dailyViews = $analytics->dailyViews($profile->id, 7, 'D');
        $weekClicks = $analytics->clicksPerLink($linkIds, 7);
        $topReferrers = $analytics->topReferrers($linkIds, 30, 3);
        $viewsWeek = $analytics->totalFromDaily($dailyViews, 'views');
        $clicksWeek = $analytics->totalFromDaily($daily);

        return Inertia::render('Dashboard', [
            'profile' => [
                'username' => $profile->username,
                'display_name' => $profile->display_name ?: $request->user()->name,
                'bio' => $profile->bio,
                'avatar_url' => $profile->avatar_url,
                'theme' => $profile->theme,
                'template' => $profile->template,
                'is_published' => $profile->is_published,
                'public_url' => route('bio.show', $profile->username),
            ],
            'stats' => [
                'total' => $profile->links->count(),
                'active' => $profile->links->filter->isLive()->count(),
                'clicks' => (int) $profile->links->sum('clicks'),
                'clicksWeek' => $clicksWeek,
                'views' => $profile->views()->count(),
                'viewsWeek' => $viewsWeek,
                'clickRate' => $viewsWeek > 0 ? (int) round($clicksWeek / $viewsWeek * 100) : 0,
            ],
            'daily' => $daily,
            'topReferrers' => $topReferrers,
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
            'qrUrl' => route('bio.qr'),
        ]);
    }
}
