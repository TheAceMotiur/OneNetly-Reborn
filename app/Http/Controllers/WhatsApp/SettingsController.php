<?php

namespace App\Http\Controllers\WhatsApp;

use App\Http\Controllers\Controller;
use App\Services\WhatsAppClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SettingsController extends Controller
{
    /**
     * Save the WhatsApp Cloud API connection for the account.
     */
    public function update(Request $request): RedirectResponse
    {
        $account = $this->automationAccount($request);

        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'display_number' => ['nullable', 'string', 'max:40'],
            'phone_number_id' => ['nullable', 'string', 'max:64'],
            'business_account_id' => ['nullable', 'string', 'max:64'],
            'access_token' => ['nullable', 'string', 'max:2000'],
            'remove_token' => ['sometimes', 'boolean'],
            'order_updates' => ['required', 'boolean'],
            'reservation_updates' => ['required', 'boolean'],
        ]);

        $account->update([
            'whatsapp_enabled' => (bool) $data['enabled'],
            'whatsapp_display_number' => $data['display_number'] ?? null,
            'whatsapp_phone_number_id' => $data['phone_number_id'] ?? null,
            'whatsapp_business_account_id' => $data['business_account_id'] ?? null,
            'whatsapp_order_updates' => (bool) $data['order_updates'],
            'whatsapp_reservation_updates' => (bool) $data['reservation_updates'],
            ...(filled($data['access_token'] ?? null)
                ? ['whatsapp_access_token' => trim((string) $data['access_token'])]
                : []),
            ...($request->boolean('remove_token') ? ['whatsapp_access_token' => null] : []),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('WhatsApp settings saved.'),
        ]);

        return back();
    }

    /**
     * Verify the saved credentials against the Graph API.
     */
    public function test(Request $request): RedirectResponse
    {
        $result = app(WhatsAppClient::class)->verify($this->automationAccount($request));

        Inertia::flash('toast', [
            'type' => $result['ok'] ? 'success' : 'error',
            'message' => $result['message'],
        ]);

        return back();
    }
}
