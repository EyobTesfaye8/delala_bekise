<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            // SRS FR-3.1: JSON Schema Draft-07 for leaf category attribute validation
            $table->jsonb('json_schema')->nullable()->after('parent_id');
            $table->boolean('is_leaf')->default(false)->after('json_schema');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['json_schema', 'is_leaf']);
        });
    }
};
