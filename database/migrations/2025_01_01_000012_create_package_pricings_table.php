<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_pricings', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('package_id')->constrained('packages')->cascadeOnDelete();
            $table->string('room_type', 30);
            $table->decimal('price', 14, 2);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_pricings');
    }
};
