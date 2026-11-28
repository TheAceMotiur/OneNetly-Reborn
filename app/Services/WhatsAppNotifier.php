<?php

namespace App\Services;

use App\Models\AutomationActivity;
use App\Models\Order;
use App\Models\Profile;
use App\Models\Reservation;
use App\Models\WhatsAppAutomation;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppMessage;
use Illuminate\Support\Str;

class WhatsAppNotifier
{
    /**
     * Send the order received automation.
     */
    public function orderPlaced(Order $order): void
    {
        $order->loadMissing('profile');

        if (! $order->profile->user?->whatsapp_order_updates) {
            return;
        }

        $this->send($order->profile, 'order_received', 'order_placed', $order->customer_phone, $order->customer_name, $this->orderReplacements($order));
    }

    /**
     * Send the order status automation when the status changed.
     */
    public function orderStatusChanged(Order $order, ?string $previousStatus = null): void
    {
        $order->loadMissing('profile');

        if ($previousStatus !== null && $previousStatus === $order->status->value) {
            return;
        }

        if (! $order->profile->user?->whatsapp_order_updates) {
            return;
        }

        $this->send($order->profile, 'order_status', 'order_status', $order->customer_phone, $order->customer_name, $this->orderReplacements($order));
    }

    /**
     * Send the booking received automation.
     */
    public function reservationReceived(Reservation $reservation): void
    {
        $reservation->loadMissing('profile');

        if (! $reservation->profile->user?->whatsapp_reservation_updates) {
            return;
        }

        $this->send($reservation->profile, 'reservation_received', 'reservation_received', $reservation->phone, $reservation->name, $this->reservationReplacements($reservation));
    }

    /**
     * Send the booking status automation when the status changed.
     */
    public function reservationStatusChanged(Reservation $reservation, ?string $previousStatus = null): void
    {
        $reservation->loadMissing('profile');

        if ($previousStatus !== null && $previousStatus === $reservation->status) {
            return;
        }

        if (! $reservation->profile->user?->whatsapp_reservation_updates) {
            return;
        }

        $this->send($reservation->profile, 'reservation_status', 'reservation_status', $reservation->phone, $reservation->name, $this->reservationReplacements($reservation));
    }

    /**
     * Send the upcoming booking reminder.
     */
    public function reservationReminder(Reservation $reservation): void
    {
        $reservation->loadMissing('profile');

        if (! $reservation->profile->user?->whatsapp_reservation_updates) {
            return;
        }

        $this->send($reservation->profile, 'reservation_reminder', 'reservation_reminder', $reservation->phone, $reservation->name, $this->reservationReplacements($reservation));
    }

    /**
     * Send a welcome message to a brand new contact.
     */
    public function welcome(Profile $profile, string $phone, ?string $name = null): void
    {
        $this->send($profile, 'welcome', 'new_contact', $phone, $name, [
            '{customer}' => $name ?: __('there'),
            '{restaurant}' => $profile->restaurantName(),
        ]);
    }

    /**
     * Reply with the first enabled keyword rule that matches the message.
     */
    public function keywordReply(Profile $profile, string $phone, string $body, ?string $name = null): bool
    {
        if (! $profile->user?->hasToolAccess('whatsapp')) {
            return false;
        }

        $haystack = Str::lower($body);

        if (trim($haystack) === '') {
            return false;
        }

        $rules = $profile->whatsappAutomations()
            ->where('key', 'keyword')
            ->where('is_enabled', true)
            ->orderBy('id')
            ->get();

        foreach ($rules as $rule) {
            $keywords = array_filter(array_map(
                fn (string $keyword): string => Str::lower(trim($keyword)),
                explode(',', (string) $rule->keywords),
            ));

            foreach ($keywords as $keyword) {
                if (! str_contains($haystack, $keyword)) {
                    continue;
                }

                $reply = trim(strtr((string) $rule->message, [
                    '{customer}' => $name ?: __('there'),
                    '{restaurant}' => $profile->restaurantName(),
                ]));

                if ($reply === '') {
                    return false;
                }

                $message = app(WhatsAppClient::class)->sendText($profile, $phone, $reply, $name);

                $this->touchContact($profile, $phone, $name, $message);
                $this->log($profile, 'keyword', 'inbound_message', $message, $phone, $name, $rule->name);

                return true;
            }
        }

        return false;
    }

    /**
     * Resolve the automation, apply replacements and send it.
     *
     * @param  array<string, string>  $replacements
     */
    private function send(Profile $profile, string $key, string $trigger, ?string $phone, ?string $name, array $replacements): void
    {
        if (blank($phone) || ! isset(WhatsAppAutomation::DEFAULTS[$key])) {
            return;
        }

        if (! $profile->user?->hasToolAccess('whatsapp')) {
            return;
        }

        $automation = $profile->whatsappAutomations()->firstOrCreate(
            ['key' => $key],
            [
                'is_enabled' => true,
                'message' => WhatsAppAutomation::DEFAULTS[$key]['message'],
            ],
        );

        if (! $automation->is_enabled) {
            return;
        }

        $client = app(WhatsAppClient::class);

        if (! $client->isConfigured($profile->user)) {
            $this->log($profile, $key, $trigger, null, (string) $phone, $name);

            return;
        }

        if (filled($automation->template_name)) {
            $message = $client->sendTemplate(
                $profile,
                (string) $phone,
                (string) $automation->template_name,
                $automation->language ?: 'en_US',
                array_values($replacements),
                $name,
            );
        } else {
            $body = strtr((string) ($automation->message ?? ''), $replacements);

            if (trim($body) === '') {
                return;
            }

            $message = $client->sendText($profile, (string) $phone, $body, $name);
        }

        $this->touchContact($profile, (string) $phone, $name, $message);
        $this->log($profile, $key, $trigger, $message, (string) $phone, $name);
    }

    /**
     * Record the result of one automation run.
     */
    private function log(Profile $profile, string $key, string $trigger, ?WhatsAppMessage $message, string $contact, ?string $name, ?string $rule = null): void
    {
        app(AutomationLogger::class)->record($profile, 'whatsapp', $key, match (true) {
            $message === null => AutomationActivity::STATUS_SKIPPED,
            $message->status === 'failed' => AutomationActivity::STATUS_FAILED,
            default => AutomationActivity::STATUS_SENT,
        }, [
            'trigger' => $trigger,
            'contact' => $contact,
            'contact_name' => $name,
            'rule' => $rule,
            'detail' => $message === null
                ? (string) __('WhatsApp is not connected.')
                : (is_string($message->error) ? $message->error : null),
        ]);
    }

    /**
     * Keep the contact directory in sync when a message is sent.
     */
    private function touchContact(Profile $profile, string $phone, ?string $name, ?WhatsAppMessage $message): void
    {
        $normalised = WhatsAppContact::normalisePhone($phone);

        if ($normalised === '') {
            return;
        }

        $contact = $profile->whatsappContacts()->firstOrCreate(
            ['phone' => $normalised],
            [
                'name' => $name,
                'source' => 'automation',
                'opted_in' => true,
            ],
        );

        $attributes = ['last_messaged_at' => now()];

        if ($name !== null && blank($contact->name)) {
            $attributes['name'] = $name;
        }

        if ($message?->wasSent()) {
            $attributes['source'] = $contact->source ?: 'automation';
        }

        $contact->update($attributes);
    }

    /**
     * Placeholders available to order automations.
     *
     * @return array<string, string>
     */
    private function orderReplacements(Order $order): array
    {
        return [
            '{customer}' => $order->customer_name,
            '{restaurant}' => $order->profile->restaurantName(),
            '{reference}' => $order->reference,
            '{status}' => strtolower($order->status->label()),
            '{total}' => $order->displayTotal(),
            '{track_url}' => route('restaurant.track', [
                $order->profile->username,
                $order->reference,
            ]),
        ];
    }

    /**
     * Placeholders available to reservation automations.
     *
     * @return array<string, string>
     */
    private function reservationReplacements(Reservation $reservation): array
    {
        return [
            '{customer}' => $reservation->name,
            '{restaurant}' => $reservation->profile->restaurantName(),
            '{party}' => (string) $reservation->party_size,
            '{date}' => $reservation->reservation_date->format('j M'),
            '{time}' => $reservation->reservation_time,
            '{status}' => strtolower($reservation->statusLabel()),
        ];
    }
}
