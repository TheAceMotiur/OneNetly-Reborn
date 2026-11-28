<?php

namespace App\Services;

use App\Models\Profile;
use App\Support\VisitorDetector;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class ProfileViewTracker
{
    /**
     * How long a repeated load from the same visitor counts as one view, so a
     * refresh storm does not inflate the numbers while every other visitor is
     * still counted. Tracking is never capped per IP or per page, because
     * shared networks and proxies would otherwise lose real views.
     */
    private const DUPLICATE_WINDOW_SECONDS = 30;

    /**
     * Record a view of the given bio page for a real visitor.
     */
    public function record(Request $request, Profile $profile): void
    {
        if (! VisitorDetector::isReal($request, $profile->user_id)) {
            return;
        }

        $key = 'profile-view:'.$profile->id.':'.$this->visitorKey($request);

        if (RateLimiter::tooManyAttempts($key, 1)) {
            return;
        }

        RateLimiter::hit($key, self::DUPLICATE_WINDOW_SECONDS);

        $referrer = $request->headers->get('referer');
        $agent = $request->userAgent();

        $profile->views()->create([
            'viewed_at' => now(),
            'referrer' => $referrer ? Str::limit($referrer, 512, '') : null,
            'user_agent' => $agent ? Str::limit($agent, 512, '') : null,
        ]);
    }

    /**
     * Identify the visitor by session when possible, and by address and
     * browser otherwise, so repeat loads inside the window are collapsed
     * without merging separate visitors on the same network.
     */
    private function visitorKey(Request $request): string
    {
        if ($request->hasSession()) {
            $session = (string) $request->session()->getId();

            if ($session !== '') {
                return 'session:'.sha1($session);
            }
        }

        return 'visitor:'.sha1(((string) $request->ip()).'|'.((string) $request->userAgent()));
    }
}
