<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => setting('site_name', config('app.name')),
            'settings' => [
                'support_email' => setting('support_email'),
                'footer_text' => setting('footer_text'),
                'payment_note' => setting('payment_note'),
            ],
            'auth' => [
                'user' => $request->user(),
            ],
            'subscribedTools' => fn (): array => $request->user()?->availableTools() ?? [],
            'locale' => app()->getLocale(),
            'locales' => (array) config('app.supported_locales', ['en' => 'English']),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
