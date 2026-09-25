<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sos_status_histories', function (Blueprint $table) {
            $table->foreignUuid('tour_leader_id')
                ->nullable()
                ->after('changed_by')
                ->constrained('tour_leaders')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sos_status_histories', function (Blueprint $table) {
            $table->dropForeign(['tour_leader_id']);
            $table->dropColumn('tour_leader_id');
        });
    }
};