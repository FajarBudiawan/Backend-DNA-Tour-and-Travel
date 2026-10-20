<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_device_tokens', function (Blueprint $table) {
            // Primary key UUID
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));

            // Polymorphic relation ke pemilik token (tokenable_type, tokenable_id)
            $table->uuidMorphs('tokenable');

            // FCM device registration token
            $table->string('fcm_token', 500)->unique();

            // Client device identifier (opsional)
            $table->string('device_id')->nullable();

            // Mobile OS platform (Android / iOS)
            $table->enum('platform', ['android', 'ios']);

            // Status aktif token
            $table->boolean('is_active')->default(true);

            // Waktu terakhir token digunakan untuk menerima notifikasi
            $table->timestamp('last_used_at')->nullable();

            $table->timestamps();

            // Composite index untuk query pencarian token aktif berdasarkan pemilik
            $table->index(['tokenable_type', 'tokenable_id', 'is_active'], 'user_device_tokens_owner_active_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_device_tokens');
    }
};
