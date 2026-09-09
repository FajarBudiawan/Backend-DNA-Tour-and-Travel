<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('day_number')->default(1);
            $table->date('date');
            $table->time('time');
            $table->string('title');
            $table->string('category', 50)->nullable();
            $table->string('location')->nullable();
            $table->text('keterangan')->nullable();
            $table->string('pic')->nullable();
            $table->string('status_override', 20)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};
