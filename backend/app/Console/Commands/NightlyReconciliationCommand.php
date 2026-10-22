<?php

namespace App\Console\Commands;

use App\Models\LedgerEntry;
use App\Services\FcmService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class NightlyReconciliationCommand extends Command
{
    protected $signature = 'delala:nightly-reconciliation';

    protected $description = 'Daily 02:00 reconciliation verifying double-entry ledger debit/credit balance across all accounts';

    public function handle(FcmService $fcm): int
    {
        $totalDebits = (int) LedgerEntry::where('direction', 'debit')->sum('amount_santim');
        $totalCredits = (int) LedgerEntry::where('direction', 'credit')->sum('amount_santim');

        $imbalance = abs($totalDebits - $totalCredits);

        if ($imbalance !== 0) {
            $msg = "CRITICAL LEDGER IMBALANCE DETECTED! Total Debits: {$totalDebits} Santim, Total Credits: {$totalCredits} Santim. Discrepancy: {$imbalance} Santim.";
            Log::critical($msg, [
                'total_debits' => $totalDebits,
                'total_credits' => $totalCredits,
                'imbalance' => $imbalance,
            ]);

            // Dispatch P1 Alert via FCM Admin topic
            $fcm->sendCriticalAlert(
                'CRITICAL LEDGER IMBALANCE',
                $msg,
                ['imbalance_santim' => (string) $imbalance]
            );

            $this->error($msg);

            return self::FAILURE;
        }

        $totalVolumeEtb = number_format($totalDebits / 100, 2);
        $this->info("Nightly ledger reconciliation balanced. Total ledger volume: {$totalVolumeEtb} ETB ({$totalDebits} Santim).");
        Log::info("Nightly ledger reconciliation verified. Double-entry balanced perfectly across {$totalDebits} Santim.");

        return self::SUCCESS;
    }
}
