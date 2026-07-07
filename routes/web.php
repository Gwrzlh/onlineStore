<?php

use App\Http\Controllers\orderController;

use Illuminate\Support\Facades\Route;

Route::post('/orders', [orderController::class, 'store']);