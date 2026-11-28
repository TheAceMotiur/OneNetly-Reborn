<?php

namespace App\Http\Controllers\Twitter;

use App\Http\Controllers\Controller;
use App\Services\TwitterClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SettingsController extends Controller
{
    /**
     * Save the Twitter connection for the account.
     */
    public function update(Request $request): RedirectResponse
    {
        $account = $this->automationAccount($request);

        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'username' => ['nullable', 'string', 'max:50'],
            'user_id' => ['nullable', 'string', 'max:32'],
            'access_token' => ['nullable', 'string', 'max:4000'],
            'refresh_token' => ['nullable', 'string', 'max:4000'],
            'remove_token' => ['sometimes', 'boolean'],
        ]);

        $account->update([
            'twitter_enabled' => (bool) $data['enabled'],
            'twitter_username' => ltrim((string) ($data['username'] ?? ''), '@') ?: null,
            'twitter_user_id' => $data['user_id'] ?? null,
            ...(filled($data['access_token'] ?? null)
                ? [
                    'twitter_access_token' => trim((string) $data['access_token']),
                    'twitter_token_expires_at' => null,
                ]
                : []),
            ...(filled($data['refresh_token'] ?? null)
                ? ['twitter_refresh_token' => trim((string) $data['refresh_token'])]
                : []),
            ...($request->boolean('remove_token')
                ? [
                    'twitter_access_token' => null,
                    'twitter_refresh_token' => null,
                    'twitter_token_expires_at' => null,
                ]
                : []),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Twitter settings saved.'),
        ]);

        return back();
    }

    /**
     * Verify the saved credentials with X.
     */
    public function test(Request $request): RedirectResponse
    {
        $result = app(TwitterClient::class)->verify($this->automationAccount($request));

        Inertia::flash('toast', [
            'type' => $result['ok'] ? 'success' : 'error',
            'message' => $result['message'],
        ]);

        return back();
    }
}
