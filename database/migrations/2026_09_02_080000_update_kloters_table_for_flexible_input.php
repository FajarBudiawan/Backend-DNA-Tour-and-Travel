<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // -------------------------------------------------------
        // 1. Ubah package_id menjadi nullable (saat ini NOT NULL)
        //    PostgreSQL: harus drop constraint FK dulu, lalu alter,
        //    lalu pasang kembali FK baru yang nullable.
        // -------------------------------------------------------
        DB::statement('ALTER TABLE kloters ALTER COLUMN package_id DROP NOT NULL');
        DB::statement('ALTER TABLE kloters ALTER COLUMN departure_date DROP NOT NULL');
        DB::statement('ALTER TABLE kloters ALTER COLUMN return_date DROP NOT NULL');

        // -------------------------------------------------------
        // 2. Tambahkan kolom plain-text untuk staf kloter
        //    (sejajar dengan pola jamaah.tour_leader & mutawif_local)
        // -------------------------------------------------------
        Schema::table('kloters', function (Blueprint $table) {
            $table->string('tour_leader', 200)->nullable()->after('status');
            $table->string('mutawif_local', 200)->nullable()->after('tour_leader');
        });
    }

    public function down(): void
    {
        // Kembalikan kolom tour_leader dan mutawif_local
        Schema::table('kloters', function (Blueprint $table) {
            $table->dropColumn(['tour_leader', 'mutawif_local']);
        });

        // Kembalikan package_id, departure_date, return_date jadi NOT NULL
        // CATATAN: Pastikan tidak ada data NULL sebelum menjalankan rollback ini.
        DB::statement('ALTER TABLE kloters ALTER COLUMN package_id SET NOT NULL');
        DB::statement('ALTER TABLE kloters ALTER COLUMN departure_date SET NOT NULL');
        DB::statement('ALTER TABLE kloters ALTER COLUMN return_date SET NOT NULL');
    }
};
