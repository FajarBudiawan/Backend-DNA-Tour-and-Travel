<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('kloter_id')->constrained('kloters')->cascadeOnDelete();
            $table->foreignUuid('hotel_id')->nullable()->constrained('hotels')->nullOnDelete();
            $table->string('room_number', 30);
            $table->string('room_type', 30)->default('quad'); // quad (4), triple (3), double (2), single (1)
            $table->integer('capacity')->default(4);
            $table->string('gender', 1); // L / P
            $table->string('notes', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('room_members', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('room_id')->constrained('rooms')->cascadeOnDelete();
            $table->foreignUuid('registration_id')->constrained('registrations')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['room_id', 'registration_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_members');
        Schema::dropIfExists('rooms');
    }
};
