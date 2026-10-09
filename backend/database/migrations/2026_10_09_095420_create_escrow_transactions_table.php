<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('escrow_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->foreignId('job_id')->constrained('delala_jobs');
            $table->foreignId('proposal_item_id')->constrained('proposal_items');
            $table->foreignId('buyer_id')->constrained('users');
            $table->foreignId('delala_id')->constrained('users');
            $table->unsignedBigInteger('commission_amount_santim'); // gross amount in Santim
            $table->unsignedBigInteger('platform_fee_santim');      // 5%
            $table->unsignedBigInteger('delala_amount_santim');     // 95%
            $table->string('status')->default('pending_payment');
            // pending_payment | funded | cancellation_requested | handover_declared
            // disputed | released | force_released | force_refunded | refund_failed
            $table->string('chapa_tx_ref')->unique()->nullable();
            $table->string('chapa_checkout_url')->nullable();
            $table->timestamp('chapa_paid_at')->nullable();
            $table->timestamp('handover_declared_at')->nullable();
            $table->timestamp('auto_release_at')->nullable(); // handover + 120hr
            $table->timestamp('cancellation_requested_at')->nullable();
            $table->timestamp('cancellation_deadline_at')->nullable(); // +48hr
            $table->text('dispute_evidence_s3_key')->nullable();
            $table->text('admin_resolution_note')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->json('chapa_webhook_payload')->nullable(); // raw webhook for audit
            $table->timestamps();

            $table->index(['status', 'auto_release_at']);
            $table->index(['buyer_id', 'status']);
            $table->index(['delala_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('escrow_transactions');
    }
};
