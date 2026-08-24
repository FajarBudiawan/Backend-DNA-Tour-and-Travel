<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->enum('recipient_type', ['jamaah', 'family_relation', 'tour_leader', 'internal_user']); // polymorphic
            $table->uuid('recipient_id');
            $table->string('category', 50); // prayer_time, schedule_update, sos_alert, dll
            $table->text('message');
            $table->timestamp('sent_at')->useCurrent();
            $table->index(['recipient_type', 'recipient_id', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};
