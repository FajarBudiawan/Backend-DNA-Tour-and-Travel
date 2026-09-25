<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sos_incidents', function (Blueprint $table) {
            $table->string('type', 50)->nullable()->after('kloter_id');
            $table->text('description')->nullable()->after('type');
            $table->string('location_name', 255)->nullable()->after('longitude');
        });
    }

    public function down(): void
    {
        Schema::table('sos_incidents', function (Blueprint $table) {
            $table->dropColumn([
                'type',
                'description',
                'location_name',
            ]);
        });
    }
};