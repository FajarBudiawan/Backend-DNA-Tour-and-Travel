<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->string('room_number', 30)->nullable()->change();
        });

        Schema::table('room_members', function (Blueprint $table) {
            $table->foreignUuid('jamaah_id')->nullable()->constrained('jamaah')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('room_members', function (Blueprint $table) {
            $table->dropForeign(['jamaah_id']);
            $table->dropColumn('jamaah_id');
        });

        Schema::table('rooms', function (Blueprint $table) {
            $table->string('room_number', 30)->nullable(false)->change();
        });
    }
};
