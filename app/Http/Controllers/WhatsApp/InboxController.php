<?php

namespace App\Http\Controllers\WhatsApp;

use App\Http\Controllers\Controller;
use App\Models\WhatsAppContact;
use App\Services\WhatsAppClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class InboxController extends Controller
{
    /**
     * Send a free-form reply to a contact.
     */
    public function send(Request $request): RedirectResponse
    {
        $profile = $this->automationAccount($request)->workspaceProfile();

        $data = $request->validate([
            'phone' => ['required', 'string', 'max:40'],
            'body' => ['required', 'string', 'max:4000'],
        ]);

        $message = app(WhatsAppClient::class)->sendText(
            $profile,
            $data['phone'],
            $data['body'],
            $request->string('name')->toString() ?: null,
        );

        if ($message?->wasSent()) {
            $phone = WhatsAppContact::normalisePhone($data['phone']);

            $contact = $profile->whatsappContacts()->firstOrCreate(
                ['phone' => $phone],
                ['source' => 'inbox', 'opted_in' => true],
            );

            $contact->update(['last_messaged_at' => now()]);

            Inertia::flash('toast', ['type' => 'success', 'message' => __('Reply sent.')]);
        } else {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => (string) (data_get($message, 'error') ?? __('Could not send the reply. Check your WhatsApp connection.')),
            ]);
        }

        return back();
    }
}
