<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Bio\BioPageController;
use App\Models\Profile;
use Closure;
use Illuminate\Http\Request;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

class ResolveCustomDomainProfile
{
    /**
     * Serve a published bio page when the request arrives on its custom domain.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): InertiaResponse|Response
    {
        if (! setting('custom_domains_enabled')) {
            return $next($request);
        }

        $host = strtolower($request->getHost());
        $appHost = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));

        if ($host === '' || $host === $appHost || in_array($host, ['localhost', '127.0.0.1', '[::1]'], true)) {
            return $next($request);
        }

        if ($request->path() !== '/') {
            return $next($request);
        }

        $profile = Profile::query()
            ->where('custom_domain', $host)
            ->whereNotNull('custom_domain_verified_at')
            ->where('is_published', true)
            ->first();

        if ($profile === null) {
            return $next($request);
        }

        return app(BioPageController::class)->show($request, $profile->username);
    }
}
