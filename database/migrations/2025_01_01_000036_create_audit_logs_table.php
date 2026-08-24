<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('actor_id')->constrained('internal_users')->restrictOnDelete();
            $table->string('action_type', 30); // create, update, delete
            $table->string('module', 50); // pilgrims, kloter, finance, dst
            $table->jsonb('before_value')->nullable();
            $table->jsonb('after_value')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['module', 'created_at']);
        });

        // AuditLog bersifat immutable: cegah UPDATE dan DELETE di level DB
        DB::statement(<<<SQL
            CREATE OR REPLACE FUNCTION prevent_audit_log_mutation()
            RETURNS TRIGGER AS \$\$
            BEGIN
                RAISE EXCEPTION 'audit_logs bersifat immutable, tidak dapat diubah atau dihapus';
            END;
            \$\$ LANGUAGE plpgsql;
        SQL);

        DB::statement(<<<SQL
            CREATE TRIGGER trg_prevent_audit_log_update
            BEFORE UPDATE OR DELETE ON audit_logs
            FOR EACH ROW
            EXECUTE FUNCTION prevent_audit_log_mutation();
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS trg_prevent_audit_log_update ON audit_logs');
        DB::statement('DROP FUNCTION IF EXISTS prevent_audit_log_mutation');
        Schema::dropIfExists('audit_logs');
    }
};
