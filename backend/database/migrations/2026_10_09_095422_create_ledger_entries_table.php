<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('ledger_entries');
    }

    public function down(): void
    {
        // ledger_entries is created in the ledger_accounts migration
    }
};
