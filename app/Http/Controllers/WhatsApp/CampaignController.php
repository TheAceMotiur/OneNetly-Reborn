<?php

namespace App\Http\Controllers\WhatsApp;

use App\Http\Controllers\Controller;
use App\Models\WhatsAppCampaign;
use App\Services\AuditLogger;
use App\Services\WhatsAppCampaignSender;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CampaignController extends Controller
{
    /**
     * Create a draft campaign.
     */
    public function store(Request $request): RedirectResponse
    {
        $profile = $this->automationAccount($request)->workspaceProfile();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'message' => ['nullable', 'string', 'max:2000', 'required_without:template_name'],
            'template_name' => ['nullable', 'string', 'max:80', 'required_without:message'],
            'language' => ['nullable', 'string', 'max:10'],
        ]);

        $profile->whatsappCampaigns()->create([
            'name' => $data['name'],
            'message' => $data['message'] ?? null,
            'template_name' => $data['template_name'] ?? null,
            'language' => $data['language'] ?? 'en_US',
            'audience' => 'all',
            'status' => 'draft',
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Campaign saved. Send it when you are ready.'),
        ]);

        return back();
    }

    /**
     * Send a campaign to every opted-in contact.
     */
    public function send(Request $request, WhatsAppCampaign $campaign): RedirectResponse
    {
        $profile = $this->automationAccount($request)->workspaceProfile();

        abort_unless($campaign->profile_id === $profile->id, 404);

        $campaign = app(WhatsAppCampaignSender::class)->send($campaign);

        app(AuditLogger::class)->log($profile, 'whatsapp.campaign_sent', __('WhatsApp campaign :name sent to :sent contacts.', [
            'name' => $campaign->name,
            'sent' => $campaign->sent_count,
        ]));

        Inertia::flash('toast', [
            'type' => $campaign->status === 'failed' ? 'error' : 'success',
            'message' => $campaign->status === 'failed'
                ? __('The campaign could not be sent. Check your WhatsApp connection and contacts.')
                : __('Campaign sent to :sent contacts (:failed failed).', [
                    'sent' => $campaign->sent_count,
                    'failed' => $campaign->failed_count,
                ]),
        ]);

        return back();
    }

    /**
     * Delete a campaign.
     */
    public function destroy(Request $request, WhatsAppCampaign $campaign): RedirectResponse
    {
        $profile = $this->automationAccount($request)->workspaceProfile();

        abort_unless($campaign->profile_id === $profile->id, 404);

        $campaign->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Campaign deleted.')]);

        return back();
    }
}
