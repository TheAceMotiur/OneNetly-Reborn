<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use App\Models\ProfileView;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;

class LeaderboardController extends Controller
{
    /**
     * The number of pages shown on the leaderboard.
     */
    private const LIMIT = 20;

    /**
     * The number of days a page's views are counted over.
     */
    private const WINDOW_DAYS = 7;

    /**
     * Rank published bio pages by the page views they received over the last
     * week, with the change against the week before it.
     */
    public function __invoke(): Response
    {
        // Match the calendar window used by the dashboard and analytics, so a
        // page's "views this week" reads the same everywhere.
        $since = now()->subDays(self::WINDOW_DAYS - 1)->startOfDay();
        $previousSince = $since->copy()->subDays(self::WINDOW_DAYS);

        $profiles = Profile::query()
            ->with('user:id,name')
            ->where('is_published', true)
            ->whereNull('access_password')
            ->whereHas('views', fn (Builder $query): Builder => $query->where('viewed_at', '>=', $since))
            ->withCount([
                'views as recent_views' => fn (Builder $query): Builder => $query->where('viewed_at', '>=', $since),
                'views as previous_views' => fn (Builder $query): Builder => $query
                    ->where('viewed_at', '>=', $previousSince)
                    ->where('viewed_at', '<', $since),
            ])
            ->orderByDesc('recent_views')
            ->orderBy('username')
            ->limit(self::LIMIT)
            ->get();

        $entries = $profiles
            ->values()
            ->map(fn (Profile $profile, int $index): array => [
                'rank' => $index + 1,
                'username' => $profile->username,
                'display_name' => $profile->display_name ?: (string) ($profile->user?->name ?? $profile->username),
                'avatar_url' => $profile->avatar_url,
                'url' => route('bio.show', $profile->username),
                'views' => (int) $profile->recent_views,
                'previous_views' => (int) $profile->previous_views,
                'change' => $this->change((int) $profile->recent_views, (int) $profile->previous_views),
            ])
            ->all();

        return Inertia::render('leaderboard/Index', [
            'entries' => $entries,
            'totals' => [
                'views' => $this->totalViews($since),
                'pages' => Profile::query()
                    ->where('is_published', true)
                    ->whereNull('access_password')
                    ->whereHas('views', fn (Builder $query): Builder => $query->where('viewed_at', '>=', $since))
                    ->count(),
                'range' => $since->format('M j').' – '.now()->format('M j, Y'),
            ],
        ]);
    }

    /**
     * The percentage change against the week before, or null for new entries.
     */
    private function change(int $views, int $previous): ?int
    {
        if ($previous <= 0) {
            return null;
        }

        return (int) round((($views - $previous) / $previous) * 100);
    }

    /**
     * Count every view of a leaderboard-eligible page in the window.
     */
    private function totalViews(CarbonInterface $since): int
    {
        return ProfileView::query()
            ->join('profiles', 'profiles.id', '=', 'profile_views.profile_id')
            ->where('profiles.is_published', true)
            ->whereNull('profiles.access_password')
            ->where('profile_views.viewed_at', '>=', $since)
            ->count('profile_views.id');
    }
}
