<?php

use App\Http\Controllers\PosController;
use Illuminate\Support\Facades\Route;

Route::middleware('pos.token')->group(function () {
    Route::get('menu', [PosController::class, 'menu'])->name('pos.menu');
    Route::get('orders', [PosController::class, 'orders'])->name('pos.orders');
    Route::post('orders', [PosController::class, 'storeOrder'])->name('pos.orders.store');
    Route::patch('orders/{order:reference}', [PosController::class, 'updateOrder'])->name('pos.orders.update');
    Route::patch('items/{item}', [PosController::class, 'updateItem'])->name('pos.items.update');
});
