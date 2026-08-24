<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('family_relations', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('jamaah_id')->constrained('jamaah')->cascadeOnDelete();
            $table->string('name', 150)->nullable();
            $table->string('relation_type', 50)->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamp('created_at')->useCurrent();
        });

        // BRD Konstrain #5: maks 5 relasi aktif/Jamaah/musim -> divalidasi di application layer
    }

    public function down(): void
    {
        Schema::dropIfExists('family_relations');
    }
};
