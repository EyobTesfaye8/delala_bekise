<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('delala:purge-unverified')]
#[Description('Purge direct registrations remaining unverified after 72 hours with zero associated jobs')]
class PurgeUnverifiedAccountsCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $cutoff = now()->subHours(72);

        $count = User::query()
            ->where('status', 'unverified')
            ->where('created_at', '<=', $cutoff)
            ->whereDoesntHave('jobs')
            ->forceDelete();

        $this->info("Purged {$count} unverified account(s) older than 72 hours.");

        return Command::SUCCESS;
    }
}
