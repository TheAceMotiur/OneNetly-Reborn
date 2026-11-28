<?php

use App\Http\Controllers\WhatsApp\AutomationController;
use App\Http\Controllers\WhatsApp\CampaignController;
use App\Http\Controllers\WhatsApp\ContactController;
use App\Http\Controllers\WhatsApp\DashboardController;
use App\Http\Controllers\WhatsApp\InboxController;
use App\Http\Controllers\WhatsApp\SettingsController;
use App\Http\Controllers\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('webhooks/whatsapp', [WhatsAppWebhookController::class, 'verify'])->name('webhooks.whatsapp.verify');
Route::post('webhooks/whatsapp', [WhatsAppWebhookController::class, 'handle'])->name('webhooks.whatsapp');

Route::middleware(['auth', 'verified', 'tool:whatsapp'])->prefix('whatsapp')->name('whatsapp.')->group(function (): void {
    Route::get('/', DashboardController::class)->name('index');

    Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::post('settings/test', [SettingsController::class, 'test'])->name('settings.test');

    Route::post('campaigns', [CampaignController::class, 'store'])->name('campaigns.store');
    Route::post('campaigns/{campaign}/send', [CampaignController::class, 'send'])->name('campaigns.send');
    Route::delete('campaigns/{campaign}', [CampaignController::class, 'destroy'])->name('campaigns.destroy');

    Route::put('automations', [AutomationController::class, 'update'])->name('automations.update');
    Route::post('automations/keywords', [AutomationController::class, 'storeKeyword'])->name('automations.keywords.store');
    Route::patch('automations/keywords/{automation}', [AutomationController::class, 'updateKeyword'])->name('automations.keywords.update');
    Route::delete('automations/keywords/{automation}', [AutomationController::class, 'destroyKeyword'])->name('automations.keywords.destroy');

    Route::post('contacts', [ContactController::class, 'store'])->name('contacts.store');
    Route::post('contacts/sync', [ContactController::class, 'sync'])->name('contacts.sync');
    Route::patch('contacts/{contact}', [ContactController::class, 'update'])->name('contacts.update');
    Route::delete('contacts/{contact}', [ContactController::class, 'destroy'])->name('contacts.destroy');

    Route::post('inbox', [InboxController::class, 'send'])->name('inbox.send');
});
