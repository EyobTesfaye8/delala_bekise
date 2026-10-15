<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Maker-Checker / Four-Eyes principle for operations > 10,000 ETB.
     * SRS §3: Junior Admin initiates, Senior Admin approves.
     */
    public function up(): void
    {
        Schema::create('admin_approval_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('initiated_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type'); // force_release | force_refund | ledger_adjustment | manual_payout
            $table->foreignId('escrow_transaction_id')->nullable()->constrained('escrow_transactions')->nullOnDelete();
            $table->foreignId('payout_request_id')->nullable()->constrained('payout_requests')->nullOnDelete();
            $table->unsignedBigInteger('amount_santim');
            $table->string('status')->default('pending_senior_approval'); // pending_senior_approval | approved | rejected
            $table->text('justification_note');
            $table->text('senior_note')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_approval_requests');
    }
};
