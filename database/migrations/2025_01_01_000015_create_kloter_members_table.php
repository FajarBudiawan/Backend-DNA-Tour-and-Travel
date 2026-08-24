<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kloter_members', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('kloter_id')->constrained('kloters')->cascadeOnDelete();
            $table->foreignUuid('jamaah_id')->constrained('jamaah')->restrictOnDelete();
            $table->enum('status', ['active', 'transferred', 'cancelled'])->default('active');
            $table->timestamp('joined_at')->useCurrent();
        });

        // BR: 1 Jamaah tidak boleh di >1 kloter aktif bersamaan (partial unique index)
        DB::statement(<<<SQL
            CREATE UNIQUE INDEX uniq_jamaah_active_kloter
            ON kloter_members (jamaah_id)
            WHERE status = 'active'
        SQL);

        // BR: maks 45 Jamaah/kloter -> divalidasi di application layer (COUNT sebelum INSERT),
        // Postgres tidak punya native "max rows per group" constraint tanpa trigger tambahan.
    }

    public function down(): void
    {
        Schema::dropIfExists('kloter_members');
    }
};
