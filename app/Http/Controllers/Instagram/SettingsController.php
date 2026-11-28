<?php

namespace App\Http\Controllers\Instagram;

use App\Http\Controllers\Controller;
use App\Services\InstagramClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SettingsController extends Controller
{
    /**
     * Save the Instagram connection for the account.
     */
    public function update(Request $request): RedirectResponse
    {
        $account = $this->automationAccount($request);

        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'username' => ['nullable', 'string', 'max:120'],
            'account_id' => ['nullable', 'string', 'max:64'],
            'access_token' => ['nullable', 'string', 'max:4000'],
            'remove_token' => ['sometimes', 'boolean'],
        ]);

        $account->update([
            'instagram_enabled' => (bool) $data['enabled'],
            'instagram_username' => ltrim((string) ($data['username'] ?? ''), '@') ?: null,
            'instagram_account_id' => $data['account_id'] ?? null,
            ...(filled($data['access_token'] ?? null)
                ? ['instagram_access_token' => trim((string) $data['access_token'])]
                : []),
            ...($request->boolean('remove_token') ? ['instagram_access_token' => null] : []),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Instagram settings saved.'),
        ]);

        return back();
    }

    /**
     * Verify the saved Instagram credentials against the Graph API.
     */
    public function test(Request $request): RedirectResponse
    {
        $result = app(InstagramClient::class)->verify($this->automationAccount($request));

        Inertia::flash('toast', [
            'type' => $result['ok'] ? 'success' : 'error',
            'message' => $result['message'],
        ]);

        return back();
    }
}
