<?php

namespace App\Services;

use App\Models\AutomationActivity;
use App\Models\FacebookAutomation;
use App\Models\FacebookMessage;
use App\Models\Profile;
use Illuminate\Support\Str;

class FacebookNotifier
{
    /**
     * Handle one inbound Messenger event.
     *
     * @param  array<string, mixed>  $event
     */
    public function handleMessenger(Profile $profile, array $event): void
    {
        if (! $profile->user?->hasToolAccess('facebook')) {
            return;
        }

        $psid = (string) data_get($event, 'sender.id', '');

        if ($psid === '' || (bool) data_get($event, 'message.is_echo', false)) {
            return;
        }

        $body = (string) data_get($event, 'message.text', '');
        $externalId = data_get($event, 'message.mid');

        if (is_string($externalId) && $externalId !== ''
            && $profile->facebookMessages()->where('external_id', $externalId)->exists()) {
            return;
        }

        $isNewConversation = ! $profile->facebookMessages()
            ->where('channel', 'messenger')
            ->where('psid', $psid)
            ->exists();

        $profile->facebookMessages()->create([
            'psid' => $psid,
            'channel' => 'messenger',
            'direction' => 'inbound',
            'body' => $body !== '' ? $body : null,
            'status' => 'received',
            'external_id' => is_string($externalId) ? $externalId : null,
            'meta' => $event,
        ]);

        if ($body !== '' && $this->keywordReply($profile, $psid, $body)) {
            return;
        }

        if ($isNewConversation) {
            $this->welcome($profile, $psid);
        }
    }

    /**
     * Handle one new comment on a Page post.
     *
     * @param  array<string, mixed>  $value
     */
    public function handleComment(Profile $profile, array $value): void
    {
        if (! $profile->user->hasToolAccess('facebook')) {
            return;
        }

        if (($value['item'] ?? '') !== 'comment' || ($value['verb'] ?? 'add') !== 'add') {
            return;
        }

        $commentId = (string) ($value['comment_id'] ?? '');
        $authorId = (string) data_get($value, 'from.id', '');

        if ($commentId === '' || ($authorId !== '' && $authorId === (string) $profile->user?->facebook_page_id)) {
            return;
        }

        if ($profile->facebookMessages()->where('external_id', $commentId)->exists()) {
            return;
        }

        $authorName = data_get($value, 'from.name');
        $authorName = is_string($authorName) ? $authorName : null;
        $commentBody = (string) ($value['message'] ?? '');

        $profile->facebookMessages()->create([
            'psid' => $authorId !== '' ? $authorId : null,
            'contact_name' => $authorName,
            'channel' => 'comment',
            'direction' => 'inbound',
            'body' => $commentBody,
            'status' => 'received',
            'external_id' => $commentId,
            'meta' => $value,
        ]);

        $this->sendCommentDm($profile, $commentId, $authorId, $authorName, $commentBody);

        $automation = $profile->facebookAutomations()->firstOrCreate(
            ['key' => 'comment'],
            [
                'is_enabled' => true,
                'message' => FacebookAutomation::DEFAULTS['comment']['message'],
            ],
        );

        if (! $automation->is_enabled) {
            return;
        }

        if (filled($automation->keywords) && ! $this->keywordsMatch($automation->keywords, $commentBody)) {
            return;
        }

        $reply = $this->render($profile, $automation->message, $authorName);

        if ($reply === '') {
            return;
        }

        $message = app(FacebookClient::class)->replyToComment(
            $profile,
            $commentId,
            $reply,
            $authorId !== '' ? $authorId : null,
            $authorName,
        );

        $this->record($profile, 'comment', $message, 'comment', $authorId, $authorName);
    }

    /**
     * Send a private reply when a comment matches the comment-to-DM keywords.
     */
    private function sendCommentDm(Profile $profile, string $commentId, string $authorId, ?string $authorName, string $body): void
    {
        $automation = $profile->facebookAutomations()->firstOrCreate(
            ['key' => 'comment_dm'],
            [
                'is_enabled' => true,
                'message' => FacebookAutomation::DEFAULTS['comment_dm']['message'],
            ],
        );

        if (! $automation->is_enabled || ! $this->keywordsMatch($automation->keywords, $body)) {
            return;
        }

        $reply = $this->render($profile, $automation->message, $authorName);

        if ($reply === '') {
            return;
        }

        $message = app(FacebookClient::class)->sendPrivateReply(
            $profile,
            $commentId,
            $reply,
            $authorId !== '' ? $authorId : null,
            $authorName,
        );

        $this->record($profile, 'comment_dm', $message, 'comment', $authorId, $authorName, $automation->name);
    }

    /**
     * Send the first-contact welcome message.
     */
    public function welcome(Profile $profile, string $psid, ?string $name = null): void
    {
        if (! $profile->user?->hasToolAccess('facebook')) {
            return;
        }

        $automation = $profile->facebookAutomations()->firstOrCreate(
            ['key' => 'welcome'],
            [
                'is_enabled' => true,
                'message' => FacebookAutomation::DEFAULTS['welcome']['message'],
            ],
        );

        if (! $automation->is_enabled) {
            return;
        }

        $body = $this->render($profile, $automation->message, $name);

        if ($body === '') {
            return;
        }

        $message = app(FacebookClient::class)->sendText($profile, $psid, $body, $name);

        $this->record($profile, 'welcome', $message, 'new_conversation', $psid, $name);
    }

    /**
     * Reply with the first enabled keyword rule that matches the message.
     */
    public function keywordReply(Profile $profile, string $psid, string $body): bool
    {
        if (! $profile->user?->hasToolAccess('facebook')) {
            return false;
        }

        $rules = $profile->facebookAutomations()
            ->where('key', 'keyword')
            ->where('is_enabled', true)
            ->orderBy('id')
            ->get();

        foreach ($rules as $rule) {
            if (! $this->keywordsMatch($rule->keywords, $body)) {
                continue;
            }

            $reply = $this->render($profile, $rule->message, null);

            if ($reply === '') {
                continue;
            }

            $message = app(FacebookClient::class)->sendText($profile, $psid, $reply);

            $this->record($profile, 'keyword', $message, 'inbound_message', $psid, null, $rule->name);

            return true;
        }

        return false;
    }

    /**
     * Determine whether the message contains one of the comma separated keywords.
     */
    private function keywordsMatch(?string $keywords, string $body): bool
    {
        $haystack = Str::lower($body);

        foreach (explode(',', (string) $keywords) as $keyword) {
            $keyword = Str::lower(trim($keyword));

            if ($keyword !== '' && str_contains($haystack, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Record the result of one automation run.
     */
    private function record(Profile $profile, string $key, ?FacebookMessage $message, string $trigger, ?string $contact, ?string $contactName, ?string $rule = null): void
    {
        app(AutomationLogger::class)->record($profile, 'facebook', $key, match (true) {
            $message === null => AutomationActivity::STATUS_SKIPPED,
            $message->status === 'failed' => AutomationActivity::STATUS_FAILED,
            default => AutomationActivity::STATUS_SENT,
        }, [
            'trigger' => $trigger,
            'contact' => $contact,
            'contact_name' => $contactName,
            'rule' => $rule,
            'detail' => $message === null
                ? (string) __('Facebook is not connected.')
                : (is_string($message->error) ? $message->error : null),
        ]);
    }

    /**
     * Replace the placeholders available to Facebook automations.
     */
    private function render(Profile $profile, ?string $message, ?string $customer): string
    {
        return trim(strtr((string) $message, [
            '{customer}' => $customer ?: __('there'),
            '{page}' => $profile->user?->facebook_page_name ?: $profile->restaurantName(),
        ]));
    }
}
