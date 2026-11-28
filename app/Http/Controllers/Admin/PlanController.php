<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PlanController extends Controller
{
    /**
     * Show every plan so administrators can manage them.
     */
    public function index(): Response
    {
        return Inertia::render('admin/plans/Index', [
            'plans' => Plan::query()
                ->withCount('purchases')
                ->orderBy('position')
                ->get()
                ->map(fn (Plan $plan): array => [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'plan_type' => $plan->plan_type,
                    'tool' => $plan->tool,
                    'price' => $plan->price,
                    'currency' => $plan->currency,
                    'features' => $plan->featureList(),
                    'is_active' => $plan->is_active,
                    'has_custom_domain' => $plan->has_custom_domain,
                    'has_analytics' => $plan->has_analytics,
                    'has_white_label' => $plan->has_white_label,
                    'position' => $plan->position,
                    'max_links' => $plan->max_links,
                    'max_restaurants' => $plan->max_restaurants,
                    'duration_days' => $plan->duration_days,
                    'purchases_count' => $plan->purchases_count,
                ])
                ->all(),
            'planTypes' => Plan::PLAN_TYPES,
            'tools' => Plan::TOOLS,
        ]);
    }

    /**
     * Create a new plan.
     */
    public function store(Request $request): RedirectResponse
    {
        Plan::create($this->validated($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Plan created.')]);

        return back();
    }

    /**
     * Update an existing plan.
     */
    public function update(Request $request, Plan $plan): RedirectResponse
    {
        $plan->update($this->validated($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Plan updated.')]);

        return back();
    }

    /**
     * Delete a plan that has never been purchased.
     */
    public function destroy(Plan $plan): RedirectResponse
    {
        if ($plan->purchases()->exists()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('This plan has purchases and cannot be deleted. Deactivate it instead.'),
            ]);

            return back();
        }

        $plan->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Plan deleted.')]);

        return back();
    }

    /**
     * Validate the plan payload.
     *
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:64'],
            'plan_type' => ['required', 'string', Rule::in(Plan::PLAN_TYPES)],
            'tool' => [
                'nullable',
                'string',
                Rule::in(array_keys(Plan::TOOLS)),
                Rule::requiredIf(fn (): bool => $request->input('plan_type') === 'tool'),
            ],
            'price' => ['required', 'numeric', 'min:0', 'max:99999'],
            'currency' => ['required', 'string', 'size:3'],
            'features' => ['nullable', 'array'],
            'features.*' => ['string', 'max:100'],
            'is_active' => ['boolean'],
            'has_custom_domain' => ['boolean'],
            'has_analytics' => ['boolean'],
            'has_white_label' => ['boolean'],
            'position' => ['integer', 'min:0', 'max:1000'],
            'max_links' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'max_restaurants' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'duration_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
        ]);

        $data['currency'] = strtoupper($data['currency']);
        $data['tool'] = $data['plan_type'] === 'tool' ? ($data['tool'] ?? null) : null;
        $data['features'] = array_values(array_filter($data['features'] ?? []));
        $data['max_links'] = $data['max_links'] ?? null;
        $data['max_restaurants'] = $data['max_restaurants'] ?? null;
        $data['duration_days'] = $data['duration_days'] ?? null;

        return $data;
    }
}
