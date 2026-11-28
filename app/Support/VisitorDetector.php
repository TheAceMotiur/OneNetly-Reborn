<?php

namespace App\Support;

use Illuminate\Http\Request;

final class VisitorDetector
{
    /**
     * User agents that never represent a real visitor.
     */
    private const AUTOMATED_AGENT_PATTERN = '/bot|crawl|spider|preview|facebookexternalhit|slack-image-proxy|discordbot|telegrambot|whatsapp|curl|wget|python-requests|headless|lighthouse/i';

    /**
     * Determine whether the request comes from a real visitor rather than the owner or a bot.
     */
    public static function isReal(Request $request, int $ownerUserId): bool
    {
        if ($request->user()?->id === $ownerUserId) {
            return false;
        }

        if ($request->headers->has('Purpose')
            || $request->headers->has('X-Purpose')
            || $request->headers->has('Sec-Purpose')) {
            return false;
        }

        $agent = (string) $request->userAgent();

        return $agent !== '' && preg_match(self::AUTOMATED_AGENT_PATTERN, $agent) !== 1;
    }
}
