<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kloter_schedules', function (Blueprint $table) {
            $table->integer('day_number')->nullable()->after('kloter_id');
            $table->string('category')->nullable()->after('location');
            $table->string('pic')->nullable()->after('category');
            $table->string('status')->default('belum_mulai')->after('pic');
            $table->foreignUuid('source_itinerary_id')->nullable()->after('is_published')->constrained('package_itineraries')->nullOnDelete();
            $table->boolean('is_customized')->default(false)->after('source_itinerary_id');
        });
    }

    public function down(): void
    {
        Schema::table('kloter_schedules', function (Blueprint $table) {
            $table->dropForeign(['source_itinerary_id']);
            $table->dropColumn(['day_number', 'category', 'pic', 'status', 'source_itinerary_id', 'is_customized']);
        });
    }
};
