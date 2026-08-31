<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Nama vendor / penerima pengeluaran
            $table->string('vendor', 150);

            // Kategori pengeluaran
            $table->enum('category', [
                'akomodasi_tiket',
                'perlengkapan',
                'operasional_bus',
            ]);

            // Nominal pengeluaran
            $table->decimal('amount', 15, 2);

            // Metode pembayaran
            $table->enum('payment_method', [
                'bca_transfer',
                'mandiri_transfer',
                'bsi_transfer',
                'cash',
                'edc_qris',
            ]);

            // Tanggal pengeluaran
            $table->date('expense_date');

            // Nomor referensi (auto-generated jika kosong: TRX-YYYY-XXX)
            $table->string('reference_number', 50)->nullable()->unique();

            // Catatan tambahan
            $table->string('notes', 255)->nullable();

            // User / Admin yang mencatat pengeluaran
            $table->foreignUuid('recorded_by')
                ->constrained('internal_users')
                ->restrictOnDelete();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
