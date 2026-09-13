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
        DB::statement("
            ALTER TABLE expenses
            DROP CONSTRAINT IF EXISTS expenses_category_check
        ");

        DB::statement("
            ALTER TABLE expenses
            ADD CONSTRAINT expenses_category_check
            CHECK (
                category IN (
                    'akomodasi_tiket',
                    'perlengkapan',
                    'operasional_bus',
                    'lainnya'
                )
            )
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Pastikan tidak ada data dengan kategori 'lainnya'
        DB::statement("
            ALTER TABLE expenses
            DROP CONSTRAINT IF EXISTS expenses_category_check
        ");

        DB::statement("
            ALTER TABLE expenses
            ADD CONSTRAINT expenses_category_check
            CHECK (
                category IN (
                    'akomodasi_tiket',
                    'perlengkapan',
                    'operasional_bus'
                )
            )
        ");
    }
};