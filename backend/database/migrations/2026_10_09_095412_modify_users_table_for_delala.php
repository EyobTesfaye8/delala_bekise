<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['name', 'email', 'password', 'email_verified_at', 'remember_token']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('full_name')->nullable();
            $table->string('phone')->unique();
            $table->string('email')->nullable()->unique();
            $table->string('password')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('remember_token', 100)->nullable();
            $table->string('type')->default('guest'); // guest | buyer | delala | admin
            $table->string('status')->default('active'); // active | suspended | deleted
            $table->timestamp('phone_verified_at')->nullable();
            $table->string('guest_uuid')->nullable()->unique();
            $table->string('avatar_s3_key')->nullable();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn([
                'full_name', 'phone', 'email', 'password', 'email_verified_at',
                'remember_token', 'type', 'status', 'phone_verified_at',
                'guest_uuid', 'avatar_s3_key',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
        });
    }
};
