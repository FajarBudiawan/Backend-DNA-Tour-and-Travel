<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kloter_status_histories', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('kloter_id')->constrained('kloters')->cascadeOnDelete();
            $table->string('old_status', 20)->nullable();
            $table->string('new_status', 20);
            $table->foreignUuid('changed_by')->nullable()->constrained('internal_users')->nullOnDelete();
            $table->timestamp('changed_at')->useCurrent();
            $table->string('reason', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kloter_status_histories');
    }
};
