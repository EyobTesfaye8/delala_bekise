<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delala_jobs', function (Blueprint $table) {
            // UUIDv7 public identifier (NFR-SEC-3)
            $table->uuid('uuid')->unique()->nullable()->after('id');

            // Separate location fields for filtering (FR-3.3)
            $table->string('location_region')->nullable()->after('location');
            $table->string('location_subcity')->nullable()->after('location_region');

            // Cancellation metadata
            $table->string('canceled_reason')->nullable()->after('status'); // EXPIRED | BUYER_CANCELED | ADMIN

            // Indexes
            $table->index(['status', 'created_at']);
            $table->index(['status', 'location_region']);
        });
    }

    public function down(): void
    {
        Schema::table('delala_jobs', function (Blueprint $table) {
            $table->dropIndex(['status', 'created_at']);
            $table->dropIndex(['status', 'location_region']);
            $table->dropColumn(['uuid', 'location_region', 'location_subcity', 'canceled_reason']);
        });
    }
};
