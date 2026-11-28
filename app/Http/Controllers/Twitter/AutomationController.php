<?php

namespace App\Http\Controllers\Twitter;

use App\Http\Controllers\Controller;
use App\Models\TwitterAutomation;
use App\Services\TwitterClient;
use App\Services\TwitterNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AutomationController extends Controller
{
    /**
     * Update the mention auto-reply.
     */
    public function update(Request $request): RedirectResponse
    {
        $profile = $this->automationAccount($request)->workspaceProfile();

        $data = $request->validate([
            'automations' => ['required', 'array'],
            'automations.*.key' => ['required', 'string', Rule::in(array_keys(TwitterAutomation::DEFAULTS))],
            'automations.*.is_enabled' => ['required', 'boolean'],
            'automations.*.message' => ['nullable', 'string', 'max:280'],
        ]);

        foreach ($data['automations'] as $automation) {
            $profile->twitterAutomations()->updateOrCreate(
                ['key' => $automation['key']],
                [
                    'is_enabled' => (bool) $automation['is_enabled'],
                    'message' => $automation['message'] ?? null,
                ],
            );
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Twitter automations updated.'),
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
            'message' => ['required', 'string', 'max:280'],
            'is_enabled' => ['sometimes', 'boolean'],
        ]);

        $profile->twitterAutomations()->create([
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
    public function updateKeyword(Request $request, TwitterAutomation $automation): RedirectResponse
    {
        $profile = $this->automationAccount($request)->workspaceProfile();

        abort_unless($automation->profile_id === $profile->id && $automation->key === 'keyword', 404);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:80'],
            'keywords' => ['sometimes', 'required', 'string', 'max:500'],
            'message' => ['sometimes', 'required', 'string', 'max:280'],
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
    public function destroyKeyword(Request $request, TwitterAutomation $automation): RedirectResponse
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

    /**
     * Poll X for new mentions now.
     */
    public function sync(Request $request, TwitterNotifier $notifier): RedirectResponse
    {
        $profile = $this->automationAccount($request)->workspaceProfile();

        if (! app(TwitterClient::class)->isConfigured($profile->user)) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Connect your X account before syncing mentions.'),
            ]);

            return back();
        }

        $count = $notifier->sync($profile);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $count === 1
                ? __('Found new mentions and sent 1 reply.')
                : __('Synced mentions. :count replies sent.', ['count' => $count]),
        ]);

        return back();
    }
}
