<?php

use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', function () {
        return ApiResponse::success([
            'status' => 'healthy',
            'framework' => 'Laravel 11',
            'timestamp' => now()->toIso8601String(),
        ], 'API v1 is operational');
    });

    Route::middleware('auth:sanctum')->get('/me', function (Request $request) {
        return ApiResponse::success($request->user());
    });
});
