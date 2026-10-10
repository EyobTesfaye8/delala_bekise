<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\EscrowController;
use App\Http\Controllers\Api\JobController;
use App\Http\Controllers\Api\ProposalController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Auth
    Route::prefix('auth')->group(function () {
        // Registration & Login
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);

        // Password Reset
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.reset');

        // NFR-1.1: SMS rate limiting (3/hr per phone, 10/hr per IP)
        Route::post('/send-otp', [AuthController::class, 'sendOtp'])->middleware('throttle:send_otp');
        Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);

        // Email Verification Magic Link
        Route::get('/verify-email', [AuthController::class, 'verifyEmail'])->name('verification.verify');

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/migrate', [AuthController::class, 'migrateGuest']);
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/me', function (Request $request) {
                return $request->user();
            });
        });
    });

    // Public feeds
    Route::get('/jobs', [JobController::class, 'index']);
    Route::get('/jobs/{job}', [JobController::class, 'show']);

    Route::middleware('auth:sanctum')->group(function () {
        // Jobs
        Route::post('/jobs', [JobController::class, 'store']);

        // Proposals
        Route::post('/jobs/{job}/proposals', [ProposalController::class, 'store']);
        Route::post('/proposals/{proposal}/withdraw', [ProposalController::class, 'withdraw']);
        Route::post('/proposals/{proposal}/reject', [ProposalController::class, 'reject']);
        Route::post('/proposals/{proposal}/items/{item}/accept', [ProposalController::class, 'acceptItem']);

        // Escrow
        Route::post('/escrow/{escrow}/handover', [EscrowController::class, 'declareHandover']);
        Route::post('/escrow/{escrow}/release', [EscrowController::class, 'releaseFunds']);
        Route::post('/escrow/{escrow}/dispute', [EscrowController::class, 'dispute']);

        // Chat
        Route::get('/proposals/{proposal}/chat', [ChatController::class, 'getOrCreate']);
        Route::post('/chats/{chat}/messages', [ChatController::class, 'sendMessage']);
    });

    // Webhooks
    Route::post('/escrow/chapa-callback', [EscrowController::class, 'chapaCallback']);
});
