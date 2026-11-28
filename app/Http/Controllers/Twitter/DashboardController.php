<?php

namespace App\Http\Controllers\Twitter;

use App\Http\Controllers\Controller;
use App\Models\AutomationActivity;
use App\Models\TwitterAutomation;
use App\Models\TwitterMention;
use App\Models\TwitterPost;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Show the Twitter automation dashboard.
     */
    public function __invoke(Request $request): Response
    {
        $account = $this->automationAccount($request);
        $profile = $account->workspaceProfile();

        TwitterAutomation::ensureDefaults($profile);

        $automationRows = $profile->twitterAutomations()->whereIn('key', array_keys(TwitterAutomation::DEFAULTS))->get()->keyBy('key')->all();
        $keywordRules = $profile->twitterAutomations()->where('key', 'keyword')->get();

        $posts = $profile->twitterPosts()->limit(50)->get();
        $mentions = $profile->twitterMentions()->limit(50)->get();

        return Inertia::render('twitter/Index', [
            'profile' => [
                'username' => $profile->username,
                'name' => $profile->display_name ?: $profile->username,
            ],
            'connection' => [
                'platform_enabled' => (bool) setting('twitter_enabled', true),
                'enabled' => $account->twitter_enabled,
                'username' => $account->twitter_username,
                'user_id' => $account->twitter_user_id,
                'has_access_token' => filled($account->twitter_access_token),
                'has_refresh_token' => filled($account->twitter_refresh_token),
                'token_expires_at' => $account->twitter_token_expires_at?->toDayDateTimeString(),
            ],
            'automations' => collect(TwitterAutomation::DEFAULTS)
                ->map(function (array $definition, string $key) use ($automationRows): array {
                    $row = $automationRows[$key] ?? null;

                    return [
                        'key' => $key,
                        'label' => $definition['label'],
                        'description' => $definition['description'],
                        'is_enabled' => (bool) data_get($row, 'is_enabled', true),
                        'message' => (string) data_get($row, 'message', $definition['message']),
                    ];
                })
                ->values()
                ->all(),
            'keywordRules' => $keywordRules
                ->map(fn (TwitterAutomation $rule): array => [
                    'id' => $rule->id,
                    'name' => $rule->name,
                    'keywords' => $rule->keywords,
                    'message' => $rule->message,
                    'is_enabled' => $rule->is_enabled,
                ])
                ->all(),
            'automationActivity' => AutomationActivity::panelFor($profile, 'twitter'),
            'posts' => $posts
                ->map(fn (TwitterPost $post): array => [
                    'id' => $post->id,
                    'body' => $post->body,
                    'status' => $post->status,
                    'scheduled_at' => $post->scheduled_at?->toDayDateTimeString(),
                    'published_at' => $post->published_at?->toDayDateTimeString(),
                    'external_id' => $post->external_id,
                    'error' => $post->error,
                    'created_human' => $post->created_at?->diffForHumans(),
                ])
                ->all(),
            'mentions' => $mentions
                ->map(fn (TwitterMention $mention): array => [
                    'id' => $mention->id,
                    'tweet_id' => $mention->tweet_id,
                    'author_username' => $mention->author_username,
                    'body' => $mention->body,
                    'replied' => $mention->replied,
                    'reply_tweet_id' => $mention->reply_tweet_id,
                    'created_human' => $mention->created_at?->diffForHumans(),
                ])
                ->all(),
            'stats' => [
                'posts' => $profile->twitterPosts()->where('status', 'published')->count(),
                'scheduled' => $profile->twitterPosts()->where('status', 'scheduled')->count(),
                'mentions' => $profile->twitterMentions()->count(),
                'replied' => $profile->twitterMentions()->where('replied', true)->count(),
                'keyword_rules' => $keywordRules->count(),
            ],
        ]);
    }
}
