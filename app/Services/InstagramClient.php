<?php

namespace App\Services;

use App\Models\InstagramMessage;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class InstagramClient
{
    /**
     * Determine whether the account can send Instagram messages right now.
     */
    public function isConfigured(?User $account): bool
    {
        return $account instanceof User
            && (bool) setting('instagram_enabled', true)
            && $account->instagram_enabled
            && filled($account->instagram_account_id)
            && filled($account->instagram_access_token);
    }

    /**
     * Send a direct message to an Instagram-scoped ID.
     */
    public function sendText(Profile $profile, string $igsid, string $body, ?string $contactName = null): ?InstagramMessage
    {
        $account = $profile->user;

        if (! $account instanceof User || ! $this->isConfigured($account) || trim($igsid) === '' || trim($body) === '') {
            return null;
        }

        $message = $profile->instagramMessages()->create([
            'igsid' => $igsid,
            'contact_name' => $contactName,
            'channel' => 'messenger',
            'direction' => 'outbound',
            'body' => $body,
            'status' => 'queued',
        ]);

        return $this->deliver($account, $message, $this->baseUrl().'/'.rawurlencode((string) $account->instagram_account_id).'/messages', [
            'recipient' => ['id' => $igsid],
            'message' => ['text' => $body],
        ]);
    }

    /**
     * Reply publicly to an Instagram comment.
     */
    public function replyToComment(Profile $profile, string $commentId, string $body, ?string $igsid = null, ?string $contactName = null): ?InstagramMessage
    {
        $account = $profile->user;

        if (! $account instanceof User || ! $this->isConfigured($account) || trim($commentId) === '' || trim($body) === '') {
            return null;
        }

        $message = $profile->instagramMessages()->create([
            'igsid' => $igsid,
            'contact_name' => $contactName,
            'channel' => 'comment',
            'direction' => 'outbound',
            'body' => $body,
            'status' => 'queued',
        ]);

        return $this->deliver($account, $message, $this->baseUrl().'/'.rawurlencode($commentId).'/replies', [
            'message' => $body,
        ]);
    }

    /**
     * Send a private reply to an Instagram comment.
     */
    public function sendPrivateReply(Profile $profile, string $commentId, string $body, ?string $igsid = null, ?string $contactName = null): ?InstagramMessage
    {
        $account = $profile->user;

        if (! $account instanceof User || ! $this->isConfigured($account) || trim($commentId) === '' || trim($body) === '') {
            return null;
        }

        $message = $profile->instagramMessages()->create([
            'igsid' => $igsid,
            'contact_name' => $contactName,
            'channel' => 'private_reply',
            'direction' => 'outbound',
            'body' => $body,
            'status' => 'queued',
        ]);

        return $this->deliver($account, $message, $this->baseUrl().'/'.rawurlencode((string) $account->instagram_account_id).'/messages', [
            'recipient' => ['comment_id' => $commentId],
            'message' => ['text' => $body],
        ]);
    }

    /**
     * Check the saved Instagram credentials with the Graph API.
     *
     * @return array{ok: bool, message: string}
     */
    public function verify(User $account): array
    {
        if (! $this->isConfigured($account)) {
            return [
                'ok' => false,
                'message' => __('Enable Instagram and save your account ID and access token first.'),
            ];
        }

        try {
            $response = Http::withToken((string) $account->instagram_access_token)
                ->acceptJson()
                ->timeout(15)
                ->get($this->baseUrl().'/'.rawurlencode((string) $account->instagram_account_id), [
                    'fields' => 'id,username',
                ]);

            if ($response->successful()) {
                return [
                    'ok' => true,
                    'message' => __('Connected to :account.', [
                        'account' => '@'.(string) ($response->json('username') ?? $account->instagram_username ?? 'instagram'),
                    ]),
                ];
            }

            return [
                'ok' => false,
                'message' => (string) ($response->json('error.message') ?? __('Instagram rejected the connection test.')),
            ];
        } catch (Throwable $exception) {
            report($exception);

            return [
                'ok' => false,
                'message' => __('Could not reach Instagram. Check the connection and try again.'),
            ];
        }
    }

    /**
     * Send a request and persist the result on the stored message.
     *
     * @param  array<string, mixed>  $payload
     */
    private function deliver(User $account, InstagramMessage $message, string $url, array $payload): InstagramMessage
    {
        try {
            $response = Http::withToken((string) $account->instagram_access_token)
                ->acceptJson()
                ->timeout(15)
                ->post($url, $payload);

            if ($response->successful()) {
                $message->update([
                    'status' => 'sent',
                    'external_id' => $response->json('message_id') ?? $response->json('id'),
                    'meta' => $response->json(),
                ]);
            } else {
                $message->update([
                    'status' => 'failed',
                    'error' => Str::limit((string) ($response->json('error.message') ?? 'Instagram rejected the message.'), 490),
                    'meta' => $response->json(),
                ]);
            }
        } catch (Throwable $exception) {
            report($exception);

            $message->update([
                'status' => 'failed',
                'error' => Str::limit($exception->getMessage(), 490),
            ]);
        }

        return $message->refresh();
    }

    /**
     * The Graph API base URL for the configured version.
     */
    private function baseUrl(): string
    {
        $version = (string) (setting('instagram_graph_version') ?: 'v21.0');

        return 'https://graph.facebook.com/'.trim($version, '/');
    }
}
