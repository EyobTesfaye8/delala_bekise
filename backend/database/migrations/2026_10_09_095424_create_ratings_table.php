<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escrow_transaction_id')->constrained('escrow_transactions')->cascadeOnDelete();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('delala_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('stars'); // 1–5
            $table->text('review')->nullable();
            $table->timestamps();

            $table->unique('escrow_transaction_id'); // one rating per completed escrow
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ratings');
    }
};
