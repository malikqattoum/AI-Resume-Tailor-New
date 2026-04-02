<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ResumeController;
use App\Http\Controllers\TailorController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Health check
Route::get('/health', function () {
    return response()->json([
        'success' => true,
        'message' => 'AI Resume Tailor API is running',
        'timestamp' => now()->toIso8601String(),
    ]);
});

// Resume endpoints
Route::post('/resume/upload', [ResumeController::class, 'upload']);
Route::get('/resume/{id}', [ResumeController::class, 'show']);

// Tailor endpoints
Route::post('/tailor', [TailorController::class, 'tailor']);
Route::get('/tailored/{id}', [TailorController::class, 'show']);

// Download endpoint
Route::get('/download/{path}', [TailorController::class, 'download'])
    ->where('path', '.*');
