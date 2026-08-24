<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('geofence_zones', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->enum('type', ['hotel', 'meeting_point', 'kloter_radius']);
            $table->foreignUuid('hotel_id')->nullable()->constrained('hotels')->nullOnDelete();
            $table->foreignUuid('kloter_id')->nullable()->constrained('kloters')->cascadeOnDelete();
            $table->decimal('radius_meter', 8, 2)->nullable();
            $table->geometry('polygon_coordinates')->nullable(); // PostGIS, untuk zona non-lingkaran
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geofence_zones');
    }
};
