<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payout_destinations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('delala_id')->constrained('users')->cascadeOnDelete();
            $table->string('method'); // bank | telebirr
            $table->string('bank_name')->nullable(); // CBE | BOA | Awash | etc.
            $table->string('account_holder_name');
            $table->string('account_number');
            $table->boolean('is_verified_by_admin')->default(false);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['delala_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_destinations');
    }
};
