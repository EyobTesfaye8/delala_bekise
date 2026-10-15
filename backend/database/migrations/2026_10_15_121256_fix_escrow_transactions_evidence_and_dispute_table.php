<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('escrow_transactions', function (Blueprint $table) {
            // SRS §4.3: Dispute reason code (FR-5.2)
            $table->string('dispute_reason_code')->nullable()->after('dispute_evidence_s3_key');
            // dispute_evidence_s3_key -> rename to json array (use new column, keep old for expand-contract)
            $table->json('dispute_evidence_s3_keys')->nullable()->after('dispute_reason_code'); // array of 1-5 keys
            // Concurrent payment "First-to-Fund Wins" — track overpayment refund reference
            $table->string('overpayment_tx_ref')->nullable()->after('chapa_tx_ref');

            $table->index(['status', 'cancellation_deadline_at']);
        });
    }

    public function down(): void
    {
        Schema::table('escrow_transactions', function (Blueprint $table) {
            $table->dropIndex(['status', 'cancellation_deadline_at']);
            $table->dropColumn(['dispute_reason_code', 'dispute_evidence_s3_keys', 'overpayment_tx_ref']);
        });
    }
};
