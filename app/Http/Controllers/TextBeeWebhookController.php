<?php

namespace App\Http\Controllers;

use App\Models\SmsMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TextBeeWebhookController extends Controller
{
    /**
     * Apply a TextBee delivery event to the matching message.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $secret = trim((string) setting('textbee_webhook_secret'));

        if ($secret === '' || ! $this->signatureIsValid($request, $secret)) {
            return response()->json(['message' => 'Invalid signature.'], Response::HTTP_UNAUTHORIZED);
        }

        $payload = $request->json()->all();
        $status = strtolower((string) ($payload['status'] ?? ''));
        $recipient = preg_replace('/[^\d+]/', '', (string) ($payload['recipient'] ?? '')) ?? '';
        $batchId = (string) ($payload['smsBatchId'] ?? '');

        $message = SmsMessage::query()
            ->when($batchId !== '', fn ($query) => $query->where('sms_batch_id', $batchId))
            ->when($recipient !== '', fn ($query) => $query->where('recipient', $recipient))
            ->latest('id')
            ->first();

        if ($message === null) {
            return response()->json(['message' => 'Message not found.']);
        }

        $message->update(match ($status) {
            'delivered' => ['status' => 'delivered', 'delivered_at' => now()],
            'failed' => ['status' => 'failed', 'error' => (string) ($payload['error'] ?? 'TextBee reported a failed message.')],
            default => ['status' => 'sent', 'sent_at' => $message->sent_at ?? now()],
        });

        return response()->json(['message' => 'ok']);
    }

    /**
     * Verify TextBee's timestamped HMAC signature over the raw body.
     */
    private function signatureIsValid(Request $request, string $secret): bool
    {
        $header = (string) $request->header('X-Textbee-Signature');
        $timestamp = null;
        $signature = null;

        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');

            if ($key === 't') {
                $timestamp = $value;
            }

            if ($key === 'v1') {
                $signature = $value;
            }
        }

        if ($signature === null) {
            return false;
        }

        if ($timestamp !== null && abs((int) now()->timestamp - (int) $timestamp) > 300) {
            return false;
        }

        $signed = $timestamp !== null
            ? $timestamp.'.'.$request->getContent()
            : $request->getContent();

        return hash_equals(hash_hmac('sha256', $signed, $secret), $signature);
    }
}
