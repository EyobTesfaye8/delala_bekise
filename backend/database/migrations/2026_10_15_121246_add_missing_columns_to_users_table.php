<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // SRS NFR-SEC-2: failed login lockout
            $table->unsignedTinyInteger('login_attempt_count')->default(0)->after('avatar_s3_key');
            $table->timestamp('locked_until')->nullable()->after('login_attempt_count');

            // SRS FR-4.5: PII circumvention strike count
            $table->unsignedTinyInteger('circumvention_strike_count')->default(0)->after('locked_until');

            // Admin TOTP 2FA (Module 10)
            $table->string('totp_secret')->nullable()->after('circumvention_strike_count');
            $table->timestamp('totp_enabled_at')->nullable()->after('totp_secret');

            $table->index(['locked_until']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['locked_until']);
            $table->dropColumn([
                'login_attempt_count',
                'locked_until',
                'circumvention_strike_count',
                'totp_secret',
                'totp_enabled_at',
            ]);
        });
    }
};
