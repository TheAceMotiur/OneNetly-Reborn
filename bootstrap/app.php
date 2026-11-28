<?php

use App\Http\Middleware\AuthenticatePosToken;
use App\Http\Middleware\EnsureToolSubscription;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ResolveCustomDomainProfile;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/pos.php',
        apiPrefix: 'api/pos',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->validateCsrfTokens(except: ['webhooks/textbee', 'webhooks/whatsapp', 'webhooks/facebook', 'webhooks/instagram']);

        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'tool' => EnsureToolSubscription::class,
            'pos.token' => AuthenticatePosToken::class,
        ]);

        $middleware->web(append: [
            SecurityHeaders::class,
            ResolveCustomDomainProfile::class,
            SetLocale::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->respond(function (SymfonyResponse $response, Throwable $exception): SymfonyResponse {
            $request = request();

            if ($request->is('api/*') || $request->expectsJson()) {
                return $response;
            }

            $status = $response->getStatusCode();

            if (! in_array($status, [403, 404, 419, 429, 500, 503], true)) {
                return $response;
            }

            if ($status >= 500 && config('app.debug')) {
                return $response;
            }

            return Inertia::render('error/Index', ['status' => $status])
                ->toResponse($request)
                ->setStatusCode($status);
        });
    })->create();
