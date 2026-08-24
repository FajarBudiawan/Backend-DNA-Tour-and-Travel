<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kloter_buses', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('kloter_id')->constrained('kloters')->cascadeOnDelete();
            $table->foreignUuid('bus_id')->constrained('buses')->restrictOnDelete();
            $table->unique(['kloter_id', 'bus_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kloter_buses');
    }
};
