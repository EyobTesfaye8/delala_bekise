<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payout_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delala_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('amount_santim');
            $table->string('status')->default('pending'); // pending | paid | cancelled
            $table->string('bank_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('telebirr_number')->nullable();
            $table->string('payment_method'); // bank | telebirr
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->text('admin_note')->nullable();
            $table->timestamps();

            $table->index(['delala_id', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_requests');
    }
};
