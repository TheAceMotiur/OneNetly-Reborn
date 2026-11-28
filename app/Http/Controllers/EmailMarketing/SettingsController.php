<?php

namespace App\Http\Controllers\EmailMarketing;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SettingsController extends Controller
{
    /**
     * Update the email marketing settings for the account.
     */
    public function update(Request $request): RedirectResponse
    {
        $account = $this->automationAccount($request);

        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'reply_to' => ['nullable', 'email', 'max:120'],
            'footer' => ['nullable', 'string', 'max:1000'],
        ]);

        $account->update([
            'email_marketing_enabled' => (bool) $data['enabled'],
            'email_reply_to' => $data['reply_to'] ?? null,
            'email_footer' => $data['footer'] ?? null,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Email marketing settings saved.'),
        ]);

        return back();
    }
}
