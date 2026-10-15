<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\OtpController;
use App\Http\Middleware\AttachRequestId;
use App\Http\Middleware\ThrottleOtp;
use App\Models\User;
use App\Notifications\EmailVerificationNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// ─────────────────────────────────────────────────────────────────────────────
// Global API Middleware: attach X-Request-ID to all API routes
// ─────────────────────────────────────────────────────────────────────────────
Route::middleware([AttachRequestId::class])->group(function () {

    // ─────────────────────────────────────────────────────────────────────
    // API v1
    // ─────────────────────────────────────────────────────────────────────
    Route::prefix('v1')->name('api.v1.')->group(function () {

        // ─── Auth Module ─────────────────────────────────────────────────
        Route::prefix('auth')->name('auth.')->group(function () {

            // OTP Flow (Guest / passwordless) — SRS FR-1.1, FR-1.2
            Route::post('/send-otp', [OtpController::class, 'send'])
                ->middleware([ThrottleOtp::class, 'throttle:10,1'])
                ->name('otp.send');

            Route::post('/verify-otp', [OtpController::class, 'verify'])
                ->middleware('throttle:10,1')
                ->name('otp.verify');

            // Full Registration — SRS FR-1.3
            Route::post('/register', [AuthController::class, 'register'])
                ->middleware('throttle:10,1')
                ->name('register');

            // Login (email/phone + password) — SRS FR-1.3, NFR-SEC-2
            Route::post('/login', [AuthController::class, 'login'])
                ->middleware('throttle:10,1')
                ->name('login');

            // Email verification callback — SRS FR-1.3
            Route::get('/email/verify/{id}/{hash}', function (Request $request, int $id, string $hash) {
                $user = User::findOrFail($id);

                if (! hash_equals($hash, sha1($user->email))) {
                    return response()->json(['error' => 'INVALID_VERIFICATION_LINK'], 400);
                }

                if ($user->email_verified_at) {
                    return response()->json(['message' => 'Email already verified.']);
                }

                $user->update(['email_verified_at' => now()]);

                return response()->json(['message' => 'Email verified successfully.']);
            })->middleware('signed')->name('email.verify');

            // Resend email verification
            Route::post('/email/resend', function (Request $request) {
                $request->validate(['email' => 'required|email']);
                $user = User::where('email', $request->email)->firstOrFail();

                if ($user->email_verified_at) {
                    return response()->json(['message' => 'Email already verified.']);
                }

                $user->notify(new EmailVerificationNotification);

                return response()->json(['message' => 'Verification email resent.']);
            })->middleware('throttle:3,1')->name('email.resend');

            // ─── Authenticated Auth Routes ─────────────────────────────
            Route::middleware('auth:sanctum')->group(function () {
                // Current user
                Route::get('/me', [AuthController::class, 'me'])->name('me');

                // Logout
                Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

                // JIT Guest → Buyer migration — SRS FR-1.4, FR-1.5
                Route::post('/migrate', [AuthController::class, 'migrateGuest'])->name('migrate');
            });
        });
    });
});
