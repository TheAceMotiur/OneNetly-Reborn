<?php

namespace App\Http\Controllers\Facebook;

use App\Http\Controllers\Controller;
use App\Models\AutomationActivity;
use App\Models\FacebookAutomation;
use App\Models\FacebookMessage;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Show Facebook connection, automations and the Messenger inbox.
     */
    public function __invoke(Request $request): Response
    {
        $account = $this->automationAccount($request);
        $profile = $account->workspaceProfile();

        FacebookAutomation::ensureDefaults($profile);

        $automationRows = $profile->facebookAutomations()->whereIn('key', array_keys(FacebookAutomation::DEFAULTS))->get()->keyBy('key')->all();
        $keywordRules = $profile->facebookAutomations()->where('key', 'keyword')->get();

        $messages = $profile->facebookMessages()
            ->where('channel', 'messenger')
            ->limit(200)
            ->get();

        $activity = $profile->facebookMessages()->limit(12)->get();

        $sent = $profile->facebookMessages()
            ->where('direction', 'outbound')
            ->whereIn('status', ['sent', 'delivered', 'read'])
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        $received = $profile->facebookMessages()
            ->where('direction', 'inbound')
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        $conversations = $profile->facebookMessages()
            ->where('channel', 'messenger')
            ->whereNotNull('psid')
            ->distinct()
            ->count('psid');

        return Inertia::render('facebook/Index', [
            'profile' => [
                'username' => $profile->username,
                'name' => $profile->display_name ?: $profile->username,
            ],
            'connection' => [
                'platform_enabled' => (bool) setting('facebook_enabled', true),
                'enabled' => $account->facebook_enabled,
                'page_name' => $account->facebook_page_name,
                'page_id' => $account->facebook_page_id,
                'has_token' => filled($account->facebook_page_access_token),
                'webhook_url' => route('webhooks.facebook'),
                'graph_version' => (string) (setting('facebook_graph_version') ?: 'v21.0'),
            ],
            'automations' => collect(FacebookAutomation::DEFAULTS)
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
                ->map(fn (FacebookAutomation $rule): array => [
                    'id' => $rule->id,
                    'name' => $rule->name,
                    'keywords' => $rule->keywords,
                    'message' => $rule->message,
                    'is_enabled' => $rule->is_enabled,
                ])
                ->all(),
            'automationActivity' => AutomationActivity::panelFor($profile, 'facebook'),
            'messages' => $messages
                ->map(fn (FacebookMessage $message): array => [
                    'id' => $message->id,
                    'psid' => $message->psid,
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
                ->map(fn (FacebookMessage $message): array => [
                    'id' => $message->id,
                    'name' => $message->contact_name,
                    'psid' => $message->psid,
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
                'automations' => $profile->facebookAutomations()->where('is_enabled', true)->count(),
            ],
        ]);
    }
}
