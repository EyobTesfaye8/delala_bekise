<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // system_chapa_holding | buyer_escrow_liability | platform_revenue_wallet | delala_pending_wallet | delala_available_wallet
            $table->string('name');
            $table->string('type'); // asset | liability | equity
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(); // null for system accounts, set for per-delala accounts
            $table->timestamps();

            $table->index(['code', 'user_id']);
        });

        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_ref')->index(); // groups the debit/credit pair
            $table->foreignId('ledger_account_id')->constrained('ledger_accounts');
            $table->foreignId('escrow_transaction_id')->nullable()->constrained('escrow_transactions')->nullOnDelete();
            $table->string('direction'); // debit | credit
            $table->unsignedBigInteger('amount_santim');
            $table->string('description');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['ledger_account_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('ledger_accounts');
    }
};
