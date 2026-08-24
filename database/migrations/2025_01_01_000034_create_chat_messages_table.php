<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('kloter_id')->constrained('kloters')->cascadeOnDelete();
            $table->enum('conversation_type', ['group', 'private']);
            $table->enum('sender_type', ['jamaah', 'tour_leader', 'family_relation']); // polymorphic
            $table->uuid('sender_id');
            $table->uuid('recipient_id')->nullable(); // diisi kalau conversation_type = private
            $table->text('message');
            $table->timestamp('sent_at')->useCurrent();
            $table->index(['kloter_id', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
