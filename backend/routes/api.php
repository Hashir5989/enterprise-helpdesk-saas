<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\TicketCommentController;
use App\Http\Controllers\Api\V1\TicketController;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Health check endpoint
    Route::get('/health', function () {
        return ApiResponse::success([
            'status' => 'healthy',
            'framework' => 'Laravel 11',
            'timestamp' => now()->toIso8601String(),
        ], 'API v1 is operational');
    });

    // Auth endpoints
    Route::prefix('auth')->group(function () {
        Route::post('/register-tenant', [AuthController::class, 'registerTenant']);
        Route::post('/login', [AuthController::class, 'login']);

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/me', [AuthController::class, 'me']);
        });
    });

    // Ticket & Comment Endpoints
    Route::middleware('auth:sanctum')->group(function () {
        Route::apiResource('tickets', TicketController::class);
        Route::get('tickets/{ticket}/comments', [TicketCommentController::class, 'index']);
        Route::post('tickets/{ticket}/comments', [TicketCommentController::class, 'store']);
    });
});
