<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tambah kolom name (nullable sementara) & flight_code
        Schema::table('kloters', function (Blueprint $table) {
            $table->string('name', 150)->nullable()->after('package_id');
            $table->string('flight_code', 50)->nullable()->after('code');
        });

        // 2. Backfill kolom name untuk data existing jika belum ada
        DB::statement("UPDATE kloters SET name = 'Kloter ' || code WHERE name IS NULL OR name = ''");

        // 3. Ubah kolom name menjadi NOT NULL
        Schema::table('kloters', function (Blueprint $table) {
            $table->string('name', 150)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('kloters', function (Blueprint $table) {
            $table->dropColumn(['name', 'flight_code']);
        });
    }
};
