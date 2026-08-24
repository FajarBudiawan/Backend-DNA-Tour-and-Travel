<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kloters', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('package_id')->constrained('packages')->restrictOnDelete();
            $table->string('code', 30)->unique();
            $table->date('departure_date');
            $table->date('return_date');
            $table->foreignUuid('hotel_makkah_id')->nullable()->constrained('hotels')->nullOnDelete();
            $table->foreignUuid('hotel_madinah_id')->nullable()->constrained('hotels')->nullOnDelete();
            $table->enum('status', ['draft', 'ready', 'active', 'completed', 'cancelled'])->default('draft');
            $table->string('cancellation_reason', 255)->nullable();
            $table->timestamps();
        });

        // BRD Konstrain #23: kloter completed terkunci permanen (read-only)
        DB::statement(<<<SQL
            CREATE OR REPLACE FUNCTION prevent_update_completed_kloter()
            RETURNS TRIGGER AS \$\$
            BEGIN
                IF OLD.status = 'completed' THEN
                    RAISE EXCEPTION 'Kloter dengan status completed tidak dapat diubah';
                END IF;
                RETURN NEW;
            END;
            \$\$ LANGUAGE plpgsql;
        SQL);

        DB::statement(<<<SQL
            CREATE TRIGGER trg_prevent_update_completed_kloter
            BEFORE UPDATE ON kloters
            FOR EACH ROW
            EXECUTE FUNCTION prevent_update_completed_kloter();
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS trg_prevent_update_completed_kloter ON kloters');
        DB::statement('DROP FUNCTION IF EXISTS prevent_update_completed_kloter');
        Schema::dropIfExists('kloters');
    }
};
