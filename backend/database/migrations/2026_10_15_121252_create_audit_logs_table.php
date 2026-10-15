<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Immutable audit trail per SRS NFR-AUD-1.
     * The web application DB role should have INSERT+SELECT only
     * (UPDATE+DELETE explicitly revoked at PostgreSQL level by a DBA).
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_role')->nullable(); // guest | buyer | delala | junior_admin | senior_admin
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->string('auditable_type'); // App\Models\EscrowTransaction etc.
            $table->unsignedBigInteger('auditable_id');
            $table->string('event'); // created | updated | state_transitioned | force_released etc.
            $table->jsonb('old_values')->nullable();
            $table->jsonb('new_values')->nullable();
            $table->string('x_request_id', 36)->nullable(); // correlation ID
            $table->timestamp('created_at')->useCurrent();

            $table->index(['auditable_type', 'auditable_id']);
            $table->index(['actor_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
