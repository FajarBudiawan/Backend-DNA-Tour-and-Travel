<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registrations', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('registration_number', 30)->unique();

            // Informasi diri calon jamaah
            $table->string('full_name', 150);
            $table->string('passport_number', 50)->nullable();
            $table->char('nik', 16)->unique();
            $table->string('phone', 20);
            $table->date('birth_date');
            $table->string('gender', 1);

            // Administrasi
            $table->date('registration_date');
            $table->date('departure_date')->nullable();

            $table->foreignUuid('package_id')
                ->constrained('packages')
                ->restrictOnDelete();

            $table->foreignUuid('kloter_id')
                ->nullable()
                ->constrained('kloters')
                ->nullOnDelete();

            $table->string('meningitis_vaccine_status', 20)
                ->default('belum_vaksin');

            $table->string('photo_status', 20)
                ->default('belum_ada');

            // Biaya dan status
            $table->decimal('total_package_cost', 14, 2)
                ->default(30000000.00);

            $table->string('status', 20)
                ->default('unpaid');

            // Admin yang membuat pendaftaran
            $table->foreignUuid('created_by')
                ->constrained('internal_users')
                ->restrictOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registrations');
    }
};