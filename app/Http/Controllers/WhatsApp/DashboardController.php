<?php

namespace App\Http\Controllers\WhatsApp;

use App\Http\Controllers\Controller;
use App\Models\AutomationActivity;
use App\Models\WhatsAppAutomation;
use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppMessage;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Show connection status, campaigns, automations, inbox and contacts.
     */
    public function __invoke(Request $request): Response
    {
        $account = $this->automationAccount($request);
        $profile = $account->workspaceProfile();

        WhatsAppAutomation::ensureDefaults($profile);

        $automationRows = $profile->whatsappAutomations()->get()->keyBy('key')->all();
        $keywordRules = $profile->whatsappAutomations()->where('key', 'keyword')->get();
        $contacts = $profile->whatsappContacts()->limit(200)->get();
        $messages = $profile->whatsappMessages()->limit(200)->get();
        $campaigns = $profile->whatsappCampaigns()->limit(20)->get();

        $sent = $profile->whatsappMessages()
            ->where('direction', 'outbound')
            ->whereIn('status', ['sent', 'delivered', 'read'])
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        $received = $profile->whatsappMessages()
            ->where('direction', 'inbound')
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        $conversations = $profile->whatsappMessages()
            ->distinct()
            ->count('contact_phone');

        return Inertia::render('whatsapp/Index', [
            'profile' => [
                'username' => $profile->username,
                'name' => $profile->display_name ?: $profile->username,
            ],
            'connection' => [
                'platform_enabled' => (bool) setting('whatsapp_enabled', true),
                'enabled' => $account->whatsapp_enabled,
                'display_number' => $account->whatsapp_display_number,
                'phone_number_id' => $account->whatsapp_phone_number_id,
                'business_account_id' => $account->whatsapp_business_account_id,
                'has_token' => filled($account->whatsapp_access_token),
                'order_updates' => $account->whatsapp_order_updates,
                'reservation_updates' => $account->whatsapp_reservation_updates,
                'webhook_url' => route('webhooks.whatsapp'),
                'api_version' => (string) (setting('whatsapp_api_version') ?: 'v21.0'),
            ],
            'automations' => collect(WhatsAppAutomation::DEFAULTS)
                ->map(function (array $definition, string $key) use ($automationRows): array {
                    $row = $automationRows[$key] ?? null;

                    return [
                        'key' => $key,
                        'label' => $definition['label'],
                        'description' => $definition['description'],
                        'is_enabled' => (bool) data_get($row, 'is_enabled', true),
                        'message' => (string) data_get($row, 'message', $definition['message']),
                        'template_name' => data_get($row, 'template_name'),
                        'language' => (string) data_get($row, 'language', 'en_US'),
                    ];
                })
                ->values()
                ->all(),
            'keywordRules' => $keywordRules
                ->map(fn (WhatsAppAutomation $rule): array => [
                    'id' => $rule->id,
                    'name' => $rule->name,
                    'keywords' => $rule->keywords,
                    'message' => $rule->message,
                    'is_enabled' => $rule->is_enabled,
                ])
                ->all(),
            'automationActivity' => AutomationActivity::panelFor($profile, 'whatsapp'),
            'campaigns' => $campaigns
                ->map(fn (WhatsAppCampaign $campaign): array => [
                    'id' => $campaign->id,
                    'name' => $campaign->name,
                    'message' => $campaign->message,
                    'template_name' => $campaign->template_name,
                    'status' => $campaign->status,
                    'sent_count' => $campaign->sent_count,
                    'failed_count' => $campaign->failed_count,
                    'sent_at' => $campaign->sent_at?->toDayDateTimeString(),
                    'created_at' => $campaign->created_at?->toDayDateTimeString(),
                ])
                ->all(),
            'contacts' => $contacts
                ->map(fn (WhatsAppContact $contact): array => [
                    'id' => $contact->id,
                    'phone' => $contact->phone,
                    'name' => $contact->name,
                    'email' => $contact->email,
                    'source' => $contact->source,
                    'opted_in' => $contact->opted_in,
                    'last_messaged_at' => $contact->last_messaged_at?->diffForHumans(),
                    'created_at' => $contact->created_at?->toDateString(),
                ])
                ->all(),
            'messages' => $messages
                ->map(fn (WhatsAppMessage $message): array => [
                    'id' => $message->id,
                    'phone' => $message->contact_phone,
                    'name' => $message->contact_name,
                    'direction' => $message->direction,
                    'type' => $message->message_type,
                    'body' => $message->body,
                    'template_name' => $message->template_name,
                    'status' => $message->status,
                    'error' => $message->error,
                    'created_at' => $message->created_at?->toDayDateTimeString(),
                    'created_human' => $message->created_at?->diffForHumans(),
                ])
                ->all(),
            'stats' => [
                'contacts' => $profile->whatsappContacts()->count(),
                'opted_in' => $profile->whatsappContacts()->where('opted_in', true)->count(),
                'sent' => $sent,
                'received' => $received,
                'campaigns' => $profile->whatsappCampaigns()->count(),
                'conversations' => $conversations,
                'keyword_rules' => $keywordRules->count(),
            ],
        ]);
    }
}
