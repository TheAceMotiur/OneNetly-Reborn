<?php

namespace App\Services;

use App\Models\ShortLink;
use App\Support\VisitorDetector;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ShortLinkTracker
{
    /**
     * Agents that identify a phone or tablet.
     */
    private const MOBILE_AGENT_PATTERN = '/(android|iphone|ipad|ipod|mobile|opera mini|iemobile)/i';

    /**
     * Agents that identify a tablet rather than a phone.
     */
    private const TABLET_AGENT_PATTERN = '/(ipad|tablet|kindle|silk)/i';

    /**
     * Record a click for a real visitor. Every redirect is one click, so the
     * tracker never caps counting per IP: shared networks and proxies would
     * silently lose real clicks otherwise.
     */
    public function record(Request $request, ShortLink $shortLink): void
    {
        if (! VisitorDetector::isReal($request, $shortLink->user_id)) {
            return;
        }

        $referrer = $request->headers->get('referer');
        $agent = $request->userAgent();

        $shortLink->clicks()->create([
            'clicked_at' => now(),
            'visitor_hash' => $this->visitorHash($request),
            'ip_hash' => $this->ipHash($request),
            'referrer' => $referrer ? Str::limit($referrer, 512, '') : null,
            'user_agent' => $agent ? Str::limit($agent, 512, '') : null,
            'device' => $this->device($agent),
            'country' => $this->country($request),
        ]);

        $shortLink->increment('clicks', 1, ['last_clicked_at' => now()]);
    }

    /**
     * Identify the visitor by session when possible, and by address and
     * browser otherwise, so unique visitors survive a changed IP on mobile.
     */
    private function visitorHash(Request $request): string
    {
        if ($request->hasSession()) {
            $session = (string) $request->session()->getId();

            if ($session !== '') {
                return sha1('session:'.$session);
            }
        }

        return sha1('visitor:'.((string) $request->ip()).'|'.((string) $request->userAgent()));
    }

    /**
     * Hash the visitor address so the raw IP is never stored.
     */
    private function ipHash(Request $request): ?string
    {
        $ip = $request->ip();

        return $ip !== null ? sha1($ip) : null;
    }

    /**
     * Classify the visitor's device from the user agent.
     */
    private function device(?string $agent): string
    {
        $agent ??= '';

        if (preg_match(self::TABLET_AGENT_PATTERN, $agent) === 1) {
            return 'tablet';
        }

        if (preg_match(self::MOBILE_AGENT_PATTERN, $agent) === 1) {
            return 'mobile';
        }

        return 'desktop';
    }

    /**
     * Read the visitor's country from the CDN headers when one is present.
     */
    private function country(Request $request): ?string
    {
        $header = $request->header('CF-IPCountry');

        if (! is_string($header) || trim($header) === '') {
            $header = $request->header('X-Country-Code');
        }

        $country = is_string($header) ? strtoupper(trim($header)) : '';

        if ($country === '' || in_array($country, ['XX', 'T1'], true)) {
            return null;
        }

        return Str::limit($country, 8, '');
    }
}
