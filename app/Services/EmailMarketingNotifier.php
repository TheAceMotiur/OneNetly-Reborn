<?php

namespace App\Services;

use App\Mail\SubscriberBroadcast;
use App\Models\AutomationActivity;
use App\Models\EmailAutomation;
use App\Models\Subscriber;
use Illuminate\Support\Facades\Mail;
use Throwable;

class EmailMarketingNotifier
{
    /**
     * Send the welcome automation to a new subscriber.
     */
    public function welcome(Subscriber $subscriber): void
    {
        $subscriber->loadMissing('profile');
        $profile = $subscriber->profile;

        if (! $profile->user->email_marketing_enabled || ! $profile->user->hasToolAccess('email')) {
            return;
        }

        $automation = $profile->emailAutomations()->firstOrCreate(
            ['key' => 'welcome'],
            [
                'is_enabled' => true,
                'subject' => EmailAutomation::DEFAULTS['welcome']['subject'],
                'body' => EmailAutomation::DEFAULTS['welcome']['body'],
            ],
        );

        if (! $automation->is_enabled) {
            return;
        }

        $replacements = [
            '{email}' => $subscriber->email,
            '{page}' => $profile->restaurantName(),
        ];

        $subject = trim(strtr((string) ($automation->subject ?? ''), $replacements));
        $body = trim(strtr((string) ($automation->body ?? ''), $replacements));

        if ($subject === '' || $body === '') {
            return;
        }

        $logger = app(AutomationLogger::class);

        try {
            Mail::to($subscriber->email)->send(new SubscriberBroadcast(
                $profile,
                $subject,
                $body,
                route('subscribers.unsubscribe', $subscriber->token),
                (string) ($profile->user->email_footer ?? ''),
            ));

            $logger->record($profile, 'email', 'welcome', AutomationActivity::STATUS_SENT, [
                'trigger' => 'subscriber_created',
                'contact' => $subscriber->email,
            ]);
        } catch (Throwable $exception) {
            report($exception);

            $logger->record($profile, 'email', 'welcome', AutomationActivity::STATUS_FAILED, [
                'trigger' => 'subscriber_created',
                'contact' => $subscriber->email,
                'detail' => $exception->getMessage(),
            ]);
        }
    }
}
