<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proposal_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_id')->constrained('proposals')->cascadeOnDelete();
            $table->string('item_name');
            $table->text('description')->nullable();
            $table->string('text_location')->nullable();
            $table->json('image_s3_keys')->nullable(); // array of S3 keys, max 5
            $table->unsignedBigInteger('commission_amount_santim'); // stored in Santim (ETB subunits)
            $table->string('status')->default('pending'); // pending | accepted_for_escrow | rejected
            $table->jsonb('attributes')->nullable(); // category-specific JSONB
            $table->timestamps();

            $table->index(['proposal_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proposal_items');
    }
};
