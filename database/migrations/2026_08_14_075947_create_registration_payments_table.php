<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registration_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Pendaftaran yang melakukan pembayaran
            $table->foreignUuid('registration_id')
                ->constrained('registrations')
                ->cascadeOnDelete();

            // Informasi pembayaran
            $table->decimal('amount', 14, 2);

            $table->string('payment_type', 20);
            // down_payment atau full_payment

            $table->string('payment_method', 30);
            // bca_transfer, mandiri_transfer, bsi_transfer,
            // cash, edc_qris

            $table->date('payment_date');

            // Admin yang mencatat pembayaran
            $table->foreignUuid('recorded_by')
                ->constrained('internal_users')
                ->restrictOnDelete();

            // Catatan pembayaran dari form
            $table->string('notes', 255)->nullable();

            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_payments');
    }
};