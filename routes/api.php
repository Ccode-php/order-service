<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\OrderController;

Route::middleware('verify.token')->group(function () {
    
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders/{order}/paid', [OrderController::class, 'markAsPaid']);
    Route::get(
        '/internal/orders/{id}/total',
        [OrderController::class, 'getOrderTotal']
    );
    

});

