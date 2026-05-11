<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\OrderController;

/*
|--------------------------------------------------------------------------
| USER ROUTES
|--------------------------------------------------------------------------
*/

Route::middleware('verify.token')->group(function () {

    Route::post(
        '/orders',
        [OrderController::class, 'store']
    );

    Route::get(
        '/orders',
        [OrderController::class, 'index']
    );
});

/*
|--------------------------------------------------------------------------
| INTERNAL ROUTES
|--------------------------------------------------------------------------
*/

Route::middleware('verify.service.token')->group(function () {

    Route::get(
        '/internal/orders/{id}/total',
        [OrderController::class, 'getOrderTotal']
    );

    Route::post(
        '/internal/orders/{order}/paid',
        [OrderController::class, 'markAsPaid']
    );
});