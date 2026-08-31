<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Drop Postgres trigger & function proteksi status completed
        DB::statement('DROP TRIGGER IF EXISTS trg_prevent_update_completed_kloter ON kloters');
        DB::statement('DROP FUNCTION IF EXISTS prevent_update_completed_kloter');

        // 2. Map data status existing (ready->draft, completed->archived, cancelled->archived)
        DB::statement("UPDATE kloters SET status = 'draft' WHERE status = 'ready'");
        DB::statement("UPDATE kloters SET status = 'archived' WHERE status IN ('completed', 'cancelled')");

        // 3. Drop constraint check enum lama dan ganti dengan enum 3 nilai (draft, active, archived)
        DB::statement('ALTER TABLE kloters DROP CONSTRAINT IF EXISTS kloters_status_check');
        DB::statement("ALTER TABLE kloters ADD CONSTRAINT kloters_status_check CHECK (status IN ('draft', 'active', 'archived'))");
        DB::statement("ALTER TABLE kloters ALTER COLUMN status SET DEFAULT 'draft'");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE kloters DROP CONSTRAINT IF EXISTS kloters_status_check');
        DB::statement("ALTER TABLE kloters ADD CONSTRAINT kloters_status_check CHECK (status IN ('draft', 'ready', 'active', 'completed', 'cancelled'))");
    }
};
