<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Users Table Columns
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'circumvention_strike_count')) {
                $table->unsignedInteger('circumvention_strike_count')->default(0)->after('status');
            }
            if (! Schema::hasColumn('users', 'login_attempt_count')) {
                $table->unsignedInteger('login_attempt_count')->default(0)->after('circumvention_strike_count');
            }
            if (! Schema::hasColumn('users', 'locked_until')) {
                $table->timestamp('locked_until')->nullable()->after('login_attempt_count');
            }
            if (! Schema::hasColumn('users', 'totp_secret')) {
                $table->text('totp_secret')->nullable()->after('locked_until');
            }
            if (! Schema::hasColumn('users', 'totp_enabled_at')) {
                $table->timestamp('totp_enabled_at')->nullable()->after('totp_secret');
            }
            if (! Schema::hasColumn('users', 'fcm_token')) {
                $table->string('fcm_token')->nullable()->after('totp_enabled_at');
            }
        });

        // 2. Delala Jobs Table Columns
        Schema::table('delala_jobs', function (Blueprint $table) {
            if (! Schema::hasColumn('delala_jobs', 'uuid')) {
                $table->uuid('uuid')->nullable()->unique()->after('id');
            }
            if (! Schema::hasColumn('delala_jobs', 'location_region')) {
                $table->string('location_region')->nullable()->after('location');
            }
            if (! Schema::hasColumn('delala_jobs', 'location_subcity')) {
                $table->string('location_subcity')->nullable()->after('location_region');
            }
            if (! Schema::hasColumn('delala_jobs', 'canceled_reason')) {
                $table->string('canceled_reason')->nullable()->after('status');
            }
            if (! Schema::hasColumn('delala_jobs', 'budget_min_santim')) {
                $table->unsignedBigInteger('budget_min_santim')->nullable()->after('budget_min');
            }
            if (! Schema::hasColumn('delala_jobs', 'budget_max_santim')) {
                $table->unsignedBigInteger('budget_max_santim')->nullable()->after('budget_max');
            }
        });

        // 3. Proposal Items Table Columns
        Schema::table('proposal_items', function (Blueprint $table) {
            if (! Schema::hasColumn('proposal_items', 'uuid')) {
                $table->uuid('uuid')->nullable()->unique()->after('id');
            }
            if (! Schema::hasColumn('proposal_items', 'estimated_item_price_santim')) {
                $table->unsignedBigInteger('estimated_item_price_santim')->nullable()->after('text_location');
            }
        });

        // 4. KYC Profiles Table Columns
        Schema::table('kyc_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('kyc_profiles', 'id_document_back_s3_key')) {
                $table->string('id_document_back_s3_key')->nullable()->after('id_document_s3_key');
            }
        });

        // 5. Categories Table Columns
        Schema::table('categories', function (Blueprint $table) {
            if (! Schema::hasColumn('categories', 'json_schema')) {
                $table->json('json_schema')->nullable()->after('attributes_schema');
            }
            if (! Schema::hasColumn('categories', 'is_leaf')) {
                $table->boolean('is_leaf')->default(true)->after('json_schema');
            }
        });

        // 6. Escrow Transactions Table Columns
        Schema::table('escrow_transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('escrow_transactions', 'dispute_reason_code')) {
                $table->string('dispute_reason_code')->nullable()->after('dispute_evidence_s3_key');
            }
            if (! Schema::hasColumn('escrow_transactions', 'dispute_evidence_s3_keys')) {
                $table->json('dispute_evidence_s3_keys')->nullable()->after('dispute_reason_code');
            }
            if (! Schema::hasColumn('escrow_transactions', 'overpayment_tx_ref')) {
                $table->string('overpayment_tx_ref')->nullable()->after('chapa_tx_ref');
            }
            if (! Schema::hasColumn('escrow_transactions', 'handover_warning_sent_at')) {
                $table->timestamp('handover_warning_sent_at')->nullable()->after('auto_release_at');
            }
        });

        // 7. Proposal Item Price Histories Table
        if (! Schema::hasTable('proposal_item_price_histories')) {
            Schema::create('proposal_item_price_histories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('proposal_item_id')->constrained('proposal_items')->cascadeOnDelete();
                $table->unsignedBigInteger('old_amount_santim');
                $table->unsignedBigInteger('new_amount_santim');
                $table->foreignId('changed_by_id')->constrained('users')->cascadeOnDelete();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['proposal_item_id', 'created_at']);
            });
        }

        // 8. Payout Destinations Table
        if (! Schema::hasTable('payout_destinations')) {
            Schema::create('payout_destinations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('delala_id')->constrained('users')->cascadeOnDelete();
                $table->string('bank_code');
                $table->string('bank_name');
                $table->string('account_number');
                $table->string('account_holder_name');
                $table->string('telebirr_number')->nullable();
                $table->boolean('is_default')->default(false);
                $table->timestamp('verified_at')->nullable();
                $table->timestamps();

                $table->index(['delala_id', 'is_default']);
            });
        }

        // 9. Audit Logs Table
        if (! Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->string('actor_role')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->string('action');
                $table->string('auditable_type');
                $table->unsignedBigInteger('auditable_id');
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['auditable_type', 'auditable_id']);
                $table->index(['actor_id', 'created_at']);
            });
        }

        // 10. Admin Approval Requests Table
        if (! Schema::hasTable('admin_approval_requests')) {
            Schema::create('admin_approval_requests', function (Blueprint $table) {
                $table->id();
                $table->string('action_type');
                $table->string('entity_type');
                $table->unsignedBigInteger('entity_id');
                $table->unsignedBigInteger('amount_santim');
                $table->json('payload')->nullable();
                $table->foreignId('requested_by_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('approved_by_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('status')->default('PENDING');
                $table->text('justification_note');
                $table->text('rejection_reason')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamps();

                $table->index(['status', 'created_at']);
                $table->index(['entity_type', 'entity_id']);
            });
        }

        // 11. Notifications Table
        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->morphs('notifiable');
                $table->text('data');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        }

        // 12. Double-Blind Ratings Columns (FR-6.1)
        if (Schema::hasTable('ratings')) {
            Schema::table('ratings', function (Blueprint $table) {
                if (! Schema::hasColumn('ratings', 'rater_id')) {
                    $table->foreignId('rater_id')->nullable()->constrained('users')->cascadeOnDelete();
                }
                if (! Schema::hasColumn('ratings', 'ratee_id')) {
                    $table->foreignId('ratee_id')->nullable()->constrained('users')->cascadeOnDelete();
                }
                if (! Schema::hasColumn('ratings', 'is_revealed')) {
                    $table->boolean('is_revealed')->default(false);
                }
                if (! Schema::hasColumn('ratings', 'revealed_at')) {
                    $table->timestamp('revealed_at')->nullable();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_approval_requests');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('payout_destinations');
        Schema::dropIfExists('proposal_item_price_histories');
    }
};
