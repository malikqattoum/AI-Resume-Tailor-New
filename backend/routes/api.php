<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ResumeController;
use App\Http\Controllers\TailorController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\WebhookController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Health check (public)
Route::get('/health', function () {
    return response()->json([
        'success' => true,
        'message' => 'AI Resume Tailor API is running',
        'timestamp' => now()->toIso8601String(),
    ]);
});

// Authentication routes (public)
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1'); // 5 attempts per minute
});

// Protected routes (require authentication)
Route::middleware('auth:sanctum')->group(function () {
    // Auth management
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/user', [AuthController::class, 'user']);

    // Resume endpoints with rate limiting
    Route::post('/resume/upload', [ResumeController::class, 'upload'])
        ->middleware('throttle:20,1'); // 20 uploads per minute
    Route::get('/resume/{id}', [ResumeController::class, 'show']);

    // Tailor endpoints with rate limiting (expensive operations)
    Route::post('/tailor', [TailorController::class, 'tailor'])
        ->middleware('throttle:5,1'); // 5 tailoring requests per minute ( OpenRouter API costs money)

    Route::get('/tailored/{id}', [TailorController::class, 'show']);

    // Download endpoint with rate limiting
    Route::get('/download/{opaque_id}', [TailorController::class, 'download'])
        ->middleware('throttle:30,1'); // 30 downloads per minute
});

// Subscription routes (protected)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/subscription', [SubscriptionController::class, 'show']);
    Route::post('/subscription/create-portal-session', [SubscriptionController::class, 'createPortalSession']);
    Route::post('/subscription/upgrade', [SubscriptionController::class, 'upgrade']);
});

// Stripe webhook (no auth - uses Stripe signature verification)
Route::post('/webhooks/stripe', [WebhookController::class, 'handle']);
