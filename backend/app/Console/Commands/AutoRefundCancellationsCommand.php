<?php

namespace App\Console\Commands;

use App\Models\EscrowTransaction;
use App\Services\ChapaService;
use App\Services\LedgerService;
use App\States\Escrow\CancellationRequested;
use App\States\Escrow\ForceRefunded;
use App\States\Job\Canceled as JobCanceled;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AutoRefundCancellationsCommand extends Command
{
    protected $signature = 'delala:auto-refund-cancellations';

    protected $description = 'Executes automatic refund for pre-handover cancellations ignored by broker after 48 hours';

    public function handle(ChapaService $chapa, LedgerService $ledger): int
    {
        $expiredCancellations = EscrowTransaction::where('status', CancellationRequested::$name)
            ->whereNotNull('cancellation_deadline_at')
            ->where('cancellation_deadline_at', '<=', now())
            ->get();

        $count = 0;

        foreach ($expiredCancellations as $escrow) {
            DB::transaction(function () use ($escrow, $chapa, $ledger) {
                $escrow->status->transitionTo(ForceRefunded::class);
                $escrow->update([
                    'resolved_at' => now(),
                    'resolved_by' => null,
                    'admin_resolution_note' => 'Auto-refunded by system scheduler after Delala failed to respond to cancellation within 48 hours.',
                ]);

                $chapa->refund($escrow);
                $ledger->recordRefund($escrow, '48h auto-refund on ignored cancellation');

                if ($escrow->job->status->canTransitionTo(JobCanceled::class)) {
                    $escrow->job->status->transitionTo(JobCanceled::class);
                }
            });

            Log::info("Escrow #{$escrow->id} auto-refunded to Buyer after 48h cancellation response expiration.");
            $count++;
        }

        $this->info("Successfully auto-refunded {$count} expired cancellations.");

        return self::SUCCESS;
    }
}
