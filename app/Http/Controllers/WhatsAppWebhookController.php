<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use App\Models\User;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppMessage;
use App\Services\WhatsAppNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class WhatsAppWebhookController extends Controller
{
    /**
     * Answer Meta's webhook verification challenge.
     */
    public function verify(Request $request): Response
    {
        $token = trim((string) setting('whatsapp_verify_token'));

        abort_if($token === '', 403);

        abort_unless(
            $request->query('hub_mode') === 'subscribe'
            && hash_equals($token, (string) $request->query('hub_verify_token')),
            403,
        );

        return response((string) $request->query('hub_challenge'), 200)
            ->header('Content-Type', 'text/plain');
    }

    /**
     * Store inbound messages and delivery status updates.
     */
    public function handle(Request $request): JsonResponse
    {
        $secret = trim((string) setting('whatsapp_app_secret'));

        if ($secret === '' || ! $this->signatureIsValid($request, $secret)) {
            return response()->json(['message' => 'Invalid signature.'], Response::HTTP_UNAUTHORIZED);
        }

        foreach ((array) $request->input('entry', []) as $entry) {
            foreach ((array) ($entry['changes'] ?? []) as $change) {
                $value = (array) ($change['value'] ?? []);

                $phoneNumberId = (string) ($value['metadata']['phone_number_id'] ?? '');
                $profile = $this->profileFor($phoneNumberId);

                foreach ((array) ($value['messages'] ?? []) as $message) {
                    if ($profile instanceof Profile && $profile->user?->hasToolAccess('whatsapp')) {
                        $this->storeInbound($profile, $value, (array) $message);
                    }
                }

                foreach ((array) ($value['statuses'] ?? []) as $status) {
                    $this->applyStatus((array) $status);
                }
            }
        }

        return response()->json(['message' => 'ok']);
    }

    /**
     * Find the workspace connected to a WhatsApp phone number ID.
     */
    private function profileFor(string $phoneNumberId): ?Profile
    {
        if ($phoneNumberId === '') {
            return null;
        }

        return User::query()
            ->where('whatsapp_phone_number_id', $phoneNumberId)
            ->first()
            ?->workspaceProfile();
    }

    /**
     * Persist an inbound message and open a contact if needed.
     *
     * @param  array<string, mixed>  $value
     * @param  array<string, mixed>  $message
     */
    private function storeInbound(Profile $profile, array $value, array $message): void
    {
        $from = WhatsAppContact::normalisePhone((string) ($message['from'] ?? ''));

        if ($from === '') {
            return;
        }

        $name = $value['contacts'][0]['profile']['name'] ?? null;
        $type = (string) ($message['type'] ?? 'text');

        $body = $message['text']['body']
            ?? $message['button']['text']
            ?? $message['interactive']['button_reply']['title']
            ?? $message['interactive']['list_reply']['title']
            ?? null;

        $contact = $profile->whatsappContacts()->firstOrCreate(
            ['phone' => $from],
            [
                'name' => is_string($name) ? $name : null,
                'source' => 'whatsapp',
                'opted_in' => true,
            ],
        );

        $attributes = ['last_messaged_at' => now()];

        if (is_string($name) && $name !== '' && blank($contact->name)) {
            $attributes['name'] = $name;
        }

        $contact->update($attributes);

        $profile->whatsappMessages()->create([
            'contact_phone' => $from,
            'contact_name' => $contact->name,
            'direction' => 'inbound',
            'message_type' => $type,
            'body' => is_string($body) ? $body : null,
            'status' => 'received',
            'external_id' => isset($message['id']) ? (string) $message['id'] : null,
            'meta' => $message,
        ]);

        if (is_string($body) && $body !== '') {
            $notifier = app(WhatsAppNotifier::class);

            if ($notifier->keywordReply($profile, $from, $body, is_string($name) ? $name : null)) {
                return;
            }
        }

        if ($contact->wasRecentlyCreated) {
            app(WhatsAppNotifier::class)->welcome(
                $profile,
                $from,
                is_string($name) ? $name : null,
            );
        }
    }

    /**
     * Apply a delivery status update to the matching message.
     *
     * @param  array<string, mixed>  $status
     */
    private function applyStatus(array $status): void
    {
        $externalId = (string) ($status['id'] ?? '');

        if ($externalId === '') {
            return;
        }

        $message = WhatsAppMessage::query()
            ->where('external_id', $externalId)
            ->first();

        if (! $message instanceof WhatsAppMessage) {
            return;
        }

        $state = strtolower((string) ($status['status'] ?? ''));

        $values = match ($state) {
            'delivered' => ['status' => 'delivered'],
            'read' => ['status' => 'read'],
            'failed' => [
                'status' => 'failed',
                'error' => (string) ($status['errors'][0]['title'] ?? 'WhatsApp reported a failed message.'),
            ],
            default => ['status' => 'sent'],
        };

        $message->update($values);
    }

    /**
     * Verify Meta's HMAC signature over the raw request body.
     */
    private function signatureIsValid(Request $request, string $secret): bool
    {
        $signature = (string) $request->header('X-Hub-Signature-256');

        if (! str_starts_with($signature, 'sha256=')) {
            return false;
        }

        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $signature);
    }
}
