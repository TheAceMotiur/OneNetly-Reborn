<?php

namespace App\Http\Controllers\Instagram;

use App\Http\Controllers\Controller;
use App\Services\InstagramClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class InboxController extends Controller
{
    /**
     * Send a direct message reply.
     */
    public function send(Request $request): RedirectResponse
    {
        $profile = $this->automationAccount($request)->workspaceProfile();

        $data = $request->validate([
            'igsid' => ['required', 'string', 'max:64'],
            'body' => ['required', 'string', 'max:4000'],
        ]);

        $message = app(InstagramClient::class)->sendText(
            $profile,
            $data['igsid'],
            $data['body'],
            $request->string('name')->toString() ?: null,
        );

        if ($message?->wasSent()) {
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Reply sent.')]);
        } else {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => (string) (data_get($message, 'error') ?? __('Could not send the reply. Check your Instagram connection.')),
            ]);
        }

        return back();
    }
}
