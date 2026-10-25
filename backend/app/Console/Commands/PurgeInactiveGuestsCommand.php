<?php

namespace App\Console\Commands;

use App\Jobs\GarbageCollectInactiveGuestsJob;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('delala:purge-inactive-guests {--days=5 : Number of inactive days before purging}')]
#[Description('Garbage collect guest user accounts inactive for 5+ days with zero ongoing deals')]
class PurgeInactiveGuestsCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        if ($days <= 0) {
            $days = 5;
        }

        $purgedCount = (new GarbageCollectInactiveGuestsJob($days))->handle();

        $this->info("Purged {$purgedCount} inactive guest user(s) with zero ongoing deals.");

        return Command::SUCCESS;
    }
}
