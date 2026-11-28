<?php

namespace App\Services;

use App\Models\LinkClick;
use App\Models\ProfileView;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AnalyticsService
{
    /**
     * Build a click series for the given number of days.
     *
     * @param  Collection<int, int>|null  $linkIds  null means every link on the platform
     * @return array<int, array{date: string, label: string, clicks: int}>
     */
    public function dailyClicks(?Collection $linkIds, int $days, string $labelFormat = 'M j'): array
    {
        /** @var Collection<string, int> $counts */
        $counts = $this->query($linkIds)
            ->where('clicked_at', '>=', now()->subDays($days - 1)->startOfDay())
            ->selectRaw('DATE(clicked_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        return collect(range($days - 1, 0))
            ->map(function (int $daysAgo) use ($counts, $labelFormat): array {
                $date = now()->subDays($daysAgo);

                return [
                    'date' => $date->toDateString(),
                    'label' => $date->format($labelFormat),
                    'clicks' => (int) ($counts[$date->toDateString()] ?? 0),
                ];
            })
            ->all();
    }

    /**
     * Build a page-view series for one profile over the given number of days.
     *
     * @return array<int, array{date: string, label: string, views: int}>
     */
    public function dailyViews(int $profileId, int $days, string $labelFormat = 'M j'): array
    {
        /** @var Collection<string, int> $counts */
        $counts = ProfileView::query()
            ->where('profile_id', $profileId)
            ->where('viewed_at', '>=', now()->subDays($days - 1)->startOfDay())
            ->selectRaw('DATE(viewed_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        return collect(range($days - 1, 0))
            ->map(function (int $daysAgo) use ($counts, $labelFormat): array {
                $date = now()->subDays($daysAgo);

                return [
                    'date' => $date->toDateString(),
                    'label' => $date->format($labelFormat),
                    'views' => (int) ($counts[$date->toDateString()] ?? 0),
                ];
            })
            ->all();
    }

    /**
     * Count page views over the given number of days.
     *
     * @param  int|null  $profileId  null means every profile on the platform
     */
    public function totalViews(?int $profileId, int $days): int
    {
        return ProfileView::query()
            ->when($profileId !== null, fn ($query) => $query->where('profile_id', $profileId))
            ->where('viewed_at', '>=', now()->subDays($days - 1)->startOfDay())
            ->count();
    }

    /**
     * Count clicks per link over the given number of days.
     *
     * @param  Collection<int, int>  $linkIds
     * @return Collection<int, int>
     */
    public function clicksPerLink(Collection $linkIds, int $days): Collection
    {
        return $this->query($linkIds)
            ->where('clicked_at', '>=', now()->subDays($days - 1)->startOfDay())
            ->selectRaw('link_id, COUNT(*) as total')
            ->groupBy('link_id')
            ->pluck('total', 'link_id');
    }

    /**
     * Group clicks by referrer host for the given number of days.
     *
     * @param  Collection<int, int>|null  $linkIds
     * @return array<int, array{host: string, clicks: int}>
     */
    public function topReferrers(?Collection $linkIds, int $days, int $limit = 5): array
    {
        return $this->query($linkIds)
            ->whereNotNull('referrer')
            ->where('referrer', '!=', '')
            ->where('clicked_at', '>=', now()->subDays($days - 1)->startOfDay())
            ->selectRaw('referrer, COUNT(*) as total')
            ->groupBy('referrer')
            ->pluck('total', 'referrer')
            ->groupBy(fn (int $total, string $referrer): string => parse_url($referrer, PHP_URL_HOST) ?: $referrer)
            ->map(fn (Collection $totals): int => (int) $totals->sum())
            ->sortDesc()
            ->take($limit)
            ->map(fn (int $total, string $host): array => ['host' => $host, 'clicks' => $total])
            ->values()
            ->all();
    }

    /**
     * Sum a metric from a daily series.
     *
     * @param  array<int, array<string, int|string>>  $daily
     */
    public function totalFromDaily(array $daily, string $key = 'clicks'): int
    {
        return (int) array_sum(array_column($daily, $key));
    }

    /**
     * Start a click query, optionally scoped to specific links.
     *
     * @param  Collection<int, int>|null  $linkIds
     * @return Builder<LinkClick>
     */
    private function query(?Collection $linkIds): Builder
    {
        $query = LinkClick::query();

        if ($linkIds !== null) {
            $query->whereIn('link_id', $linkIds);
        }

        return $query;
    }
}
