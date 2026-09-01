<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_members', function (Blueprint $table) {
            // Make registration_id nullable – foreign key already exists, no need to re-declare it
            $table->uuid('registration_id')->nullable()->change();

            // New manual-occupant columns – all nullable at DB level for back-compat with existing rows.
            // Application-level validation (RoomController@addMember) still enforces occupant_name as required.
            $table->string('title', 10)->nullable()->comment('MR, MRS, MISS, MSTR');
            $table->string('occupant_name')->nullable();
            $table->integer('age')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('room_members', function (Blueprint $table) {
            $table->uuid('registration_id')->nullable(false)->change();
            $table->dropColumn(['title', 'occupant_name', 'age']);
        });
    }
};
