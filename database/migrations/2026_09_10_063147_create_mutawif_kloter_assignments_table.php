<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mutawif_kloter_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));

            $table->foreignUuid('mutawif_id')
                ->constrained('mutawifs')
                ->cascadeOnDelete();

            $table->foreignUuid('kloter_id')
                ->constrained('kloters')
                ->cascadeOnDelete();

            $table->timestamp('assigned_at')->useCurrent();

            $table->unique(['mutawif_id', 'kloter_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mutawif_kloter_assignments');
    }
};