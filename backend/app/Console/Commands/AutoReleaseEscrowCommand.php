<?php

namespace App\Console\Commands;

use App\Models\EscrowTransaction;
use App\Services\LedgerService;
use App\States\Escrow\HandoverDeclared;
use App\States\Escrow\Released;
use App\States\Job\Completed;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AutoReleaseEscrowCommand extends Command
{
    protected $signature = 'delala:auto-release-escrow';

    protected $description = 'Auto-releases escrow transactions where 120-hour handover review window has expired (FR-5.1)';

    public function handle(LedgerService $ledger): int
    {
        $expiredEscrows = EscrowTransaction::where('status', HandoverDeclared::$name)
            ->whereNotNull('auto_release_at')
            ->where('auto_release_at', '<=', now())
            ->get();

        $count = 0;

        foreach ($expiredEscrows as $escrow) {
            DB::transaction(function () use ($escrow, $ledger) {
                $escrow->status->transitionTo(Released::class);
                $escrow->update([
                    'resolved_at' => now(),
                    'resolved_by' => null,
                    'admin_resolution_note' => 'Auto-released by system scheduler after 120 hours without buyer dispute.',
                ]);

                $ledger->recordEscrowReleased($escrow);

                if ($escrow->job->status->canTransitionTo(Completed::class)) {
                    $escrow->job->status->transitionTo(Completed::class);
                }
            });

            Log::info("Escrow #{$escrow->id} auto-released after 120-hour timer expiration.");
            $count++;
        }

        $this->info("Successfully auto-released {$count} expired escrow transactions.");

        return self::SUCCESS;
    }
}
