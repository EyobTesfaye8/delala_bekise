<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // ledger_entries is already created in the ledger_accounts migration
    }

    public function down(): void
    {
        // ledger_entries is created in the ledger_accounts migration
    }
};
