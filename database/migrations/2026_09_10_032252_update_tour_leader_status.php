<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("
            ALTER TABLE tour_leaders
            DROP CONSTRAINT tour_leaders_status_check
        ");

        DB::statement("
            ALTER TABLE tour_leaders
            ADD CONSTRAINT tour_leaders_status_check
            CHECK (status IN ('active', 'resting', 'standby', 'inactive'))
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("
            ALTER TABLE tour_leaders
            DROP CONSTRAINT tour_leaders_status_check
        ");

        DB::statement("
            ALTER TABLE tour_leaders
            ADD CONSTRAINT tour_leaders_status_check
            CHECK (status IN ('active', 'inactive'))
        ");
    }
};
