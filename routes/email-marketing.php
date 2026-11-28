<?php

use App\Http\Controllers\EmailMarketing\AutomationController;
use App\Http\Controllers\EmailMarketing\CampaignController;
use App\Http\Controllers\EmailMarketing\DashboardController;
use App\Http\Controllers\EmailMarketing\SettingsController;
use App\Http\Controllers\EmailMarketing\SubscriberController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'tool:email'])->prefix('email-marketing')->name('email-marketing.')->group(function (): void {
    Route::get('/', DashboardController::class)->name('index');

    Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');

    Route::post('campaigns', [CampaignController::class, 'store'])->name('campaigns.store');
    Route::post('campaigns/{campaign}/send', [CampaignController::class, 'send'])->name('campaigns.send');
    Route::delete('campaigns/{campaign}', [CampaignController::class, 'destroy'])->name('campaigns.destroy');

    Route::put('automations', [AutomationController::class, 'update'])->name('automations.update');

    Route::post('subscribers', [SubscriberController::class, 'store'])->name('subscribers.store');
    Route::post('subscribers/sync', [SubscriberController::class, 'sync'])->name('subscribers.sync');
    Route::get('subscribers/export', [SubscriberController::class, 'export'])->name('subscribers.export');
    Route::delete('subscribers/{subscriber}', [SubscriberController::class, 'destroy'])->name('subscribers.destroy');
});
