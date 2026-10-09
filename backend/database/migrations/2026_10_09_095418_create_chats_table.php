<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_id')->constrained('proposals')->cascadeOnDelete();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('delala_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_read_only')->default(false);
            $table->timestamps();

            $table->unique('proposal_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chats');
    }
};
