<?php

namespace App\Jobs;

use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GarbageCollectInactiveGuestsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $inactiveDays = 5) {}

    /**
     * Execute the job.
     */
    public function handle(): int
    {
        $cutoff = now()->subDays($this->inactiveDays);

        // Fetch candidates: guest users inactive for 5 days without ongoing deals
        $candidateGuests = User::query()
            ->where('type', 'guest')
            ->where('created_at', '<=', $cutoff)
            ->where('updated_at', '<=', $cutoff)
            // No recent OTP codes requested within cutoff
            ->whereNotIn('phone', OtpCode::select('phone')->where('created_at', '>', $cutoff))
            // No personal access tokens created or used within cutoff
            ->whereDoesntHave('tokens', function ($q) use ($cutoff) {
                $q->where('created_at', '>', $cutoff)
                    ->orWhere('last_used_at', '>', $cutoff);
            })
            // No chat messages sent within cutoff
            ->whereDoesntHave('chatMessages', function ($q) use ($cutoff) {
                $q->where('created_at', '>', $cutoff);
            })
            // No jobs created or updated within cutoff
            ->whereDoesntHave('jobs', function ($q) use ($cutoff) {
                $q->where('created_at', '>', $cutoff)
                    ->orWhere('updated_at', '>', $cutoff);
            })
            // No ongoing deals: no active jobs
            ->whereDoesntHave('jobs', function ($q) {
                $q->whereNotIn('status', ['completed', 'canceled']);
            })
            // No ongoing deals: no active/in-progress escrow transactions
            ->whereDoesntHave('buyerEscrows', function ($q) {
                $q->whereNotIn('status', ['released', 'force_released', 'force_refunded']);
            })
            ->get();

        $purgedCount = 0;

        foreach ($candidateGuests as $guest) {
            DB::transaction(function () use ($guest) {
                // Revoke all Sanctum API tokens
                $guest->tokens()->delete();

                // If user has historical escrow records (foreign key restrict on escrow_transactions.buyer_id),
                // anonymize phone and soft delete to preserve ledger auditability without violating FK constraints.
                if ($guest->buyerEscrows()->exists()) {
                    $guest->update([
                        'phone' => $guest->phone.'_purged_'.$guest->id,
                        'status' => 'purged',
                    ]);
                    $guest->delete();
                } else {
                    // Safe to permanently purge: cascades jobs, chats, messages
                    $guest->forceDelete();
                }
            });

            $purgedCount++;
        }

        Log::info("Garbage collector purged {$purgedCount} inactive guest account(s) older than {$this->inactiveDays} days.");

        return $purgedCount;
    }
}
