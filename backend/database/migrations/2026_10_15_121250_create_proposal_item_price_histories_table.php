<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proposal_item_price_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_item_id')->constrained('proposal_items')->cascadeOnDelete();
            $table->foreignId('changed_by_id')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('old_amount_santim');
            $table->unsignedBigInteger('new_amount_santim');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['proposal_item_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proposal_item_price_histories');
    }
};
