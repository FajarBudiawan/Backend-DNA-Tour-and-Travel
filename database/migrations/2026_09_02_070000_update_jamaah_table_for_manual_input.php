<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Revisi [2026-09-02]: Modul Jamaah dijadikan master data mandiri.
     *
     * Perubahan:
     * 1. login_id: VARCHAR(20) → VARCHAR(10). Field ini diisi MANUAL oleh admin
     *    (bukan auto-generate). Unique constraint dipertahankan.
     * 2. Tambahkan kolom identitas dokumen perjalanan:
     *    passport_number, visa_number, nationality.
     * 3. Tambahkan FK: package_id (→ packages), kloter_id (→ kloters). Nullable
     *    karena jamaah bisa diinput sebelum paket/kloter ditentukan.
     * 4. Tambahkan info logistik perjalanan yang sebelumnya tidak ada di tabel ini
     *    (sebelumnya hanya ada di registrations):
     *    hotel_makkah, hotel_madinah, departure_date, return_date.
     * 5. Tambahkan plain-text tour_leader dan mutawif_local. Tidak dibuat FK
     *    karena mutawif lokal (staf Saudi) tidak ada di tabel internal, dan
     *    tour_leader bisa berubah-ubah tanpa harus terikat ke entitas staf.
     *
     * TIDAK DISENTUH: registration_payments, room_members, registration_equipments.
     */
    public function up(): void
    {
        // ── Step 1: Ubah panjang login_id dari VARCHAR(20) → VARCHAR(10) ──────────
        // Di PostgreSQL, ALTER COLUMN + USING diperlukan jika ada constraint.
        // Hapus dulu unique constraint lama, resize, lalu buat ulang.
        DB::statement("
            ALTER TABLE jamaah
            ALTER COLUMN login_id TYPE VARCHAR(10)
            USING SUBSTRING(login_id, 1, 10)
        ");

        Schema::table('jamaah', function (Blueprint $table) {
            // ── Step 2: Tambah kolom identitas dokumen ───────────────────────────
            $table->string('passport_number', 30)->nullable()->after('nik');
            $table->string('visa_number', 50)->nullable()->after('passport_number');
            $table->string('nationality', 100)->nullable()->default('Indonesia')->after('visa_number');

            // ── Step 3: FK ke packages dan kloters ──────────────────────────────
            $table->foreignUuid('package_id')
                ->nullable()
                ->after('nationality')
                ->constrained('packages')
                ->nullOnDelete();

            $table->foreignUuid('kloter_id')
                ->nullable()
                ->after('package_id')
                ->constrained('kloters')
                ->nullOnDelete();

            // ── Step 4: Informasi logistik perjalanan ────────────────────────────
            $table->string('hotel_makkah', 200)->nullable()->after('kloter_id');
            $table->string('hotel_madinah', 200)->nullable()->after('hotel_makkah');
            $table->date('departure_date')->nullable()->after('hotel_madinah');
            $table->date('return_date')->nullable()->after('departure_date');

            // ── Step 5: Pembimbing (plain text, bukan FK) ────────────────────────
            $table->string('tour_leader', 200)->nullable()->after('return_date');
            $table->string('mutawif_local', 200)->nullable()->after('tour_leader');
        });
    }

    public function down(): void
    {
        Schema::table('jamaah', function (Blueprint $table) {
            $table->dropForeign(['package_id']);
            $table->dropForeign(['kloter_id']);
            $table->dropColumn([
                'passport_number',
                'visa_number',
                'nationality',
                'package_id',
                'kloter_id',
                'hotel_makkah',
                'hotel_madinah',
                'departure_date',
                'return_date',
                'tour_leader',
                'mutawif_local',
            ]);
        });

        // Kembalikan login_id ke VARCHAR(20)
        DB::statement("
            ALTER TABLE jamaah
            ALTER COLUMN login_id TYPE VARCHAR(20)
        ");
    }
};
