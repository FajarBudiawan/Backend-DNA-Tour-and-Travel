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
        Schema::table('schedules', function (Blueprint $table) {
            $table->time('end_time')->nullable()->after('time');
            $table->string('status_mode', 20)->default('auto')->after('status_override'); // 'auto' or 'manual'
            $table->string('status_updated_by_type')->nullable()->after('status_mode');
            $table->string('status_updated_by_id')->nullable()->after('status_updated_by_type');
            $table->timestamp('status_updated_at')->nullable()->after('status_updated_by_id');
            $table->string('reminder_status', 20)->default('draft')->after('status_updated_at'); // 'draft' or 'published'
            $table->timestamp('broadcasted_at')->nullable()->after('reminder_status');
            $table->string('broadcasted_by_type')->nullable()->after('broadcasted_at');
            $table->string('broadcasted_by_id')->nullable()->after('broadcasted_by_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropColumn([
                'end_time',
                'status_mode',
                'status_updated_by_type',
                'status_updated_by_id',
                'status_updated_at',
                'reminder_status',
                'broadcasted_at',
                'broadcasted_by_type',
                'broadcasted_by_id',
            ]);
        });
    }
};
