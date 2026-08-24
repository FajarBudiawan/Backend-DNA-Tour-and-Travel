<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sos_incidents', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('jamaah_id')->constrained('jamaah')->restrictOnDelete();
            $table->foreignUuid('kloter_id')->constrained('kloters')->restrictOnDelete();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->enum('status', ['triggered', 'acknowledged', 'resolved', 'false_alarm'])->default('triggered');
            $table->timestamp('triggered_at')->useCurrent();
            $table->index(['status', 'triggered_at']); // untuk dashboard live monitoring
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sos_incidents');
    }
};
