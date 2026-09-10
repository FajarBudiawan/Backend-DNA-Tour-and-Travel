<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tour_leaders', function (Blueprint $table) {
            $table->string('experience')->nullable()->after('phone');
            $table->string('performance')->nullable()->after('experience');
        });
    }

    public function down(): void
    {
        Schema::table('tour_leaders', function (Blueprint $table) {
            $table->dropColumn(['experience', 'performance']);
        });
    }
};