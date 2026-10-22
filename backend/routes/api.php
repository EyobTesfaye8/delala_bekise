<?php

use App\Http\Controllers\Api\Admin\AdminApprovalController;
use App\Http\Controllers\Api\Admin\AdminEscrowController;
use App\Http\Controllers\Api\Admin\AdminPayoutController;
use App\Http\Controllers\Api\Admin\AdminUserController;
use App\Http\Controllers\Api\Admin\KycController as AdminKycController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\EscrowController;
use App\Http\Controllers\Api\JobController;
use App\Http\Controllers\Api\KycController;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\PayoutController;
use App\Http\Controllers\Api\PayoutDestinationController;
use App\Http\Controllers\Api\ProposalController;
use App\Http\Controllers\Api\RatingController;
use App\Http\Middleware\EnsureKycApproved;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Auth Routes
    Route::prefix('auth')->group(function () {
        // Registration & Login
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);

        // Password Reset
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.reset');

        // NFR-SEC-1: SMS rate limiting (60s cooldown, 3/hr per phone, 10/hr per IP)
        Route::post('/send-otp', [AuthController::class, 'sendOtp'])->middleware('throttle:send_otp');
        Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);

        // Email Verification Magic Link
        Route::get('/verify-email/{id}/{hash}', [AuthController::class, 'verifyEmail'])->name('verification.verify');

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/migrate', [AuthController::class, 'migrateGuest']);
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/me', function (Request $request) {
                return $request->user();
            });
        });
    });

    // Public feeds & Profiles (SRS FR-2.2, FR-6.2)
    Route::get('/jobs', [JobController::class, 'index']);
    Route::get('/jobs/{job}', [JobController::class, 'show']);
    Route::get('/delalas/{delala}/profile', [RatingController::class, 'getDelalaProfile']);

    // Authenticated Routes (Without requiring Approved KYC)
    Route::middleware(['auth:sanctum'])->group(function () {
        // Media Pre-Signed Pipeline (SRS §9.1)
        Route::post('/media/presign', [MediaController::class, 'presign']);

        // KYC Management (Delala can view status & submit documents)
        Route::get('/kyc', [KycController::class, 'show']);
        Route::post('/kyc', [KycController::class, 'submit']);

        // Payout Destinations CRUD (FR-2.4)
        Route::get('/payout-destinations', [PayoutDestinationController::class, 'index']);
        Route::post('/payout-destinations', [PayoutDestinationController::class, 'store']);
        Route::post('/payout-destinations/{payoutDestination}/set-default', [PayoutDestinationController::class, 'setDefault']);
        Route::delete('/payout-destinations/{payoutDestination}', [PayoutDestinationController::class, 'destroy']);

        // Delala Payout Requests & Balance (FR-2.5)
        Route::get('/payouts', [PayoutController::class, 'index']);
        Route::post('/payouts/request', [PayoutController::class, 'requestPayout']);

        // Pre-handover cancellation request (Buyer)
        Route::post('/escrow/{escrow}/cancel-request', [EscrowController::class, 'cancelRequest']);

        // Jobs Creation (Buyers)
        Route::post('/jobs', [JobController::class, 'store']);

        // Double-Blind Mutual Rating Submission (FR-6.1)
        Route::post('/jobs/{job}/ratings', [RatingController::class, 'store']);

        // FCM Device Push Token Registration
        Route::post('/users/fcm-token', [RatingController::class, 'updateFcmToken']);
    });

    // Authenticated Routes Requiring Approved KYC for Delalas
    Route::middleware(['auth:sanctum', EnsureKycApproved::class])->group(function () {
        // Proposals
        Route::post('/jobs/{job}/proposals', [ProposalController::class, 'store']);
        Route::patch('/proposals/{proposal}/items/{item}/commission', [ProposalController::class, 'updateCommission']);
        Route::post('/proposals/{proposal}/withdraw', [ProposalController::class, 'withdraw']);
        Route::post('/proposals/{proposal}/reject', [ProposalController::class, 'reject']);
        Route::post('/proposals/{proposal}/items/{item}/accept', [ProposalController::class, 'acceptItem']);

        // Escrow Actions
        Route::post('/escrow/{escrow}/handover', [EscrowController::class, 'declareHandover']);
        Route::post('/escrow/{escrow}/release', [EscrowController::class, 'releaseFunds']);
        Route::post('/escrow/{escrow}/dispute', [EscrowController::class, 'dispute']);
        Route::post('/escrow/{escrow}/cancel-approve', [EscrowController::class, 'cancelApprove']);
        Route::post('/escrow/{escrow}/cancel-reject', [EscrowController::class, 'cancelReject']);
        Route::post('/escrow/{escrow}/dispute-evidence', [EscrowController::class, 'uploadDisputeEvidence']);

        // Chat
        Route::get('/proposals/{proposal}/chat', [ChatController::class, 'getOrCreate']);
        Route::post('/chats/{chat}/messages', [ChatController::class, 'sendMessage']);
    });

    // Admin Routes
    Route::prefix('admin')->middleware(['auth:sanctum'])->group(function () {
        // KYC Queue Management (AR-1)
        Route::get('/kyc', [AdminKycController::class, 'index']);
        Route::post('/kyc/{kyc}/approve', [AdminKycController::class, 'approve']);
        Route::post('/kyc/{kyc}/reject', [AdminKycController::class, 'reject']);

        // Escrow Force Actions with Maker-Checker (AR-2)
        Route::post('/escrow/{escrow}/force-release', [AdminEscrowController::class, 'forceRelease']);
        Route::post('/escrow/{escrow}/force-refund', [AdminEscrowController::class, 'forceRefund']);

        // Maker-Checker Approvals Queue (SRS §3)
        Route::get('/approvals', [AdminApprovalController::class, 'index']);
        Route::post('/approvals/{approval}/approve', [AdminApprovalController::class, 'approve']);
        Route::post('/approvals/{approval}/reject', [AdminApprovalController::class, 'reject']);

        // Batch Payout Processing (AR-3)
        Route::get('/payouts', [AdminPayoutController::class, 'index']);
        Route::get('/payouts/export-csv', [AdminPayoutController::class, 'exportBatchCsv']);
        Route::post('/payouts/{payout}/settle', [AdminPayoutController::class, 'settle']);

        // User Suspension Kill-Switch (AR-4)
        Route::post('/users/{user}/suspend', [AdminUserController::class, 'suspend']);
        Route::post('/users/{user}/reactivate', [AdminUserController::class, 'reactivate']);
    });

    // Webhooks
    Route::post('/escrow/chapa-callback', [EscrowController::class, 'chapaCallback']);
});
