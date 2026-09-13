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
        Schema::create('other_incomes', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Sumber pemasukan
            $table->string('source', 150);

            // Kategori pemasukan
            $table->string('category', 50);

            // Nominal pemasukan
            $table->decimal('amount', 15, 2);

            // Metode penerimaan uang
            $table->enum('payment_method', [
                'bca_transfer',
                'mandiri_transfer',
                'bsi_transfer',
                'cash',
                'edc_qris',
            ]);

            // Tanggal pemasukan
            $table->date('income_date');

            // Nomor referensi, misalnya INC-2026-001
            $table->string('reference_number', 50)
                ->nullable()
                ->unique();

            // Catatan tambahan
            $table->string('notes', 255)->nullable();

            // Admin yang mencatat transaksi
            $table->foreignUuid('recorded_by')
                ->constrained('internal_users')
                ->restrictOnDelete();

            $table->timestamps();

            // Soft delete
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('other_incomes');
    }
};