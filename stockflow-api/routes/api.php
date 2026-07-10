<?php

use App\Http\Controllers\Api\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/hello', function () {
    return response()->json([
        'success' => true,
        'message' => 'Hello dari Laravel API',
        'application' => 'StockFlow',
    ]);
});

Route::middleware([
    'auth:sanctum',
    'active.user',
])->get('/user', function (Request $request) {
    return response()->json([
        'user' => $request->user()->only([
            'id',
            'name',
            'email',
            'role',
            'is_active',
        ]),
    ]);
});

Route::middleware([
    'auth:sanctum',
    'active.user',
    'role:owner',
])
    ->prefix('users')
    ->group(function () {
        Route::get('/', [
            UserController::class,
            'index',
        ]);

        Route::post('/', [
            UserController::class,
            'store',
        ]);

        Route::put('/{user}', [
            UserController::class,
            'update',
        ]);

        Route::patch('/{user}/toggle-status', [
            UserController::class,
            'toggleStatus',
        ]);
    });
