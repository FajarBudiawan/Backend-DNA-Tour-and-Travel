<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('early_warning_events', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('jamaah_id')->constrained('jamaah')->cascadeOnDelete();
            $table->foreignUuid('geofence_zone_id')->constrained('geofence_zones')->cascadeOnDelete();
            $table->foreignUuid('kloter_id')->constrained('kloters')->cascadeOnDelete();
            $table->timestamp('triggered_at')->useCurrent();
            $table->timestamp('returned_at')->nullable();
            $table->index(['jamaah_id', 'triggered_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('early_warning_events');
    }
};
