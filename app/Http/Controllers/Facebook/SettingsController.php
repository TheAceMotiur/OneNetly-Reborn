<?php

namespace App\Http\Controllers\Facebook;

use App\Http\Controllers\Controller;
use App\Services\FacebookClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SettingsController extends Controller
{
    /**
     * Save the Facebook Page connection for the account.
     */
    public function update(Request $request): RedirectResponse
    {
        $account = $this->automationAccount($request);

        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'page_name' => ['nullable', 'string', 'max:120'],
            'page_id' => ['nullable', 'string', 'max:64'],
            'access_token' => ['nullable', 'string', 'max:4000'],
            'remove_token' => ['sometimes', 'boolean'],
        ]);

        $account->update([
            'facebook_enabled' => (bool) $data['enabled'],
            'facebook_page_name' => $data['page_name'] ?? null,
            'facebook_page_id' => $data['page_id'] ?? null,
            ...(filled($data['access_token'] ?? null)
                ? ['facebook_page_access_token' => trim((string) $data['access_token'])]
                : []),
            ...($request->boolean('remove_token') ? ['facebook_page_access_token' => null] : []),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Facebook settings saved.'),
        ]);

        return back();
    }

    /**
     * Verify the saved Page credentials against the Graph API.
     */
    public function test(Request $request): RedirectResponse
    {
        $result = app(FacebookClient::class)->verify($this->automationAccount($request));

        Inertia::flash('toast', [
            'type' => $result['ok'] ? 'success' : 'error',
            'message' => $result['message'],
        ]);

        return back();
    }
}
