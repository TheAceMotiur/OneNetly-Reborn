<?php

namespace App\Services;

use App\Models\FacebookMessage;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class FacebookClient
{
    /**
     * Determine whether the account can send Facebook messages right now.
     */
    public function isConfigured(?User $account): bool
    {
        return $account instanceof User
            && (bool) setting('facebook_enabled', true)
            && $account->facebook_enabled
            && filled($account->facebook_page_id)
            && filled($account->facebook_page_access_token);
    }

    /**
     * Send a Messenger text message to a page-scoped user ID.
     */
    public function sendText(Profile $profile, string $psid, string $body, ?string $contactName = null): ?FacebookMessage
    {
        $account = $profile->user;

        if (! $account instanceof User || ! $this->isConfigured($account) || trim($psid) === '' || trim($body) === '') {
            return null;
        }

        $message = $profile->facebookMessages()->create([
            'psid' => $psid,
            'contact_name' => $contactName,
            'channel' => 'messenger',
            'direction' => 'outbound',
            'body' => $body,
            'status' => 'queued',
        ]);

        return $this->deliver($account, $message, $this->baseUrl().'/'.rawurlencode((string) $account->facebook_page_id).'/messages', [
            'recipient' => ['id' => $psid],
            'messaging_type' => 'RESPONSE',
            'message' => ['text' => $body],
        ]);
    }

    /**
     * Reply publicly to a Page post comment.
     */
    public function replyToComment(Profile $profile, string $commentId, string $body, ?string $psid = null, ?string $contactName = null): ?FacebookMessage
    {
        $account = $profile->user;

        if (! $account instanceof User || ! $this->isConfigured($account) || trim($commentId) === '' || trim($body) === '') {
            return null;
        }

        $message = $profile->facebookMessages()->create([
            'psid' => $psid,
            'contact_name' => $contactName,
            'channel' => 'comment',
            'direction' => 'outbound',
            'body' => $body,
            'status' => 'queued',
        ]);

        return $this->deliver($account, $message, $this->baseUrl().'/'.rawurlencode($commentId).'/comments', [
            'message' => $body,
        ]);
    }

    /**
     * Send a private reply to a Page post comment.
     */
    public function sendPrivateReply(Profile $profile, string $commentId, string $body, ?string $psid = null, ?string $contactName = null): ?FacebookMessage
    {
        $account = $profile->user;

        if (! $account instanceof User || ! $this->isConfigured($account) || trim($commentId) === '' || trim($body) === '') {
            return null;
        }

        $message = $profile->facebookMessages()->create([
            'psid' => $psid,
            'contact_name' => $contactName,
            'channel' => 'private_reply',
            'direction' => 'outbound',
            'body' => $body,
            'status' => 'queued',
        ]);

        return $this->deliver($account, $message, $this->baseUrl().'/'.rawurlencode($commentId).'/private_replies', [
            'message' => $body,
        ]);
    }

    /**
     * Check the saved Page credentials with the Graph API.
     *
     * @return array{ok: bool, message: string}
     */
    public function verify(User $account): array
    {
        if (! $this->isConfigured($account)) {
            return [
                'ok' => false,
                'message' => __('Enable Facebook and save your Page ID and access token first.'),
            ];
        }

        try {
            $response = Http::withToken((string) $account->facebook_page_access_token)
                ->acceptJson()
                ->timeout(15)
                ->get($this->baseUrl().'/'.rawurlencode((string) $account->facebook_page_id), [
                    'fields' => 'id,name',
                ]);

            if ($response->successful()) {
                return [
                    'ok' => true,
                    'message' => __('Connected to :page.', [
                        'page' => (string) ($response->json('name') ?? $account->facebook_page_name ?? 'Facebook Page'),
                    ]),
                ];
            }

            return [
                'ok' => false,
                'message' => (string) ($response->json('error.message') ?? __('Facebook rejected the connection test.')),
            ];
        } catch (Throwable $exception) {
            report($exception);

            return [
                'ok' => false,
                'message' => __('Could not reach Facebook. Check the connection and try again.'),
            ];
        }
    }

    /**
     * Send a request and persist the result on the stored message.
     *
     * @param  array<string, mixed>  $payload
     */
    private function deliver(User $account, FacebookMessage $message, string $url, array $payload): FacebookMessage
    {
        try {
            $response = Http::withToken((string) $account->facebook_page_access_token)
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
                    'error' => Str::limit((string) ($response->json('error.message') ?? 'Facebook rejected the message.'), 490),
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
        $version = (string) (setting('facebook_graph_version') ?: 'v21.0');

        return 'https://graph.facebook.com/'.trim($version, '/');
    }
}
