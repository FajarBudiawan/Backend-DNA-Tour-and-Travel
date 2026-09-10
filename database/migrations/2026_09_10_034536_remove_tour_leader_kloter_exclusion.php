<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('
            ALTER TABLE kloter_leader_assignments
            DROP CONSTRAINT excl_tour_leader_kloter
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('
            ALTER TABLE kloter_leader_assignments
            ADD CONSTRAINT excl_tour_leader_kloter
            EXCLUDE USING gist (
                tour_leader_id WITH =,
                kloter_id WITH <>
            )
        ');
    }
};