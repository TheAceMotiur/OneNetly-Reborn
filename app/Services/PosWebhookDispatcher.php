<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Profile;
use App\Models\Reservation;
use App\Models\WebhookDelivery;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class PosWebhookDispatcher
{
    /**
     * Send the order.created event to the restaurant's POS webhook.
     */
    public function orderCreated(Order $order): ?WebhookDelivery
    {
        $order->loadMissing(['profile', 'items']);

        return $this->send($order->profile, 'order.created', $this->orderPayload($order), $order);
    }

    /**
     * Send the order.updated event after a status change.
     */
    public function orderUpdated(Order $order, ?string $previousStatus = null): ?WebhookDelivery
    {
        $order->loadMissing(['profile', 'items']);

        return $this->send(
            $order->profile,
            'order.updated',
            $this->orderPayload($order, 'order.updated', $previousStatus),
            $order,
        );
    }

    /**
     * Send the reservation.created event.
     */
    public function reservationCreated(Reservation $reservation): ?WebhookDelivery
    {
        $reservation->loadMissing('profile');

        return $this->send(
            $reservation->profile,
            'reservation.created',
            $this->reservationPayload($reservation, 'reservation.created'),
        );
    }

    /**
     * Send the reservation.updated event after a status change.
     */
    public function reservationUpdated(Reservation $reservation, ?string $previousStatus = null): ?WebhookDelivery
    {
        $reservation->loadMissing('profile');

        return $this->send(
            $reservation->profile,
            'reservation.updated',
            $this->reservationPayload($reservation, 'reservation.updated', $previousStatus),
        );
    }

    /**
     * Send a ping so the team can confirm the webhook is reachable.
     */
    public function test(Profile $profile): WebhookDelivery
    {
        return $this->send($profile, 'ping', [
            'event' => 'ping',
            'sent_at' => now()->toIso8601String(),
            'restaurant' => $this->restaurantPayload($profile),
        ]);
    }

    /**
     * Post a signed JSON payload and record every attempt.
     *
     * @param  array<string, mixed>  $payload
     */
    public function send(Profile $profile, string $event, array $payload, ?Order $order = null): WebhookDelivery
    {
        $url = trim((string) $profile->pos_webhook_url);

        $delivery = $profile->webhookDeliveries()->create([
            'order_id' => $order?->id,
            'event' => $event,
            'url' => $url,
            'success' => false,
            'attempts' => 0,
        ]);

        if ($url === '') {
            $delivery->update(['error' => __('No webhook URL configured.')]);

            return $delivery;
        }

        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $body, (string) $profile->pos_webhook_secret);

        $status = null;
        $error = null;
        $attempts = 0;

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $attempts = $attempt;
            $status = null;
            $error = null;

            try {
                $response = Http::timeout(4)
                    ->withHeaders([
                        'X-OneNetly-Event' => $event,
                        'X-OneNetly-Signature' => $signature,
                        'X-OneNetly-Delivery' => (string) $delivery->id,
                    ])
                    ->withBody($body, 'application/json')
                    ->post($url);

                $status = $response->status();

                if ($response->successful()) {
                    $delivery->update([
                        'success' => true,
                        'response_status' => $status,
                        'attempts' => $attempts,
                        'error' => null,
                    ]);

                    return $delivery->refresh();
                }

                $error = 'HTTP '.$status;
            } catch (Throwable $exception) {
                $error = $exception->getMessage();
            }

            if ($attempt === 1) {
                usleep(300_000);
            }
        }

        $delivery->update([
            'success' => false,
            'response_status' => $status,
            'attempts' => $attempts,
            'error' => filled($error) ? Str::limit((string) $error, 900) : null,
        ]);

        return $delivery->refresh();
    }

    /**
     * Build the payload sent when an order is placed.
     *
     * @return array<string, mixed>
     */
    private function orderPayload(Order $order, string $event = 'order.created', ?string $previousStatus = null): array
    {
        return [
            'event' => $event,
            'sent_at' => now()->toIso8601String(),
            ...($previousStatus !== null ? ['previous_status' => $previousStatus] : []),
            'restaurant' => $this->restaurantPayload($order->profile),
            'order' => [
                'id' => $order->id,
                'reference' => $order->reference,
                'status' => $order->status->value,
                'status_label' => $order->status->label(),
                'type' => $order->order_type,
                'type_label' => $order->typeLabel(),
                'table_number' => $order->table_number,
                'delivery_address' => $order->delivery_address,
                'coupon_code' => $order->coupon_code,
                'customer_name' => $order->customer_name,
                'customer_phone' => $order->customer_phone,
                'customer_email' => $order->customer_email,
                'notes' => $order->notes,
                'subtotal_cents' => $order->subtotal_cents,
                'discount_cents' => $order->discount_cents,
                'points_redeemed' => $order->points_redeemed,
                'loyalty_discount_cents' => $order->loyalty_discount_cents,
                'delivery_fee_cents' => $order->delivery_fee_cents,
                'service_cents' => $order->service_cents,
                'tax_cents' => $order->tax_cents,
                'tip_cents' => $order->tip_cents,
                'total_cents' => $order->total_cents,
                'currency' => $order->currency,
                'created_at' => $order->created_at?->toIso8601String(),
                'updated_at' => $order->updated_at?->toIso8601String(),
                'items' => $order->items->map(fn ($item): array => [
                    'menu_item_id' => $item->menu_item_id,
                    'name' => $item->name,
                    'quantity' => $item->quantity,
                    'unit_price_cents' => $item->unit_price_cents,
                    'line_total_cents' => $item->line_total_cents,
                    'notes' => $item->notes,
                    'options' => $item->options ?? [],
                ])->all(),
            ],
        ];
    }

    /**
     * Build the payload for reservation events.
     *
     * @return array<string, mixed>
     */
    private function reservationPayload(Reservation $reservation, string $event, ?string $previousStatus = null): array
    {
        return [
            'event' => $event,
            'sent_at' => now()->toIso8601String(),
            ...($previousStatus !== null ? ['previous_status' => $previousStatus] : []),
            'restaurant' => $this->restaurantPayload($reservation->profile),
            'reservation' => [
                'id' => $reservation->id,
                'name' => $reservation->name,
                'phone' => $reservation->phone,
                'email' => $reservation->email,
                'party_size' => $reservation->party_size,
                'date' => $reservation->reservation_date->toDateString(),
                'time' => $reservation->reservation_time,
                'notes' => $reservation->notes,
                'status' => $reservation->status,
                'status_label' => $reservation->statusLabel(),
                'created_at' => $reservation->created_at?->toIso8601String(),
            ],
        ];
    }

    /**
     * The restaurant details included with every event.
     *
     * @return array<string, mixed>
     */
    private function restaurantPayload(Profile $profile): array
    {
        return [
            'username' => $profile->username,
            'name' => $profile->restaurantName(),
            'currency' => $profile->menu_currency,
            'menu_url' => route('restaurant.show', $profile->username),
        ];
    }
}
