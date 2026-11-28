<?php

use App\Http\Controllers\WidgetController;
use Illuminate\Support\Facades\Route;

Route::get('widgets/embed/{key}.js', [WidgetController::class, 'embed'])
    ->where('key', '[A-Za-z0-9]+')
    ->name('widgets.embed');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('widgets', [WidgetController::class, 'index'])->name('widgets.index');
    Route::post('widgets', [WidgetController::class, 'store'])->name('widgets.store');
    Route::post('widgets/preview', [WidgetController::class, 'preview'])->name('widgets.preview');

    Route::get('widgets/{widget}', [WidgetController::class, 'edit'])
        ->whereNumber('widget')
        ->name('widgets.edit');
    Route::patch('widgets/{widget}', [WidgetController::class, 'update'])
        ->whereNumber('widget')
        ->name('widgets.update');
    Route::delete('widgets/{widget}', [WidgetController::class, 'destroy'])
        ->whereNumber('widget')
        ->name('widgets.destroy');
});
