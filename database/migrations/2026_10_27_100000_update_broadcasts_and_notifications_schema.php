<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('broadcasts', function (Blueprint $table) {
            $table->string('title', 150)->default('Pengumuman')->after('id');
            $table->string('category', 50)->default('general')->after('message');
            $table->string('target_type', 30)->default('all')->after('priority'); // 'all', 'kloter'
            $table->string('status', 30)->default('published')->after('target_type'); // 'draft', 'published', 'archived'
            $table->string('idempotency_key', 64)->nullable()->unique()->after('status');
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['status', 'created_at']);
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->uuid('broadcast_id')->nullable()->after('sos_incident_id');
            $table->foreign('broadcast_id')
                  ->references('id')
                  ->on('broadcasts')
                  ->nullOnDelete();
                  
            $table->index('broadcast_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropForeign(['broadcast_id']);
            $table->dropColumn('broadcast_id');
        });

        Schema::table('broadcasts', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropTimestamps();
            $table->dropColumn([
                'title',
                'category',
                'target_type',
                'status',
                'idempotency_key',
            ]);
        });
    }
};
