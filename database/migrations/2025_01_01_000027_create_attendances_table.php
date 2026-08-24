<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('attendance_session_id')->constrained('attendance_sessions')->cascadeOnDelete();
            $table->foreignUuid('jamaah_id')->constrained('jamaah')->cascadeOnDelete();
            $table->enum('status', ['present', 'absent', 'late'])->default('present');
            $table->foreignUuid('marked_by')->nullable()->constrained('tour_leaders')->nullOnDelete();
            $table->timestamp('detected_at')->useCurrent();
            $table->unique(['attendance_session_id', 'jamaah_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
