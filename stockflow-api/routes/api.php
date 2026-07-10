<?php

use Illuminate\Support\Facades\Route;

Route::get('/hello', function () {
    return response()->json([
        'success' => true,
        'message' => 'Hello dari Laravel API',
        'application' => 'StockFlow',
    ]);
});