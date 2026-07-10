<?php

use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\PurchaseController;
use App\Http\Controllers\Api\StockAdjustmentController;
use App\Http\Controllers\Api\StockMovementController;
use App\Http\Controllers\Api\SupplierController;
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

Route::middleware([
    'auth:sanctum',
    'active.user',
    'role:owner,admin',
])
    ->prefix('categories')
    ->group(function () {
        Route::get('/', [
            CategoryController::class,
            'index',
        ]);

        Route::post('/', [
            CategoryController::class,
            'store',
        ]);

        Route::put('/{category}', [
            CategoryController::class,
            'update',
        ]);

        Route::patch('/{category}/toggle-status', [
            CategoryController::class,
            'toggleStatus',
        ]);
    });

Route::middleware([
    'auth:sanctum',
    'active.user',
    'role:owner,admin',
])
    ->prefix('products')
    ->group(function () {
        Route::get('/', [
            ProductController::class,
            'index',
        ]);

        Route::post('/', [
            ProductController::class,
            'store',
        ]);

        Route::put('/{product}', [
            ProductController::class,
            'update',
        ]);

        Route::patch('/{product}/toggle-status', [
            ProductController::class,
            'toggleStatus',
        ]);
    });

Route::middleware([
    'auth:sanctum',
    'active.user',
    'role:owner,admin',
])->group(function () {
    Route::get(
        '/suppliers',
        [SupplierController::class, 'index']
    );

    Route::post(
        '/suppliers',
        [SupplierController::class, 'store']
    );

    Route::put(
        '/suppliers/{supplier}',
        [SupplierController::class, 'update']
    );

    Route::patch(
        '/suppliers/{supplier}/toggle-status',
        [SupplierController::class, 'toggleStatus']
    );

    Route::get(
        '/purchases',
        [PurchaseController::class, 'index']
    );

    Route::post(
        '/purchases',
        [PurchaseController::class, 'store']
    );

    Route::get(
        '/purchases/{purchase}',
        [PurchaseController::class, 'show']
    );

    Route::get(
        '/stock-movements',
        [StockMovementController::class, 'index']
    );

    Route::get(
        '/stock-adjustments',
        [StockAdjustmentController::class, 'index']
    );

    Route::post(
        '/stock-adjustments',
        [StockAdjustmentController::class, 'store']
    );

    Route::get(
        '/stock-adjustments/{stockAdjustment}',
        [StockAdjustmentController::class, 'show']
    );
});
