<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('broadcasts', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->enum('sender_type', ['internal_user', 'tour_leader']); // polymorphic, tanpa FK keras
            $table->uuid('sender_id');
            $table->text('message');
            $table->enum('priority', ['normal', 'urgent'])->default('normal');
            $table->timestamp('sent_at')->useCurrent();
            $table->index(['sender_type', 'sender_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('broadcasts');
    }
};
