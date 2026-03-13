<?php

use App\Http\Controllers\ProductController;
use App\Http\Controllers\OrderController;
use Illuminate\Support\Facades\Route;


Route::middleware('throttle:60,1')->prefix('api')->group(function () {
    Route::prefix('products')->group(function () {
        Route::get('/', [ProductController::class, 'index']);
        Route::post('/', [ProductController::class, 'store']);
        Route::get('/search', [ProductController::class, 'search']);
        Route::get('/dashboard', [ProductController::class, 'dashboard']);
        Route::get('/sales-report', [ProductController::class, 'salesReport']);
    });
    Route::prefix('orders')->group(function () {
        Route::get('/', [OrderController::class, 'index']);
        Route::post('/', [OrderController::class, 'store']);
        Route::get('/filter', [OrderController::class, 'filterByStatus']);
    });
});
