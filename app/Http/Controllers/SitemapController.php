<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    /**
     * List every published page for search engines.
     */
    public function sitemap(): Response
    {
        $urls = Profile::query()
            ->where('is_published', true)
            ->orderBy('updated_at', 'desc')
            ->get(['username', 'business_type', 'updated_at'])
            ->map(function (Profile $profile): array {
                $restaurant = $profile->business_type === Profile::RESTAURANT_TYPE;

                return [
                    'loc' => $restaurant
                        ? route('restaurant.show', $profile->username)
                        : route('bio.show', $profile->username),
                    'lastmod' => $profile->updated_at?->toAtomString(),
                    'priority' => $restaurant ? '0.8' : '0.7',
                ];
            });

        $xml = view('sitemap', ['urls' => $urls])->render();

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }

    /**
     * Point crawlers at the sitemap.
     */
    public function robots(): Response
    {
        return response(
            "User-agent: *\nAllow: /\n\nSitemap: ".route('sitemap')."\n",
            200,
            ['Content-Type' => 'text/plain'],
        );
    }
}
