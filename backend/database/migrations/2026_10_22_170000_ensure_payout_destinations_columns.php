<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payout_destinations')) {
            Schema::table('payout_destinations', function (Blueprint $table) {
                if (! Schema::hasColumn('payout_destinations', 'bank_code')) {
                    $table->string('bank_code')->nullable()->after('delala_id');
                }
                if (! Schema::hasColumn('payout_destinations', 'telebirr_number')) {
                    $table->string('telebirr_number')->nullable()->after('account_number');
                }
                if (! Schema::hasColumn('payout_destinations', 'verified_at')) {
                    $table->timestamp('verified_at')->nullable()->after('is_default');
                }
            });

            if (DB::getDriverName() === 'pgsql') {
                Schema::table('payout_destinations', function (Blueprint $table) {
                    if (Schema::hasColumn('payout_destinations', 'method')) {
                        $table->string('method')->nullable()->change();
                    }
                    if (Schema::hasColumn('payout_destinations', 'uuid')) {
                        $table->uuid('uuid')->nullable()->change();
                    }
                });
            }
        }

        // Allow mutual double-blind ratings per escrow on PostgreSQL
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE ratings DROP CONSTRAINT IF EXISTS ratings_escrow_transaction_id_unique;');
        }
    }

    public function down(): void {}
};
