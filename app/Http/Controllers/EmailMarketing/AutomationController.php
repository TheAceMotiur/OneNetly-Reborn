<?php

namespace App\Http\Controllers\EmailMarketing;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AutomationController extends Controller
{
    /**
     * Update the welcome email automation.
     */
    public function update(Request $request): RedirectResponse
    {
        $profile = $this->automationAccount($request)->workspaceProfile();

        $data = $request->validate([
            'is_enabled' => ['required', 'boolean'],
            'subject' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:10000'],
        ]);

        $profile->emailAutomations()->updateOrCreate(
            ['key' => 'welcome'],
            [
                'is_enabled' => (bool) $data['is_enabled'],
                'subject' => $data['subject'],
                'body' => $data['body'],
            ],
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Welcome email updated.'),
        ]);

        return back();
    }
}
