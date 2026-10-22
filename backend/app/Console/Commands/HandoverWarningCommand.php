<?php

namespace App\Console\Commands;

use App\Models\EscrowTransaction;
use App\Services\FcmService;
use App\States\Escrow\HandoverDeclared;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class HandoverWarningCommand extends Command
{
    protected $signature = 'delala:handover-warning';

    protected $description = 'Sends 96-hour countdown warning (24h before auto-release) to Buyer for active handovers';

    public function handle(FcmService $fcm): int
    {
        // Finds handovers expiring within the next 24 hours that haven't received the warning yet
        $pendingWarnings = EscrowTransaction::where('status', HandoverDeclared::$name)
            ->whereNotNull('auto_release_at')
            ->where('auto_release_at', '<=', now()->addHours(24))
            ->whereNull('handover_warning_sent_at')
            ->with(['buyer', 'job'])
            ->get();

        $count = 0;

        foreach ($pendingWarnings as $escrow) {
            $buyer = $escrow->buyer;

            if ($buyer && $buyer->fcm_token) {
                $fcm->sendToToken(
                    $buyer->fcm_token,
                    'Action Required: 24h Remaining to Review Handover',
                    "You have 24 hours remaining to review your deal for '{$escrow->job->title}' before escrow funds automatically release to the broker.",
                    ['escrow_id' => (string) $escrow->id, 'type' => 'handover_warning']
                );
            }

            $escrow->update(['handover_warning_sent_at' => now()]);

            Log::info("Sent 96-hour handover auto-release warning for Escrow #{$escrow->id} to Buyer #{$escrow->buyer_id}.");
            $count++;
        }

        $this->info("Dispatched {$count} handover 96h countdown warnings.");

        return self::SUCCESS;
    }
}
