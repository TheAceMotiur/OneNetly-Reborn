<?php

namespace App\Http\Controllers\Instagram;

use App\Http\Controllers\Controller;
use App\Models\InstagramAutomation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AutomationController extends Controller
{
    /**
     * Update the welcome and comment automations in one submit.
     */
    public function update(Request $request): RedirectResponse
    {
        $profile = $this->automationAccount($request)->workspaceProfile();

        $data = $request->validate([
            'automations' => ['required', 'array'],
            'automations.*.key' => ['required', 'string', Rule::in(array_keys(InstagramAutomation::DEFAULTS))],
            'automations.*.is_enabled' => ['required', 'boolean'],
            'automations.*.message' => ['nullable', 'string', 'max:1000'],
            'automations.*.keywords' => ['nullable', 'string', 'max:500'],
        ]);

        foreach ($data['automations'] as $automation) {
            $profile->instagramAutomations()->updateOrCreate(
                ['key' => $automation['key']],
                [
                    'is_enabled' => (bool) $automation['is_enabled'],
                    'message' => $automation['message'] ?? null,
                    'keywords' => $automation['keywords'] ?? null,
                ],
            );
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Instagram automations updated.'),
        ]);

        return back();
    }

    /**
     * Add a keyword auto-reply.
     */
    public function storeKeyword(Request $request): RedirectResponse
    {
        $profile = $this->automationAccount($request)->workspaceProfile();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'keywords' => ['required', 'string', 'max:500'],
            'message' => ['required', 'string', 'max:1000'],
            'is_enabled' => ['sometimes', 'boolean'],
        ]);

        $profile->instagramAutomations()->create([
            'key' => 'keyword',
            'name' => $data['name'],
            'keywords' => $data['keywords'],
            'message' => $data['message'],
            'is_enabled' => (bool) ($data['is_enabled'] ?? true),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Keyword reply added.'),
        ]);

        return back();
    }

    /**
     * Update a keyword auto-reply.
     */
    public function updateKeyword(Request $request, InstagramAutomation $automation): RedirectResponse
    {
        $profile = $this->automationAccount($request)->workspaceProfile();

        abort_unless($automation->profile_id === $profile->id && $automation->key === 'keyword', 404);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:80'],
            'keywords' => ['sometimes', 'required', 'string', 'max:500'],
            'message' => ['sometimes', 'required', 'string', 'max:1000'],
            'is_enabled' => ['sometimes', 'boolean'],
        ]);

        $automation->update($data);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Keyword reply updated.'),
        ]);

        return back();
    }

    /**
     * Delete a keyword auto-reply.
     */
    public function destroyKeyword(Request $request, InstagramAutomation $automation): RedirectResponse
    {
        $profile = $this->automationAccount($request)->workspaceProfile();

        abort_unless($automation->profile_id === $profile->id && $automation->key === 'keyword', 404);

        $automation->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Keyword reply removed.'),
        ]);

        return back();
    }
}
