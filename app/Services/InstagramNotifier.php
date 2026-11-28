<?php

namespace App\Services;

use App\Models\AutomationActivity;
use App\Models\InstagramAutomation;
use App\Models\InstagramMessage;
use App\Models\Profile;
use Illuminate\Support\Str;

class InstagramNotifier
{
    /**
     * Handle one inbound Instagram direct message event.
     *
     * @param  array<string, mixed>  $event
     */
    public function handleMessenger(Profile $profile, array $event): void
    {
        if (! $profile->user?->hasToolAccess('instagram')) {
            return;
        }

        $igsid = (string) data_get($event, 'sender.id', '');

        if ($igsid === '' || (bool) data_get($event, 'message.is_echo', false)) {
            return;
        }

        $body = (string) data_get($event, 'message.text', '');
        $externalId = data_get($event, 'message.mid');

        if (is_string($externalId) && $externalId !== ''
            && $profile->instagramMessages()->where('external_id', $externalId)->exists()) {
            return;
        }

        $isNewConversation = ! $profile->instagramMessages()
            ->where('channel', 'messenger')
            ->where('igsid', $igsid)
            ->exists();

        $profile->instagramMessages()->create([
            'igsid' => $igsid,
            'channel' => 'messenger',
            'direction' => 'inbound',
            'body' => $body !== '' ? $body : null,
            'status' => 'received',
            'external_id' => is_string($externalId) ? $externalId : null,
            'meta' => $event,
        ]);

        if ($body !== '' && $this->keywordReply($profile, $igsid, $body)) {
            return;
        }

        if ($isNewConversation) {
            $this->welcome($profile, $igsid);
        }
    }

    /**
     * Handle one new Instagram comment event.
     *
     * @param  array<string, mixed>  $value
     */
    public function handleComment(Profile $profile, array $value): void
    {
        if (! $profile->user->hasToolAccess('instagram')) {
            return;
        }

        if (($value['item'] ?? 'comment') !== 'comment' || ($value['verb'] ?? 'add') !== 'add') {
            return;
        }

        $commentId = (string) ($value['id'] ?? $value['comment_id'] ?? '');
        $authorId = (string) data_get($value, 'from.id', '');

        if ($commentId === '' || ($authorId !== '' && $authorId === (string) $profile->user?->instagram_account_id)) {
            return;
        }

        if ($profile->instagramMessages()->where('external_id', $commentId)->exists()) {
            return;
        }

        $authorName = data_get($value, 'from.username');
        $authorName = is_string($authorName) ? $authorName : null;
        $body = (string) ($value['text'] ?? $value['message'] ?? '');

        $profile->instagramMessages()->create([
            'igsid' => $authorId !== '' ? $authorId : null,
            'contact_name' => $authorName,
            'channel' => 'comment',
            'direction' => 'inbound',
            'body' => $body,
            'status' => 'received',
            'external_id' => $commentId,
            'meta' => $value,
        ]);

        $this->sendCommentDm($profile, $commentId, $authorId, $authorName, $body);

        $automation = $profile->instagramAutomations()->firstOrCreate(
            ['key' => 'comment'],
            [
                'is_enabled' => true,
                'message' => InstagramAutomation::DEFAULTS['comment']['message'],
            ],
        );

        if (! $automation->is_enabled) {
            return;
        }

        if (filled($automation->keywords) && ! $this->keywordsMatch($automation->keywords, $body)) {
            return;
        }

        $reply = $this->render($profile, $automation->message, $authorName);

        if ($reply === '') {
            return;
        }

        $message = app(InstagramClient::class)->replyToComment(
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
        $automation = $profile->instagramAutomations()->firstOrCreate(
            ['key' => 'comment_dm'],
            [
                'is_enabled' => true,
                'message' => InstagramAutomation::DEFAULTS['comment_dm']['message'],
            ],
        );

        if (! $automation->is_enabled || ! $this->keywordsMatch($automation->keywords, $body)) {
            return;
        }

        $reply = $this->render($profile, $automation->message, $authorName);

        if ($reply === '') {
            return;
        }

        $message = app(InstagramClient::class)->sendPrivateReply(
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
    public function welcome(Profile $profile, string $igsid, ?string $name = null): void
    {
        if (! $profile->user?->hasToolAccess('instagram')) {
            return;
        }

        $automation = $profile->instagramAutomations()->firstOrCreate(
            ['key' => 'welcome'],
            [
                'is_enabled' => true,
                'message' => InstagramAutomation::DEFAULTS['welcome']['message'],
            ],
        );

        if (! $automation->is_enabled) {
            return;
        }

        $body = $this->render($profile, $automation->message, $name);

        if ($body === '') {
            return;
        }

        $message = app(InstagramClient::class)->sendText($profile, $igsid, $body, $name);

        $this->record($profile, 'welcome', $message, 'new_conversation', $igsid, $name);
    }

    /**
     * Reply with the first enabled keyword rule that matches the message.
     */
    public function keywordReply(Profile $profile, string $igsid, string $body): bool
    {
        if (! $profile->user?->hasToolAccess('instagram')) {
            return false;
        }

        $rules = $profile->instagramAutomations()
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

            $message = app(InstagramClient::class)->sendText($profile, $igsid, $reply);

            $this->record($profile, 'keyword', $message, 'inbound_message', $igsid, null, $rule->name);

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
    private function record(Profile $profile, string $key, ?InstagramMessage $message, string $trigger, ?string $contact, ?string $contactName, ?string $rule = null): void
    {
        app(AutomationLogger::class)->record($profile, 'instagram', $key, match (true) {
            $message === null => AutomationActivity::STATUS_SKIPPED,
            $message->status === 'failed' => AutomationActivity::STATUS_FAILED,
            default => AutomationActivity::STATUS_SENT,
        }, [
            'trigger' => $trigger,
            'contact' => $contact,
            'contact_name' => $contactName,
            'rule' => $rule,
            'detail' => $message === null
                ? (string) __('Instagram is not connected.')
                : (is_string($message->error) ? $message->error : null),
        ]);
    }

    /**
     * Replace the placeholders available to Instagram automations.
     */
    private function render(Profile $profile, ?string $message, ?string $customer): string
    {
        $username = $profile->user?->instagram_username;

        return trim(strtr((string) $message, [
            '{customer}' => $customer ?: __('there'),
            '{account}' => filled($username) ? '@'.$username : $profile->restaurantName(),
        ]));
    }
}
