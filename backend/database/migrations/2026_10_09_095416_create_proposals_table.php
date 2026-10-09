<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained('delala_jobs')->cascadeOnDelete();
            $table->foreignId('delala_id')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('pending'); // pending | negotiating | accepted | rejected | withdrawn
            $table->text('cover_note')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['job_id', 'delala_id']); // one active proposal per delala per job
            $table->index(['job_id', 'status']);
            $table->index(['delala_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proposals');
    }
};
