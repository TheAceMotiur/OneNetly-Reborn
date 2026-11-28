<?php

namespace App\Services;

use App\Models\AutomationActivity;
use App\Models\Profile;
use App\Models\TwitterAutomation;
use Illuminate\Support\Str;

class TwitterNotifier
{
    public function __construct(private TwitterClient $client) {}

    /**
     * Import new mentions and reply where an automation applies.
     *
     * @return int Number of replies sent.
     */
    public function sync(Profile $profile): int
    {
        if (! $profile->user?->hasToolAccess('twitter')) {
            return 0;
        }

        if (! $this->client->isConfigured($profile->user)) {
            return 0;
        }

        $sinceId = $profile->twitterMentions()->orderByDesc('id')->value('tweet_id');
        $result = $this->client->mentions($profile, is_string($sinceId) ? $sinceId : null);

        if (! $result['ok']) {
            return 0;
        }

        $replied = 0;

        foreach (array_reverse($result['mentions']) as $mention) {
            if (($mention['author_id'] ?? null) === (string) $profile->user?->twitter_user_id) {
                continue;
            }

            $record = $profile->twitterMentions()->firstOrCreate(
                ['tweet_id' => $mention['tweet_id']],
                [
                    'author_id' => $mention['author_id'],
                    'author_username' => $mention['author_username'],
                    'body' => $mention['body'],
                    'replied' => false,
                    'meta' => $mention['meta'],
                ],
            );

            if (! $record->wasRecentlyCreated) {
                continue;
            }

            $reply = $this->replyFor($profile, $mention['body'], $mention['author_username']);

            if ($reply['body'] === '') {
                continue;
            }

            $response = $this->client->createTweet($profile, $reply['body'], $mention['tweet_id']);

            if (! $response['ok']) {
                $this->record($profile, $reply['rule'], AutomationActivity::STATUS_FAILED, $mention['author_username'], $response['error']);

                continue;
            }

            $profile->twitterPosts()->create([
                'body' => $reply['body'],
                'status' => 'published',
                'published_at' => now(),
                'external_id' => $response['id'],
                'meta' => $response['data'],
            ]);

            $record->update([
                'replied' => true,
                'reply_tweet_id' => $response['id'],
            ]);

            $this->record($profile, $reply['rule'], AutomationActivity::STATUS_SENT, $mention['author_username']);

            $replied++;
        }

        return $replied;
    }

    /**
     * Pick the first matching keyword rule, then fall back to the mention automation.
     *
     * @return array{body: string, rule: string|null}
     */
    private function replyFor(Profile $profile, string $text, ?string $username): array
    {
        $haystack = Str::lower($text);

        $rules = $profile->twitterAutomations()
            ->where('key', 'keyword')
            ->where('is_enabled', true)
            ->orderBy('id')
            ->get();

        foreach ($rules as $rule) {
            $keywords = array_filter(array_map(
                fn (string $keyword): string => Str::lower(trim($keyword)),
                explode(',', (string) $rule->keywords),
            ));

            foreach ($keywords as $keyword) {
                if (! str_contains($haystack, $keyword)) {
                    continue;
                }

                return [
                    'body' => $this->render($profile, $rule->message, $username, $text),
                    'rule' => $rule->name,
                ];
            }
        }

        $automation = $profile->twitterAutomations()->firstOrCreate(
            ['key' => 'mention'],
            [
                'is_enabled' => true,
                'message' => TwitterAutomation::DEFAULTS['mention']['message'],
            ],
        );

        if (! $automation->is_enabled) {
            return ['body' => '', 'rule' => null];
        }

        return [
            'body' => $this->render($profile, $automation->message, $username, $text),
            'rule' => null,
        ];
    }

    /**
     * Record the result of one automation run.
     */
    private function record(Profile $profile, ?string $rule, string $status, ?string $username, ?string $detail = null): void
    {
        app(AutomationLogger::class)->record($profile, 'twitter', $rule === null ? 'mention' : 'keyword', $status, [
            'trigger' => 'mention',
            'contact' => $username,
            'rule' => $rule,
            'detail' => $detail,
        ]);
    }

    /**
     * Replace the placeholders available to Twitter automations.
     */
    private function render(Profile $profile, ?string $message, ?string $username, string $text): string
    {
        $account = $profile->user?->twitter_username;

        return trim(strtr((string) $message, [
            '{username}' => $username !== null && $username !== '' ? $username : __('there'),
            '{account}' => filled($account) ? '@'.$account : $profile->restaurantName(),
            '{text}' => Str::limit($text, 120),
        ]));
    }

    /**
     * Publish due scheduled tweets for a page.
     */
    public function publishDue(Profile $profile): int
    {
        if (! $profile->user?->hasToolAccess('twitter')) {
            return 0;
        }

        $due = $profile->twitterPosts()
            ->where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->limit(20)
            ->get();

        $published = 0;

        foreach ($due as $post) {
            if (app(TwitterPublisher::class)->publish($post)) {
                $published++;
            }
        }

        return $published;
    }
}
