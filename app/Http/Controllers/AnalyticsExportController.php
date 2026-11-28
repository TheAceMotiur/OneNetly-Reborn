<?php

namespace App\Http\Controllers;

use App\Models\LinkClick;
use App\Models\Profile;
use App\Models\ProfileView;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnalyticsExportController extends Controller
{
    /**
     * Download every click and page view for the signed-in user as CSV.
     */
    public function __invoke(Request $request): StreamedResponse
    {
        $user = $request->user();

        abort_unless($user->hasPlanFeature('has_analytics'), 403);

        $profile = Profile::forUser($user)->load('links');

        $siteName = Str::slug((string) setting('site_name', config('app.name'))) ?: 'analytics';
        $filename = sprintf('%s-%s-analytics-%s.csv', $siteName, $profile->username, now()->toDateString());

        return response()->streamDownload(function () use ($profile): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, ['Type', 'Date', 'Link', 'Destination', 'Referrer']);

            LinkClick::query()
                ->with('link:id,title,url')
                ->whereIn('link_id', $profile->links->pluck('id'))
                ->orderBy('clicked_at')
                ->chunk(500, function ($clicks) use ($handle): void {
                    foreach ($clicks as $click) {
                        fputcsv($handle, [
                            'click',
                            $click->clicked_at->toDateTimeString(),
                            $this->safe($click->link?->title),
                            $this->safe($click->link?->url),
                            $this->safe($click->referrer),
                        ]);
                    }
                });

            ProfileView::query()
                ->where('profile_id', $profile->id)
                ->orderBy('viewed_at')
                ->chunk(500, function ($views) use ($handle): void {
                    foreach ($views as $view) {
                        fputcsv($handle, [
                            'view',
                            $view->viewed_at->toDateTimeString(),
                            '',
                            '',
                            $this->safe($view->referrer),
                        ]);
                    }
                });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Prevent spreadsheet formula injection in exported values.
     */
    private function safe(?string $value): string
    {
        $value = (string) $value;

        return preg_match('/^[=+\-@]/', $value) === 1 ? "'".$value : $value;
    }
}
