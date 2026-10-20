
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_device_deliveries', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('notification_id')
                ->constrained('notifications')
                ->cascadeOnDelete();

            $table->foreignUuid('device_token_id')
                ->constrained('user_device_tokens')
                ->cascadeOnDelete();

            $table->string('status')->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(
                ['notification_id', 'device_token_id'],
                'notification_device_deliveries_unique'
            );

            $table->index(['status', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_device_deliveries');
    }
};
