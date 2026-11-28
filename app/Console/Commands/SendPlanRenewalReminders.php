<?php

namespace App\Console\Commands;

use App\Enums\PurchaseStatus;
use App\Mail\PlanExpiringSoon;
use App\Models\Purchase;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendPlanRenewalReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'purchases:send-renewal-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Email customers whose paid plan ends soon';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) setting('plan_reminder_days', 3);
        $days = $days > 0 ? min($days, 30) : 3;

        $purchases = Purchase::query()
            ->where('status', PurchaseStatus::Completed)
            ->whereNotNull('expires_at')
            ->whereNull('reminder_sent_at')
            ->whereBetween('expires_at', [now(), now()->addDays($days)])
            ->with(['user', 'plan'])
            ->get();

        $sent = 0;

        foreach ($purchases as $purchase) {
            try {
                Mail::to($purchase->user->email)->send(new PlanExpiringSoon($purchase));

                $purchase->forceFill(['reminder_sent_at' => now()])->save();

                $sent++;
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        $this->info("Sent {$sent} plan renewal reminder(s).");

        return self::SUCCESS;
    }
}
