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
        Schema::table('sos_status_histories', function (Blueprint $table) {
            $table->string('reason', 500)->nullable()->after('new_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sos_status_histories', function (Blueprint $table) {
            $table->dropColumn('reason');
        });
    }
};
