<?php

namespace App\Services;

use App\Models\Profile;
use App\Models\User;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppMessage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class WhatsAppClient
{
    /**
     * Determine whether the account can send WhatsApp messages right now.
     */
    public function isConfigured(?User $account): bool
    {
        return $account instanceof User
            && (bool) setting('whatsapp_enabled', true)
            && $account->whatsapp_enabled
            && filled($account->whatsapp_phone_number_id)
            && filled($account->whatsapp_access_token);
    }

    /**
     * Send a free-form text message inside the 24 hour customer window.
     */
    public function sendText(Profile $profile, string $phone, string $body, ?string $contactName = null): ?WhatsAppMessage
    {
        return $this->send($profile, $phone, [
            'type' => 'text',
            'text' => [
                'preview_url' => true,
                'body' => $body,
            ],
        ], $body, null, $contactName);
    }

    /**
     * Send an approved template message, optionally with body parameters.
     *
     * @param  array<int, string|int|float>  $parameters
     */
    public function sendTemplate(
        Profile $profile,
        string $phone,
        string $template,
        string $language,
        array $parameters = [],
        ?string $contactName = null,
    ): ?WhatsAppMessage {
        $payload = [
            'type' => 'template',
            'template' => [
                'name' => $template,
                'language' => ['code' => $language !== '' ? $language : 'en_US'],
            ],
        ];

        if ($parameters !== []) {
            $payload['template']['components'] = [[
                'type' => 'body',
                'parameters' => array_map(
                    fn (string|int|float $value): array => ['type' => 'text', 'text' => (string) $value],
                    array_values($parameters),
                ),
            ]];
        }

        return $this->send($profile, $phone, $payload, null, $template, $contactName);
    }

    /**
     * Check the configured phone number with the Graph API.
     *
     * @return array{ok: bool, message: string}
     */
    public function verify(User $account): array
    {
        if (! $this->isConfigured($account)) {
            return [
                'ok' => false,
                'message' => __('Enable WhatsApp and save your phone number ID and access token first.'),
            ];
        }

        try {
            $response = Http::withToken((string) $account->whatsapp_access_token)
                ->acceptJson()
                ->timeout(15)
                ->get($this->baseUrl().'/'.rawurlencode((string) $account->whatsapp_phone_number_id), [
                    'fields' => 'display_phone_number,verified_name',
                ]);

            if ($response->successful()) {
                return [
                    'ok' => true,
                    'message' => __('Connected to :name (:number).', [
                        'name' => (string) ($response->json('verified_name') ?? 'WhatsApp'),
                        'number' => (string) ($response->json('display_phone_number') ?? $account->whatsapp_display_number),
                    ]),
                ];
            }

            return [
                'ok' => false,
                'message' => (string) ($response->json('error.message') ?? __('WhatsApp rejected the connection test.')),
            ];
        } catch (Throwable $exception) {
            report($exception);

            return [
                'ok' => false,
                'message' => __('Could not reach WhatsApp. Check the connection and try again.'),
            ];
        }
    }

    /**
     * Post one message payload and persist the result.
     *
     * @param  array<string, mixed>  $payload
     */
    private function send(
        Profile $profile,
        string $phone,
        array $payload,
        ?string $body,
        ?string $templateName,
        ?string $contactName,
    ): ?WhatsAppMessage {
        $account = $profile->user;

        if (! $account instanceof User || ! $this->isConfigured($account)) {
            return null;
        }

        $recipient = WhatsAppContact::normalisePhone($phone);

        if ($recipient === '') {
            return null;
        }

        $message = $profile->whatsappMessages()->create([
            'contact_phone' => $recipient,
            'contact_name' => $contactName,
            'direction' => 'outbound',
            'message_type' => $templateName !== null ? 'template' : 'text',
            'body' => $body,
            'template_name' => $templateName,
            'status' => 'queued',
        ]);

        try {
            $response = Http::withToken((string) $account->whatsapp_access_token)
                ->acceptJson()
                ->timeout(15)
                ->post($this->baseUrl().'/'.rawurlencode((string) $account->whatsapp_phone_number_id).'/messages', [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => $recipient,
                    ...$payload,
                ]);

            if ($response->successful()) {
                $message->update([
                    'status' => 'sent',
                    'external_id' => $response->json('messages.0.id'),
                    'meta' => $response->json(),
                ]);
            } else {
                $message->update([
                    'status' => 'failed',
                    'error' => Str::limit((string) ($response->json('error.message') ?? 'WhatsApp rejected the message.'), 490),
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
        $version = (string) (setting('whatsapp_api_version') ?: 'v21.0');

        return 'https://graph.facebook.com/'.trim($version, '/');
    }
}
