<?php

namespace App\Console\Commands;

use App\Models\Reservation;
use App\Services\SmsNotifier;
use App\Services\WhatsAppNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

class SendReservationReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reservations:send-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send SMS reminders for confirmed reservations inside each restaurant lead time';

    /**
     * Execute the console command.
     */
    public function handle(SmsNotifier $sms): int
    {
        $sent = 0;

        Reservation::query()
            ->whereNull('reminder_sent_at')
            ->where('status', Reservation::STATUS_CONFIRMED)
            ->whereHas('profile', fn ($query) => $query->where('reservation_reminder_hours', '>', 0))
            ->with('profile')
            ->chunkById(100, function ($reservations) use ($sms, &$sent): void {
                foreach ($reservations as $reservation) {
                    $profile = $reservation->profile;
                    $timezone = $profile->timezone ?: (string) config('app.timezone');

                    $startsAt = Carbon::createFromFormat(
                        'Y-m-d H:i',
                        $reservation->reservation_date->toDateString().' '.substr($reservation->reservation_time, 0, 5),
                        $timezone,
                    );

                    $now = now();

                    if ($startsAt->isPast() || $startsAt->gt($now->copy()->addHours($profile->reservation_reminder_hours))) {
                        continue;
                    }

                    $sms->reservationReminder($reservation);

                    try {
                        app(WhatsAppNotifier::class)->reservationReminder($reservation);
                    } catch (Throwable $exception) {
                        report($exception);
                    }

                    $reservation->forceFill(['reminder_sent_at' => $now])->save();

                    $sent++;
                }
            });

        $this->info("Sent {$sent} reservation reminders.");

        return self::SUCCESS;
    }
}
