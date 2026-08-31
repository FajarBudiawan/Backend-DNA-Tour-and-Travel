<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tour_leaders', function (Blueprint $table) {
            $table->text('experience')->nullable();
            $table->text('performance')->nullable();
        });

        Schema::table('kloter_leader_assignments', function (Blueprint $table) {
            $table->unique('kloter_id');
            $table->unique('tour_leader_id');
        });

        DB::statement("ALTER TABLE tour_leaders DROP CONSTRAINT IF EXISTS tour_leaders_status_check");
        DB::statement("UPDATE tour_leaders SET status = 'standby' WHERE status = 'inactive'");
        DB::statement("ALTER TABLE tour_leaders ADD CONSTRAINT tour_leaders_status_check CHECK (status IN ('active', 'resting', 'standby'))");
    }

    public function down(): void
    {
        Schema::table('kloter_leader_assignments', function (Blueprint $table) {
            $table->dropUnique(['kloter_id']);
            $table->dropUnique(['tour_leader_id']);
        });

        DB::statement("UPDATE tour_leaders SET status = 'active' WHERE status IN ('resting', 'standby')");
        DB::statement('ALTER TABLE tour_leaders DROP CONSTRAINT IF EXISTS tour_leaders_status_check');
        DB::statement("ALTER TABLE tour_leaders ADD CONSTRAINT tour_leaders_status_check CHECK (status IN ('active', 'inactive'))");
        Schema::table('tour_leaders', fn (Blueprint $table) => $table->dropColumn(['experience', 'performance']));
    }
};
