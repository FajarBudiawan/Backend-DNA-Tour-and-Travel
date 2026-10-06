<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tambah kolom ke sos_incidents SAJA
        Schema::table('sos_incidents', function (Blueprint $table) {
            $table->timestamp('acknowledged_at')->nullable()->after('triggered_at');
            $table->timestamp('resolved_at')->nullable()->after('acknowledged_at');
        });
    }

    public function down(): void
    {
        Schema::table('sos_incidents', function (Blueprint $table) {
            $table->dropColumn(['acknowledged_at', 'resolved_at']);
        });
    }
};