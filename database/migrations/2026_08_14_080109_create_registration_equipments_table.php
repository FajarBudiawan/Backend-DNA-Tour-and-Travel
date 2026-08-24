<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registration_equipments', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Pendaftaran pemilik perlengkapan
            $table->foreignUuid('registration_id')
                ->constrained('registrations')
                ->cascadeOnDelete();

            // Nama perlengkapan
            $table->string('equipment_name', 100);

            // Status penerimaan
            $table->boolean('is_received')->default(false);

            // Waktu perlengkapan diterima
            $table->timestamp('received_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_equipments');
    }
};