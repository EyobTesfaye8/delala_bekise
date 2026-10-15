<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proposals', function (Blueprint $table) {
            $table->uuid('uuid')->unique()->nullable()->after('id');
        });

        Schema::table('proposal_items', function (Blueprint $table) {
            $table->uuid('uuid')->unique()->nullable()->after('id');
            // Informational price field (FR-4.1)
            $table->unsignedBigInteger('estimated_item_price_santim')->nullable()->after('text_location');
        });
    }

    public function down(): void
    {
        Schema::table('proposal_items', function (Blueprint $table) {
            $table->dropColumn(['uuid', 'estimated_item_price_santim']);
        });

        Schema::table('proposals', function (Blueprint $table) {
            $table->dropColumn(['uuid']);
        });
    }
};
