<?php

use App\Http\Controllers\Twitter\AutomationController;
use App\Http\Controllers\Twitter\DashboardController;
use App\Http\Controllers\Twitter\PostController;
use App\Http\Controllers\Twitter\SettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'tool:twitter'])->prefix('twitter')->name('twitter.')->group(function (): void {
    Route::get('/', DashboardController::class)->name('index');

    Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::post('settings/test', [SettingsController::class, 'test'])->name('settings.test');

    Route::post('posts', [PostController::class, 'store'])->name('posts.store');
    Route::post('posts/{post}/publish', [PostController::class, 'publish'])->name('posts.publish');
    Route::post('posts/{post}/schedule', [PostController::class, 'schedule'])->name('posts.schedule');
    Route::delete('posts/{post}', [PostController::class, 'destroy'])->name('posts.destroy');

    Route::put('automations', [AutomationController::class, 'update'])->name('automations.update');
    Route::post('automations/keywords', [AutomationController::class, 'storeKeyword'])->name('automations.keywords.store');
    Route::patch('automations/keywords/{automation}', [AutomationController::class, 'updateKeyword'])->name('automations.keywords.update');
    Route::delete('automations/keywords/{automation}', [AutomationController::class, 'destroyKeyword'])->name('automations.keywords.destroy');
    Route::post('sync', [AutomationController::class, 'sync'])->name('sync');
});
