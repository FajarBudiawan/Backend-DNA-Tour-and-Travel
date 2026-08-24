<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_facilities', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('package_id')->constrained('packages')->cascadeOnDelete();
            $table->enum('facility_type', ['hotel', 'bus', 'airline']); // polymorphic, tanpa FK keras
            $table->uuid('reference_id');
            $table->index(['facility_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_facilities');
    }
};
