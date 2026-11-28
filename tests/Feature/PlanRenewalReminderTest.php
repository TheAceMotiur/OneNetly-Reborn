<?php

use App\Enums\PurchaseStatus;
use App\Mail\PlanExpiringSoon;
use App\Models\Purchase;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Mail;

test('the command reminds customers whose plan ends soon', function () {
    Mail::fake();

    $purchase = Purchase::factory()->create([
        'status' => PurchaseStatus::Completed,
        'expires_at' => now()->addDays(2),
        'reminder_sent_at' => null,
    ]);

    $this->artisan('purchases:send-renewal-reminders')->assertSuccessful();

    Mail::assertSent(
        PlanExpiringSoon::class,
        fn (PlanExpiringSoon $mail): bool => $mail->hasTo($purchase->user->email),
    );

    expect($purchase->refresh()->reminder_sent_at)->not->toBeNull();
});

test('lifetime purchases and purchases ending later are skipped', function () {
    Mail::fake();

    Purchase::factory()->create([
        'status' => PurchaseStatus::Completed,
        'expires_at' => null,
        'reminder_sent_at' => null,
    ]);

    Purchase::factory()->create([
        'status' => PurchaseStatus::Completed,
        'expires_at' => now()->addDays(20),
        'reminder_sent_at' => null,
    ]);

    $this->artisan('purchases:send-renewal-reminders')->assertSuccessful();

    Mail::assertNothingSent();
});

test('a purchase that was already reminded is skipped', function () {
    Mail::fake();

    Purchase::factory()->create([
        'status' => PurchaseStatus::Completed,
        'expires_at' => now()->addDays(2),
        'reminder_sent_at' => now()->subDay(),
    ]);

    $this->artisan('purchases:send-renewal-reminders')->assertSuccessful();

    Mail::assertNothingSent();
});

test('the reminder days are configurable from the admin settings', function () {
    Mail::fake();

    app(SettingsService::class)->set('plan_reminder_days', 10);

    $purchase = Purchase::factory()->create([
        'status' => PurchaseStatus::Completed,
        'expires_at' => now()->addDays(8),
        'reminder_sent_at' => null,
    ]);

    $this->artisan('purchases:send-renewal-reminders')->assertSuccessful();

    Mail::assertSent(PlanExpiringSoon::class);
    expect($purchase->refresh()->reminder_sent_at)->not->toBeNull();
});
