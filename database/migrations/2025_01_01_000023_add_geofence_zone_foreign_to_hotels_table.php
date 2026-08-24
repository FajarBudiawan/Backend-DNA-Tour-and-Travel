<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Ditunda ke migration terpisah karena hotels dan geofence_zones saling mereferensikan
    // (Hotel.geofence_zone_id -> GeofenceZone, GeofenceZone.hotel_id -> Hotel).
    public function up(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->foreign('geofence_zone_id')
                ->references('id')->on('geofence_zones')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->dropForeign(['geofence_zone_id']);
        });
    }
};
