<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Apply the user's saved language, or a supported ?lang= override.
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var array<string, string> $supported */
        $supported = (array) config('app.supported_locales', ['en' => 'English']);

        $requested = $request->query('lang');

        if (is_string($requested) && array_key_exists($requested, $supported)) {
            $request->session()->put('locale', $requested);
        }

        $locale = $request->user()?->locale
            ?: $request->session()->get('locale')
            ?: (string) config('app.locale');

        if (is_string($locale) && array_key_exists($locale, $supported)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
