<?php

namespace App\Console\Commands;

use App\Models\EscrowTransaction;
use App\Services\ChapaService;
use App\Services\EscrowService;
use App\States\Escrow\PendingPayment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ChapaPollingFallbackCommand extends Command
{
    protected $signature = 'delala:chapa-polling-fallback';

    protected $description = 'Queries Chapa verify API for pending payments older than 10 minutes (webhook circuit breaker)';

    public function handle(ChapaService $chapa, EscrowService $escrowService): int
    {
        $pendingEscrows = EscrowTransaction::where('status', PendingPayment::$name)
            ->whereNotNull('chapa_tx_ref')
            ->where('created_at', '<=', now()->subMinutes(10))
            ->where('created_at', '>=', now()->subHours(24))
            ->get();

        $recovered = 0;

        foreach ($pendingEscrows as $escrow) {
            $data = $chapa->verifyTransaction($escrow->chapa_tx_ref);

            if ($data && ($data['status'] ?? '') === 'success') {
                $escrowService->handlePaymentSuccess($escrow->chapa_tx_ref, $data);
                Log::info("Chapa Polling Fallback recovered missed webhook payment for Escrow #{$escrow->id} ({$escrow->chapa_tx_ref}).");
                $recovered++;
            }
        }

        $this->info("Chapa polling completed. Verified and recovered {$recovered} missed transactions.");

        return self::SUCCESS;
    }
}
