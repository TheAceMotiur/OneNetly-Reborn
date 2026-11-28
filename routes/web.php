<?php

use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\AnalyticsExportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocsController;
use App\Http\Controllers\LeaderboardController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PricingController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('analytics', AnalyticsController::class)->name('analytics');
    Route::get('analytics/export', AnalyticsExportController::class)->name('analytics.export');
    Route::get('purchases', [PurchaseController::class, 'index'])->name('purchases.index');
});

Route::get('pricing', [PricingController::class, 'index'])->name('pricing.index');

Route::get('docs', DocsController::class)->name('docs');

Route::get('leaderboard', LeaderboardController::class)->name('leaderboard');

Route::get('sitemap.xml', [SitemapController::class, 'sitemap'])->name('sitemap');
Route::get('robots.txt', [SitemapController::class, 'robots'])->name('robots');

Route::middleware(['auth'])->group(function () {
    Route::post('purchase', [PurchaseController::class, 'store'])->name('purchase.store');
    Route::get('purchase/return/{purchase}', [PurchaseController::class, 'getReturn'])->name('purchase.return');
});

Route::get('purchase/cancel', [PurchaseController::class, 'getCancel'])->name('purchase.cancel');

Route::get('terms', [PageController::class, 'terms'])->name('pages.terms');
Route::get('privacy', [PageController::class, 'privacy'])->name('pages.privacy');

require __DIR__.'/admin.php';
require __DIR__.'/settings.php';
require __DIR__.'/whatsapp.php';
require __DIR__.'/facebook.php';
require __DIR__.'/email-marketing.php';
require __DIR__.'/instagram.php';
require __DIR__.'/twitter.php';
require __DIR__.'/resume.php';
require __DIR__.'/widget.php';
require __DIR__.'/short-links.php';
require __DIR__.'/bio.php';
