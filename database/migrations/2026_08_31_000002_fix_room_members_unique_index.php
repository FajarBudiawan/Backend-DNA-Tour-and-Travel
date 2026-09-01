<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop the original unique constraint on (room_id, registration_id)
        Schema::table('room_members', function ($table) {
            $table->dropUnique('room_members_room_id_registration_id_unique');
        });

        // Create a partial unique index that only applies when registration_id is NOT NULL
        DB::statement('CREATE UNIQUE INDEX room_members_room_registration_unique ON room_members (room_id, registration_id) WHERE registration_id IS NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the partial unique index
        DB::statement('DROP INDEX IF EXISTS room_members_room_registration_unique');

        // Re‑create the original unique constraint (applies to all rows, including nulls)
        Schema::table('room_members', function ($table) {
            $table->unique(['room_id', 'registration_id']);
        });
    }
};
