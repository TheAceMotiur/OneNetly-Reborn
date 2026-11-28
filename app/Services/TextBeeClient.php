<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Profile;
use App\Models\Reservation;
use App\Models\SmsMessage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class TextBeeClient
{
    /**
     * Send one SMS through TextBee and record the attempt.
     */
    public function send(
        string $recipient,
        string $body,
        ?Profile $profile = null,
        ?Order $order = null,
        ?Reservation $reservation = null,
    ): ?SmsMessage {
        $apiKey = trim((string) config('services.textbee.api_key'));
        $recipient = preg_replace('/[^\d+]/', '', trim($recipient)) ?? '';

        if ($apiKey === '' || $recipient === '') {
            return null;
        }

        $message = SmsMessage::query()->create([
            'profile_id' => $profile?->id,
            'order_id' => $order?->id,
            'reservation_id' => $reservation?->id,
            'recipient' => Str::limit(trim($recipient), 40, ''),
            'body' => $body,
            'status' => 'pending',
        ]);

        try {
            $response = Http::withHeaders(['x-api-key' => $apiKey])
                ->acceptJson()
                ->timeout(10)
                ->post(rtrim((string) config('services.textbee.base_url'), '/').'/gateway/send-sms', [
                    'recipients' => [$message->recipient],
                    'message' => $body,
                    ...(filled(config('services.textbee.device_id'))
                        ? ['deviceId' => (string) config('services.textbee.device_id')]
                        : []),
                ]);

            $batchId = $response->json('data.smsBatchId');

            if ($response->successful() && is_string($batchId)) {
                $message->update([
                    'status' => 'queued',
                    'sms_batch_id' => $batchId,
                    'sent_at' => now(),
                ]);

                return $message;
            }

            $message->update([
                'status' => 'failed',
                'error' => Str::limit(
                    (string) ($response->json('message') ?? $response->json('error') ?? 'TextBee rejected the message.'),
                    500,
                    '',
                ),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            $message->update([
                'status' => 'failed',
                'error' => Str::limit($exception->getMessage(), 500, ''),
            ]);
        }

        return $message;
    }
}
