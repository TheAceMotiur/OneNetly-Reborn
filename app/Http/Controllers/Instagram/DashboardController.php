<?php

namespace App\Http\Controllers\Instagram;

use App\Http\Controllers\Controller;
use App\Models\AutomationActivity;
use App\Models\InstagramAutomation;
use App\Models\InstagramMessage;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Show Instagram connection, automations and the direct message inbox.
     */
    public function __invoke(Request $request): Response
    {
        $account = $this->automationAccount($request);
        $profile = $account->workspaceProfile();

        InstagramAutomation::ensureDefaults($profile);

        $automationRows = $profile->instagramAutomations()->whereIn('key', array_keys(InstagramAutomation::DEFAULTS))->get()->keyBy('key')->all();
        $keywordRules = $profile->instagramAutomations()->where('key', 'keyword')->get();

        $messages = $profile->instagramMessages()
            ->where('channel', 'messenger')
            ->limit(200)
            ->get();

        $activity = $profile->instagramMessages()->limit(12)->get();

        $sent = $profile->instagramMessages()
            ->where('direction', 'outbound')
            ->whereIn('status', ['sent', 'delivered', 'read'])
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        $received = $profile->instagramMessages()
            ->where('direction', 'inbound')
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        $conversations = $profile->instagramMessages()
            ->where('channel', 'messenger')
            ->whereNotNull('igsid')
            ->distinct()
            ->count('igsid');

        return Inertia::render('instagram/Index', [
            'profile' => [
                'username' => $profile->username,
                'name' => $profile->display_name ?: $profile->username,
            ],
            'connection' => [
                'platform_enabled' => (bool) setting('instagram_enabled', true),
                'enabled' => $account->instagram_enabled,
                'username' => $account->instagram_username,
                'account_id' => $account->instagram_account_id,
                'has_token' => filled($account->instagram_access_token),
                'webhook_url' => route('webhooks.instagram'),
                'graph_version' => (string) (setting('instagram_graph_version') ?: 'v21.0'),
            ],
            'automations' => collect(InstagramAutomation::DEFAULTS)
                ->map(function (array $definition, string $key) use ($automationRows): array {
                    $row = $automationRows[$key] ?? null;

                    return [
                        'key' => $key,
                        'label' => $definition['label'],
                        'description' => $definition['description'],
                        'is_enabled' => (bool) data_get($row, 'is_enabled', true),
                        'message' => (string) data_get($row, 'message', $definition['message']),
                        'keywords' => (string) data_get($row, 'keywords', ''),
                    ];
                })
                ->values()
                ->all(),
            'keywordRules' => $keywordRules
                ->map(fn (InstagramAutomation $rule): array => [
                    'id' => $rule->id,
                    'name' => $rule->name,
                    'keywords' => $rule->keywords,
                    'message' => $rule->message,
                    'is_enabled' => $rule->is_enabled,
                ])
                ->all(),
            'automationActivity' => AutomationActivity::panelFor($profile, 'instagram'),
            'messages' => $messages
                ->map(fn (InstagramMessage $message): array => [
                    'id' => $message->id,
                    'igsid' => $message->igsid,
                    'name' => $message->contact_name,
                    'direction' => $message->direction,
                    'body' => $message->body,
                    'status' => $message->status,
                    'error' => $message->error,
                    'created_at' => $message->created_at?->toDayDateTimeString(),
                    'created_human' => $message->created_at?->diffForHumans(),
                ])
                ->all(),
            'activity' => $activity
                ->map(fn (InstagramMessage $message): array => [
                    'id' => $message->id,
                    'name' => $message->contact_name,
                    'igsid' => $message->igsid,
                    'channel' => $message->channel,
                    'direction' => $message->direction,
                    'body' => $message->body,
                    'status' => $message->status,
                    'created_human' => $message->created_at?->diffForHumans(),
                ])
                ->all(),
            'stats' => [
                'conversations' => $conversations,
                'received' => $received,
                'sent' => $sent,
                'keyword_rules' => $keywordRules->count(),
                'automations' => $profile->instagramAutomations()->where('is_enabled', true)->count(),
            ],
        ]);
    }
}
