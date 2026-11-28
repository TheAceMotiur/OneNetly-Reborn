<?php

use App\Http\Controllers\Instagram\AutomationController;
use App\Http\Controllers\Instagram\DashboardController;
use App\Http\Controllers\Instagram\InboxController;
use App\Http\Controllers\Instagram\SettingsController;
use App\Http\Controllers\InstagramWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('webhooks/instagram', [InstagramWebhookController::class, 'verify'])->name('webhooks.instagram.verify');
Route::post('webhooks/instagram', [InstagramWebhookController::class, 'handle'])->name('webhooks.instagram');

Route::middleware(['auth', 'verified', 'tool:instagram'])->prefix('instagram')->name('instagram.')->group(function (): void {
    Route::get('/', DashboardController::class)->name('index');

    Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::post('settings/test', [SettingsController::class, 'test'])->name('settings.test');

    Route::put('automations', [AutomationController::class, 'update'])->name('automations.update');
    Route::post('automations/keywords', [AutomationController::class, 'storeKeyword'])->name('automations.keywords.store');
    Route::patch('automations/keywords/{automation}', [AutomationController::class, 'updateKeyword'])->name('automations.keywords.update');
    Route::delete('automations/keywords/{automation}', [AutomationController::class, 'destroyKeyword'])->name('automations.keywords.destroy');

    Route::post('inbox', [InboxController::class, 'send'])->name('inbox.send');
});
