<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use Inertia\Inertia;
use Inertia\Response;

class PricingController extends Controller
{
    /** Show the pricing page with available plans. */
    public function index(): Response
    {
        $plans = Plan::query()
            ->where('is_active', true)
            ->orderBy('position')
            ->get();

        $user = request()->user();
        $subscribedTools = $user?->availableTools() ?? [];
        $hasBundle = $user?->activePlans()->contains(fn (Plan $plan): bool => $plan->isBundlePlan()) ?? false;

        return Inertia::render('pricing/Index', [
            'plans' => $plans->map(fn (Plan $plan): array => [
                'id' => $plan->id,
                'name' => $plan->name,
                'type' => $plan->typeDisplayName(),
                'plan_type' => $plan->plan_type,
                'tool' => $plan->tool,
                'tool_label' => $plan->toolLabel(),
                'is_tool' => $plan->isToolPlan(),
                'is_bundle' => $plan->isBundlePlan(),
                'is_subscribed' => $plan->isToolPlan()
                    ? in_array($plan->tool, $subscribedTools, true)
                    : $plan->isBundlePlan() && $hasBundle,
                'price' => $plan->displayPrice(),
                'numeric_price' => (float) $plan->price,
                'currency' => $plan->currency,
                'features' => $plan->featureList(),
                'is_free' => (float) $plan->price <= 0,
                'has_custom_domain' => $plan->has_custom_domain,
                'has_analytics' => $plan->has_analytics,
                'max_links' => $plan->max_links,
                'duration_days' => $plan->duration_days,
                'duration_label' => $plan->durationLabel(),
            ])->all(),
            'paypalConfigured' => filled(setting('paypal_me_username')) || filled(config('services.paypal.me_username')),
            'paymentsEnabled' => (bool) setting('payments_enabled'),
            'currentPlanId' => $user?->currentPlan()?->id,
            'subscribedTools' => $subscribedTools,
            'hasBundle' => $hasBundle,
        ]);
    }
}
