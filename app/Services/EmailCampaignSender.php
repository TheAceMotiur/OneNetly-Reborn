<?php

namespace App\Services;

use App\Mail\SubscriberBroadcast;
use App\Models\EmailCampaign;
use App\Models\Subscriber;
use Illuminate\Support\Facades\Mail;
use Throwable;

class EmailCampaignSender
{
    /**
     * Send the campaign to every subscriber on the page.
     */
    public function send(EmailCampaign $campaign): EmailCampaign
    {
        $campaign->loadMissing('profile');
        $profile = $campaign->profile;

        $sent = 0;
        $failed = 0;

        $profile->subscribers()->orderBy('id')->each(function (Subscriber $subscriber) use ($campaign, $profile, &$sent, &$failed): void {
            try {
                Mail::to($subscriber->email)->send(new SubscriberBroadcast(
                    $profile,
                    $campaign->subject,
                    $campaign->body,
                    route('subscribers.unsubscribe', $subscriber->token),
                    (string) ($profile->user->email_footer ?? ''),
                ));

                $sent++;
            } catch (Throwable $exception) {
                report($exception);

                $failed++;
            }
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
