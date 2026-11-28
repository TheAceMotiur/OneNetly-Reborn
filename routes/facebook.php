<?php

use App\Http\Controllers\Facebook\AutomationController;
use App\Http\Controllers\Facebook\DashboardController;
use App\Http\Controllers\Facebook\InboxController;
use App\Http\Controllers\Facebook\SettingsController;
use App\Http\Controllers\FacebookWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('webhooks/facebook', [FacebookWebhookController::class, 'verify'])->name('webhooks.facebook.verify');
Route::post('webhooks/facebook', [FacebookWebhookController::class, 'handle'])->name('webhooks.facebook');

Route::middleware(['auth', 'verified', 'tool:facebook'])->prefix('facebook')->name('facebook.')->group(function (): void {
    Route::get('/', DashboardController::class)->name('index');

    Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::post('settings/test', [SettingsController::class, 'test'])->name('settings.test');

    Route::put('automations', [AutomationController::class, 'update'])->name('automations.update');
    Route::post('automations/keywords', [AutomationController::class, 'storeKeyword'])->name('automations.keywords.store');
    Route::patch('automations/keywords/{automation}', [AutomationController::class, 'updateKeyword'])->name('automations.keywords.update');
    Route::delete('automations/keywords/{automation}', [AutomationController::class, 'destroyKeyword'])->name('automations.keywords.destroy');

    Route::post('inbox', [InboxController::class, 'send'])->name('inbox.send');
});
