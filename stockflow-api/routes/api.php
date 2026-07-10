<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/hello', function () {
    return response()->json([
        'success' => true,
        'message' => 'Hello dari Laravel API',
        'application' => 'StockFlow',
    ]);
});

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
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
    'role:owner',
])->get('/owner/check', function (Request $request) {
    return response()->json([
        'message' => 'Anda memiliki akses sebagai Owner.',
        'user' => $request->user()->only([
            'id',
            'name',
            'email',
            'role',
        ]),
    ]);
});