<?php

namespace App\Http\Controllers\Facebook;

use App\Http\Controllers\Controller;
use App\Services\FacebookClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class InboxController extends Controller
{
    /**
     * Send a Messenger reply.
     */
    public function send(Request $request): RedirectResponse
    {
        $profile = $this->automationAccount($request)->workspaceProfile();

        $data = $request->validate([
            'psid' => ['required', 'string', 'max:64'],
            'body' => ['required', 'string', 'max:4000'],
        ]);

        $message = app(FacebookClient::class)->sendText(
            $profile,
            $data['psid'],
            $data['body'],
            $request->string('name')->toString() ?: null,
        );

        if ($message?->wasSent()) {
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Reply sent.')]);
        } else {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => (string) (data_get($message, 'error') ?? __('Could not send the reply. Check your Facebook connection.')),
            ]);
        }

        return back();
    }
}
