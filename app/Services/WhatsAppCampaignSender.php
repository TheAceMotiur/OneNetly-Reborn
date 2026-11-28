<?php

namespace App\Services;

use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppContact;

class WhatsAppCampaignSender
{
    /**
     * Send the campaign to all opted-in contacts and record the result.
     */
    public function send(WhatsAppCampaign $campaign): WhatsAppCampaign
    {
        $campaign->loadMissing('profile');
        $profile = $campaign->profile;
        $client = app(WhatsAppClient::class);

        if (! $client->isConfigured($profile->user)) {
            $campaign->update([
                'status' => 'failed',
                'sent_at' => now(),
            ]);

            return $campaign->refresh();
        }

        $sent = 0;
        $failed = 0;

        $profile->whatsappContacts()
            ->where('opted_in', true)
            ->orderBy('id')
            ->each(function (WhatsAppContact $contact) use ($campaign, $client, $profile, &$sent, &$failed): void {
                $name = $contact->name ?: __('there');

                if (filled($campaign->template_name)) {
                    $message = $client->sendTemplate(
                        $profile,
                        $contact->phone,
                        (string) $campaign->template_name,
                        $campaign->language ?: 'en_US',
                        [$name],
                        $contact->name,
                    );
                } else {
                    $body = strtr((string) ($campaign->message ?? ''), [
                        '{name}' => $name,
                        '{restaurant}' => $profile->restaurantName(),
                    ]);

                    if (trim($body) === '') {
                        return;
                    }

                    $message = $client->sendText($profile, $contact->phone, $body, $contact->name);
                }

                $message?->wasSent() ? $sent++ : $failed++;

                $contact->update(['last_messaged_at' => now()]);
            });

        $campaign->update([
            'status' => $sent === 0 && $failed > 0 ? 'failed' : 'sent',
            'sent_count' => $sent,
            'failed_count' => $failed,
            'sent_at' => now(),
        ]);

        return $campaign->refresh();
    }
}
