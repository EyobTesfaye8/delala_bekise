<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\OtpController;
use App\Http\Controllers\Api\V1\KycController;
use App\Http\Controllers\Api\V1\MediaController;
use App\Http\Controllers\Api\V1\PayoutDestinationController;
use App\Http\Controllers\Api\V1\JobController;
use App\Http\Controllers\Api\V1\ProposalController;
use App\Http\Controllers\Api\V1\ChatController;
use App\Http\Controllers\Api\V1\EscrowController;
use App\Http\Controllers\Api\V1\PayoutController;
use App\Http\Controllers\Api\V1\RatingController;
use App\Http\Middleware\AttachRequestId;
use App\Http\Middleware\EnsureDelalaIsApproved;
use App\Http\Middleware\ThrottleOtp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware([AttachRequestId::class])->group(function () {
    Route::prefix('v1')->name('api.v1.')->group(function () {

        // ── MODULE 1: Auth ────────────────────────────────────────────────
        Route::prefix('auth')->name('auth.')->group(function () {
            Route::post('/send-otp', [OtpController::class, 'send'])
                ->middleware([ThrottleOtp::class, 'throttle:10,1'])
                ->name('otp.send');

            Route::post('/verify-otp', [OtpController::class, 'verify'])
                ->middleware('throttle:10,1')
                ->name('otp.verify');

            Route::post('/register', [AuthController::class, 'register'])
                ->middleware('throttle:10,1')
                ->name('register');

            Route::post('/login', [AuthController::class, 'login'])
                ->middleware('throttle:10,1')
                ->name('login');

            Route::get('/email/verify/{id}/{hash}', function (Request $request, int $id, string $hash) {
                $user = \App\Models\User::findOrFail($id);
                if (! hash_equals($hash, sha1($user->email))) {
                    return response()->json(['error' => 'INVALID_VERIFICATION_LINK'], 400);
                }
                if ($user->email_verified_at) {
                    return response()->json(['message' => 'Email already verified.']);
                }
                $user->update(['email_verified_at' => now()]);

                return response()->json(['message' => 'Email verified successfully.']);
            })->middleware('signed')->name('email.verify');

            Route::post('/email/resend', function (Request $request) {
                $request->validate(['email' => 'required|email']);
                $user = \App\Models\User::where('email', $request->email)->firstOrFail();
                if ($user->email_verified_at) {
                    return response()->json(['message' => 'Email already verified.']);
                }
                $user->notify(new \App\Notifications\EmailVerificationNotification());

                return response()->json(['message' => 'Verification email resent.']);
            })->middleware('throttle:3,1')->name('email.resend');

            Route::middleware('auth:sanctum')->group(function () {
                Route::get('/me', [AuthController::class, 'me'])->name('me');
                Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
                Route::post('/migrate', [AuthController::class, 'migrateGuest'])->name('migrate');
            });
        });

        // ── All routes below require auth ─────────────────────────────────
        Route::middleware('auth:sanctum')->group(function () {

            // ── MODULE 3: Media (S3 Pre-Signed Upload) ────────────────────
            Route::prefix('media')->name('media.')->group(function () {
                Route::post('/presign', [MediaController::class, 'presign'])->name('presign');
            });

            // ── MODULE 2: KYC & Payout Destinations ──────────────────────
            Route::prefix('kyc')->name('kyc.')->group(function () {
                Route::get('/status', [KycController::class, 'status'])->name('status');
                Route::post('/submit', [KycController::class, 'submit'])->name('submit');
            });

            Route::prefix('payout-destinations')->name('payout-destinations.')->group(function () {
                Route::get('/', [PayoutDestinationController::class, 'index'])->name('index');
                Route::post('/', [PayoutDestinationController::class, 'store'])->name('store');
                Route::put('/{destination}', [PayoutDestinationController::class, 'update'])->name('update');
                Route::delete('/{destination}', [PayoutDestinationController::class, 'destroy'])->name('destroy');
                Route::patch('/{destination}/set-default', [PayoutDestinationController::class, 'setDefault'])->name('set-default');
            });

            // ── MODULE 4: Jobs ────────────────────────────────────────────
            Route::prefix('jobs')->name('jobs.')->group(function () {
                // Delala feed (approved Delalas only)
                Route::get('/feed', [JobController::class, 'feed'])
                    ->middleware('delala.approved')
                    ->name('feed');

                // Buyer's own jobs
                Route::get('/my', [JobController::class, 'myJobs'])->name('my');

                Route::get('/{job}', [JobController::class, 'show'])->name('show');
                Route::post('/', [JobController::class, 'store'])->name('store');
                Route::put('/{job}', [JobController::class, 'update'])->name('update');
                Route::post('/{job}/publish', [JobController::class, 'publish'])->name('publish');
                Route::post('/{job}/cancel', [JobController::class, 'cancel'])->name('cancel');
                Route::post('/{job}/clone', [JobController::class, 'clone'])->name('clone');
            });

            // ── MODULE 5: Proposals & Negotiation Chat ────────────────────
            Route::prefix('proposals')->name('proposals.')->group(function () {
                Route::post('/', [ProposalController::class, 'store'])
                    ->middleware('delala.approved')
                    ->name('store');
                Route::get('/{proposal}', [ProposalController::class, 'show'])->name('show');
                Route::post('/{proposal}/withdraw', [ProposalController::class, 'withdraw'])->name('withdraw');
                Route::patch('/{proposal}/items/{item}/commission', [ProposalController::class, 'updateCommission'])
                    ->middleware('delala.approved')
                    ->name('items.commission');
                Route::get('/my/submitted', [ProposalController::class, 'myProposals'])
                    ->middleware('delala.approved')
                    ->name('my');
            });

            Route::prefix('chats')->name('chats.')->group(function () {
                Route::get('/', [ChatController::class, 'index'])->name('index');
                Route::get('/{chat}', [ChatController::class, 'show'])->name('show');
                Route::post('/{chat}/messages', [ChatController::class, 'sendMessage'])->name('messages.send');
                Route::get('/{chat}/messages', [ChatController::class, 'messages'])->name('messages.index');
                Route::post('/initiate/{proposal}', [ChatController::class, 'initiate'])->name('initiate');
            });

            // ── MODULE 6: Escrow & Payments ───────────────────────────────
            Route::prefix('escrow')->name('escrow.')->group(function () {
                Route::post('/initiate', [EscrowController::class, 'initiate'])->name('initiate');
                Route::post('/webhook', [EscrowController::class, 'webhook'])
                    ->withoutMiddleware('auth:sanctum')
                    ->name('webhook'); // Public — verified by HMAC signature

                Route::get('/{escrow}', [EscrowController::class, 'show'])->name('show');
                Route::post('/{escrow}/request-cancellation', [EscrowController::class, 'requestCancellation'])->name('cancel.request');
                Route::post('/{escrow}/respond-cancellation', [EscrowController::class, 'respondCancellation'])->name('cancel.respond');
                Route::post('/{escrow}/declare-handover', [EscrowController::class, 'declareHandover'])->name('handover.declare');
                Route::post('/{escrow}/accept-handover', [EscrowController::class, 'acceptHandover'])->name('handover.accept');
                Route::post('/{escrow}/dispute', [EscrowController::class, 'dispute'])->name('dispute');
                Route::post('/{escrow}/upload-evidence', [EscrowController::class, 'uploadEvidence'])->name('evidence');
            });

            // ── MODULE 7: Payouts ─────────────────────────────────────────
            Route::prefix('payouts')->name('payouts.')->group(function () {
                Route::post('/withdraw', [PayoutController::class, 'withdraw'])
                    ->middleware('delala.approved')
                    ->name('withdraw');
                Route::get('/history', [PayoutController::class, 'history'])
                    ->middleware('delala.approved')
                    ->name('history');
            });

            // ── MODULE 8: Ratings & Trust ─────────────────────────────────
            Route::prefix('ratings')->name('ratings.')->group(function () {
                Route::post('/jobs/{job}', [RatingController::class, 'rate'])->name('rate');
                Route::get('/delala/{user}', [RatingController::class, 'delalaTrustProfile'])->name('delala.profile');
            });
        });
    });
});
