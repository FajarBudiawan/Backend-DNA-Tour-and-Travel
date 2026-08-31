<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('muthawwifs', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('name', 150);
            $table->string('language', 255);
            $table->text('experience')->nullable();
            $table->enum('status', ['active', 'standby'])->default('standby');
            $table->timestamps();
        });

        Schema::create('kloter_muthawwif_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('kloter_id')->constrained('kloters')->cascadeOnDelete();
            $table->foreignUuid('muthawwif_id')->constrained('muthawwifs')->restrictOnDelete();
            $table->timestamp('assigned_at')->useCurrent();
            $table->unique('kloter_id');
            $table->unique('muthawwif_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kloter_muthawwif_assignments');
        Schema::dropIfExists('muthawwifs');
    }
};
