<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotels', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('name', 150);
            $table->enum('city', ['Makkah', 'Madinah']);
            $table->smallInteger('star_rating')->nullable(); // 1-5
            $table->integer('distance_to_mosque')->nullable(); // meter
            $table->string('contact_info', 255)->nullable();
            // FK ke geofence_zones ditambahkan belakangan (lihat migration add_geofence_zone_foreign_to_hotels)
            $table->uuid('geofence_zone_id')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotels');
    }
};
