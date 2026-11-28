<?php

namespace App\Http\Controllers\EmailMarketing;

use App\Http\Controllers\Controller;
use App\Models\AutomationActivity;
use App\Models\EmailAutomation;
use App\Models\EmailCampaign;
use App\Models\Subscriber;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Show the email marketing workspace for one page.
     */
    public function __invoke(Request $request): Response
    {
        $account = $this->automationAccount($request);
        $profile = $account->workspaceProfile();

        EmailAutomation::ensureDefaults($profile);

        $automation = $profile->emailAutomations()->where('key', 'welcome')->first();
        $campaigns = $profile->emailCampaigns()->limit(20)->get();
        $subscribers = $profile->subscribers()->latest()->limit(200)->get();

        return Inertia::render('email-marketing/Index', [
            'profile' => [
                'username' => $profile->username,
                'name' => $profile->display_name ?: $profile->username,
            ],
            'campaigns' => $campaigns
                ->map(fn (EmailCampaign $campaign): array => [
                    'id' => $campaign->id,
                    'name' => $campaign->name,
                    'subject' => $campaign->subject,
                    'body' => $campaign->body,
                    'status' => $campaign->status,
                    'sent_count' => $campaign->sent_count,
                    'failed_count' => $campaign->failed_count,
                    'sent_at' => $campaign->sent_at?->toDayDateTimeString(),
                    'created_at' => $campaign->created_at?->toDayDateTimeString(),
                ])
                ->all(),
            'subscribers' => $subscribers
                ->map(fn (Subscriber $subscriber): array => [
                    'id' => $subscriber->id,
                    'email' => $subscriber->email,
                    'source' => $subscriber->source,
                    'created_at' => $subscriber->created_at?->toDayDateTimeString(),
                ])
                ->all(),
            'automation' => [
                'key' => 'welcome',
                'label' => EmailAutomation::DEFAULTS['welcome']['label'],
                'description' => EmailAutomation::DEFAULTS['welcome']['description'],
                'is_enabled' => (bool) data_get($automation, 'is_enabled', true),
                'subject' => (string) data_get($automation, 'subject', EmailAutomation::DEFAULTS['welcome']['subject']),
                'body' => (string) data_get($automation, 'body', EmailAutomation::DEFAULTS['welcome']['body']),
            ],
            'automationActivity' => AutomationActivity::panelFor($profile, 'email'),
            'settings' => [
                'enabled' => $account->email_marketing_enabled,
                'reply_to' => $account->email_reply_to,
                'footer' => $account->email_footer,
            ],
            'stats' => [
                'subscribers' => $profile->subscribers()->count(),
                'campaigns' => $profile->emailCampaigns()->count(),
                'emails_sent' => (int) $profile->emailCampaigns()->sum('sent_count'),
                'sent_30_days' => (int) $profile->emailCampaigns()
                    ->where('sent_at', '>=', now()->subDays(30))
                    ->sum('sent_count'),
                'welcome_enabled' => (bool) data_get($automation, 'is_enabled', true),
            ],
        ]);
    }
}
