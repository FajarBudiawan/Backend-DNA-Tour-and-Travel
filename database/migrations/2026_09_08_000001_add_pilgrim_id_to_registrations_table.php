<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
si.     *
     * Catatan penting — field ini BUKAN foreign key dan BUKAN identitas login:
     * - Diisi MANUAL oleh admin di form Registrasi, murni sebagai informasi/
     *   referensi internal untuk data pendaftaran itu sendiri.
     * - TIDAK unique — boleh duplikat antar baris registrations, dan TIDAK
     *   berelasi sama sekali dengan tabel `jamaah` atau kolom `jamaah.login_id`.
     * - Identitas login untuk web mobile jamaah tetap dan hanya ditangani oleh
     *   `jamaah.login_id` (lihat migration 2026_09_02_070000).
     *
     * Halaman Registrasi dan halaman Jamaah tetap dua entitas yang berdiri
     * sendiri-sendiri, masing-masing punya field "ID Jamaah" versinya sendiri.
     */
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->string('pilgrim_id', 30)
                ->nullable()
                ->after('registration_number');
        });
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropColumn('pilgrim_id');
        });
    }
};