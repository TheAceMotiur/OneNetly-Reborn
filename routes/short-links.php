<?php

use App\Http\Controllers\ShortLinkController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('short-links', [ShortLinkController::class, 'index'])->name('short-links.index');
    Route::post('short-links', [ShortLinkController::class, 'store'])->name('short-links.store');
    Route::get('short-links/{shortLink}', [ShortLinkController::class, 'analytics'])->name('short-links.analytics');
    Route::patch('short-links/{shortLink}', [ShortLinkController::class, 'update'])->name('short-links.update');
    Route::delete('short-links/{shortLink}', [ShortLinkController::class, 'destroy'])->name('short-links.destroy');
    Route::get('short-links/{shortLink}/qr', [ShortLinkController::class, 'qr'])->name('short-links.qr');
});

Route::get('s/{slug}', [ShortLinkController::class, 'redirect'])
    ->where('slug', '[A-Za-z0-9_-]+')
    ->name('short-links.redirect');

Route::post('s/{slug}/unlock', [ShortLinkController::class, 'unlock'])
    ->where('slug', '[A-Za-z0-9_-]+')
    ->middleware('throttle:10,1')
    ->name('short-links.unlock');
