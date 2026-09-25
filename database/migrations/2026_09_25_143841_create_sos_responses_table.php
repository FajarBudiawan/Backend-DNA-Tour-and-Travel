<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sos_responses', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));

            $table->foreignUuid('sos_incident_id')
                ->constrained('sos_incidents')
                ->cascadeOnDelete();

            $table->foreignUuid('jamaah_id')
                ->nullable()
                ->constrained('jamaah')
                ->nullOnDelete();

            $table->foreignUuid('tour_leader_id')
                ->nullable()
                ->constrained('tour_leaders')
                ->nullOnDelete();

            $table->foreignUuid('internal_user_id')
                ->nullable()
                ->constrained('internal_users')
                ->nullOnDelete();

            $table->text('message');

            $table->timestamp('created_at')->useCurrent();

            $table->index([
                'sos_incident_id',
                'created_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sos_responses');
    }
};