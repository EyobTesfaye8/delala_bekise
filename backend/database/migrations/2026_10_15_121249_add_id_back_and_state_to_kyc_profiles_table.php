<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kyc_profiles', function (Blueprint $table) {
            // SRS FR-2.3: Front AND Back of ID document
            $table->string('id_document_back_s3_key')->nullable()->after('id_document_s3_key');
        });
    }

    public function down(): void
    {
        Schema::table('kyc_profiles', function (Blueprint $table) {
            $table->dropColumn(['id_document_back_s3_key']);
        });
    }
};
