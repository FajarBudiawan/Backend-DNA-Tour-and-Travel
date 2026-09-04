<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('package_itineraries', function (Blueprint $table) {
            $table->string('title')->after('day_number');
            $table->time('activity_time')->nullable()->after('title');
            $table->string('location')->nullable()->after('activity_time');
            $table->string('category')->nullable()->after('location');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::table('package_itineraries', function (Blueprint $table) {
            $table->dropColumn(['title', 'activity_time', 'location', 'category', 'created_at', 'updated_at']);
        });
    }
};
