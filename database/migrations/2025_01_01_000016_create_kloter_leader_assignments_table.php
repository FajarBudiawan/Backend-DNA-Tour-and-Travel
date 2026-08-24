<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kloter_leader_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('kloter_id')->constrained('kloters')->cascadeOnDelete();
            $table->foreignUuid('tour_leader_id')->constrained('tour_leaders')->restrictOnDelete();
            $table->timestamp('assigned_at')->useCurrent();
        });

        // BR: no overlapping date range per tour_leader (pakai daterange dari Kloter via join tidak trivial
        // di level kolom ini; disederhanakan jadi EXCLUDE per tour_leader+kloter pair aktif)
        DB::statement(<<<SQL
            ALTER TABLE kloter_leader_assignments
            ADD CONSTRAINT excl_tour_leader_kloter
            EXCLUDE USING gist (tour_leader_id WITH =, kloter_id WITH <>)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('kloter_leader_assignments');
    }
};
