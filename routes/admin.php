<?php

use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\PurchaseController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');

        Route::get('analytics', AnalyticsController::class)->name('analytics');

        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::patch('users/{user}/admin', [UserController::class, 'update'])->name('users.update');
        Route::post('users/{user}/plan', [UserController::class, 'assignPlan'])->name('users.plan');

        Route::get('profiles', [ProfileController::class, 'index'])->name('profiles.index');
        Route::patch('profiles/{profile}', [ProfileController::class, 'update'])->name('profiles.update');
        Route::delete('profiles/{profile}', [ProfileController::class, 'destroy'])->name('profiles.destroy');

        Route::get('plans', [PlanController::class, 'index'])->name('plans.index');
        Route::post('plans', [PlanController::class, 'store'])->name('plans.store');
        Route::patch('plans/{plan}', [PlanController::class, 'update'])->name('plans.update');
        Route::delete('plans/{plan}', [PlanController::class, 'destroy'])->name('plans.destroy');

        Route::get('purchases', [PurchaseController::class, 'index'])->name('purchases.index');
        Route::patch('purchases/{purchase}', [PurchaseController::class, 'update'])->name('purchases.update');

        Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
        Route::patch('settings', [SettingsController::class, 'update'])->name('settings.update');
    });
