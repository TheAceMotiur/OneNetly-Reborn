<?php

namespace App\Http\Controllers\EmailMarketing;

use App\Http\Controllers\Controller;
use App\Models\EmailCampaign;
use App\Services\AuditLogger;
use App\Services\EmailCampaignSender;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CampaignController extends Controller
{
    /**
     * Save a campaign as a draft.
     */
    public function store(Request $request): RedirectResponse
    {
        $profile = $this->automationAccount($request)->workspaceProfile();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'subject' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:10000'],
        ]);

        $profile->emailCampaigns()->create([
            ...$data,
            'status' => 'draft',
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Campaign saved. Send it when you are ready.'),
        ]);

        return back();
    }

    /**
     * Send a campaign to every subscriber.
     */
    public function send(Request $request, EmailCampaign $campaign): RedirectResponse
    {
        $profile = $this->automationAccount($request)->workspaceProfile();

        abort_unless($campaign->profile_id === $profile->id, 404);

        $campaign = app(EmailCampaignSender::class)->send($campaign);

        app(AuditLogger::class)->log($profile, 'email.campaign_sent', __('Email campaign :name sent to :count subscribers.', [
            'name' => $campaign->name,
            'count' => $campaign->sent_count,
        ]));

        Inertia::flash('toast', [
            'type' => $campaign->status === 'failed' ? 'error' : 'success',
            'message' => $campaign->status === 'failed'
                ? __('The campaign could not be sent. Check your mail settings.')
                : __('Campaign sent to :sent subscribers (:failed failed).', [
                    'sent' => $campaign->sent_count,
                    'failed' => $campaign->failed_count,
                ]),
        ]);

        return back();
    }

    /**
     * Delete a campaign.
     */
    public function destroy(Request $request, EmailCampaign $campaign): RedirectResponse
    {
        $profile = $this->automationAccount($request)->workspaceProfile();

        abort_unless($campaign->profile_id === $profile->id, 404);

        $campaign->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Campaign deleted.')]);

        return back();
    }
}
