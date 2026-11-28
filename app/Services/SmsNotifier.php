<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Profile;
use App\Models\Reservation;
use Illuminate\Support\Str;

class SmsNotifier
{
    public function __construct(private readonly TextBeeClient $client) {}

    /**
     * Send a guest the status of their order.
     */
    public function orderStatusChanged(Order $order, ?string $previousStatus = null): void
    {
        $order->loadMissing('profile');

        if ($previousStatus !== null && $previousStatus === $order->status->value) {
            return;
        }

        $this->sendForProfile(
            $order->profile,
            $order->customer_phone,
            __(':restaurant: order :reference is now :status. Track it at :url', [
                'restaurant' => $order->profile->restaurantName(),
                'reference' => $order->reference,
                'status' => strtolower($order->status->label()),
                'url' => route('restaurant.track', [$order->profile->username, $order->reference]),
            ]),
            $order,
        );
    }

    /**
     * Send a guest confirmation that their order was received.
     */
    public function orderPlaced(Order $order): void
    {
        $order->loadMissing('profile');

        $this->sendForProfile(
            $order->profile,
            $order->customer_phone,
            __(':restaurant received order :reference. Track it at :url', [
                'restaurant' => $order->profile->restaurantName(),
                'reference' => $order->reference,
                'url' => route('restaurant.track', [$order->profile->username, $order->reference]),
            ]),
            $order,
        );
    }

    /**
     * Send a guest the status of their table booking.
     */
    public function reservationStatusChanged(Reservation $reservation, ?string $previousStatus = null): void
    {
        $reservation->loadMissing('profile');

        if ($previousStatus !== null && $previousStatus === $reservation->status) {
            return;
        }

        $this->sendForProfile(
            $reservation->profile,
            $reservation->phone,
            __('Hi :name, your table for :party at :restaurant on :date at :time is :status.', [
                'name' => $reservation->name,
                'party' => $reservation->party_size,
                'restaurant' => $reservation->profile->restaurantName(),
                'date' => $reservation->reservation_date->format('j M'),
                'time' => $reservation->reservation_time,
                'status' => strtolower($reservation->statusLabel()),
            ]),
            null,
            $reservation,
        );
    }

    /**
     * Remind a guest about an upcoming confirmed booking.
     */
    public function reservationReminder(Reservation $reservation): void
    {
        $reservation->loadMissing('profile');

        $this->sendForProfile(
            $reservation->profile,
            $reservation->phone,
            __('Reminder: your table for :party at :restaurant is on :date at :time. Reply to this message if you need to change it.', [
                'party' => $reservation->party_size,
                'restaurant' => $reservation->profile->restaurantName(),
                'date' => $reservation->reservation_date->format('j M'),
                'time' => $reservation->reservation_time,
            ]),
            null,
            $reservation,
        );
    }

    /**
     * Send only when SMS is enabled for the restaurant and the guest.
     */
    private function sendForProfile(
        ?Profile $profile,
        ?string $recipient,
        string $body,
        ?Order $order = null,
        ?Reservation $reservation = null,
    ): void {
        if (! setting('sms_enabled') || ! $profile?->sms_notifications_enabled || blank($recipient)) {
            return;
        }

        $this->client->send(Str::limit(trim((string) $recipient), 40, ''), $body, $profile, $order, $reservation);
    }
}
