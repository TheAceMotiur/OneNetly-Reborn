<?php

namespace App\Http\Middleware;

use App\Models\Plan;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class EnsureToolSubscription
{
    /**
     * Only let subscribers of the given tool open its workspace.
     */
    public function handle(Request $request, Closure $next, string $tool): Response
    {
        $user = $request->user();

        abort_unless($user !== null, 403);

        if (! $user->hasToolAccess($tool)) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Subscribe to :tool to use it.', [
                    'tool' => Plan::TOOLS[$tool] ?? ucfirst($tool),
                ]),
            ]);

            return to_route('pricing.index');
        }

        return $next($request);
    }
}
