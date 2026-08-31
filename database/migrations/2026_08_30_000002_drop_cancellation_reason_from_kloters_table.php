<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kloters', function (Blueprint $table) {
            if (Schema::hasColumn('kloters', 'cancellation_reason')) {
                $table->dropColumn('cancellation_reason');
            }
        });
    }

    public function down(): void
    {
        Schema::table('kloters', function (Blueprint $table) {
            $table->string('cancellation_reason', 255)->nullable();
        });
    }
};
