<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drop kolom birth_place, address, email, passport_expiry_date
     * dari tabel jamaah karena tidak dipakai di frontend saat ini.
     */
    public function up(): void
    {
        Schema::table('jamaah', function (Blueprint $table) {
            $table->dropColumn([
                'birth_place',
                'address',
                'email',
                'passport_expiry_date',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('jamaah', function (Blueprint $table) {
            $table->string('birth_place', 100)->nullable()->after('full_name');
            $table->text('address')->nullable()->after('phone');
            $table->string('email', 150)->nullable()->after('address');
            $table->date('passport_expiry_date')->nullable()->after('emergency_contact_phone');
        });
    }
};
