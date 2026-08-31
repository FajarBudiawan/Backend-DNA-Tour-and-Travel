<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drop kolom emergency_contact_name dan emergency_contact_phone,
     * ganti dengan 1 kolom emergency_contact (string gabungan).
     */
    public function up(): void
    {
        Schema::table('jamaah', function (Blueprint $table) {
            $table->dropColumn(['emergency_contact_name', 'emergency_contact_phone']);
        });

        Schema::table('jamaah', function (Blueprint $table) {
            $table->string('emergency_contact', 200)->nullable()->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('jamaah', function (Blueprint $table) {
            $table->dropColumn('emergency_contact');
        });

        Schema::table('jamaah', function (Blueprint $table) {
            $table->string('emergency_contact_name', 150)->nullable()->after('phone');
            $table->string('emergency_contact_phone', 20)->nullable()->after('emergency_contact_name');
        });
    }
};
