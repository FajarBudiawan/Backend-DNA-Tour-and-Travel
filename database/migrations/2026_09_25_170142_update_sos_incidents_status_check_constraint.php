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
            ALTER TABLE sos_incidents
            DROP CONSTRAINT IF EXISTS sos_incidents_status_check
        ');

        DB::statement("
            ALTER TABLE sos_incidents
            ADD CONSTRAINT sos_incidents_status_check
            CHECK (
                status IN (
                    'triggered',
                    'acknowledged',
                    'in_action',
                    'resolved',
                    'false_alarm'
                )
            )
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('
            ALTER TABLE sos_incidents
            DROP CONSTRAINT IF EXISTS sos_incidents_status_check
        ');

        DB::statement("
            ALTER TABLE sos_incidents
            ADD CONSTRAINT sos_incidents_status_check
            CHECK (
                status IN (
                    'triggered',
                    'acknowledged',
                    'resolved',
                    'false_alarm'
                )
            )
        ");
    }
};