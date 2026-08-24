<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('broadcast_recipient_kloters', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('broadcast_id')->constrained('broadcasts')->cascadeOnDelete();
            $table->foreignUuid('kloter_id')->constrained('kloters')->cascadeOnDelete();
            $table->unique(['broadcast_id', 'kloter_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('broadcast_recipient_kloters');
    }
};
