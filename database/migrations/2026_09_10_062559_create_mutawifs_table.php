<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('mutawifs', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('code', 20)->unique();
            $table->string('name', 200);
            $table->string('language', 250);
            $table->string('experience', 250)->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        DB::statement("
            ALTER TABLE mutawifs
            ADD CONSTRAINT mutawifs_status_check
            CHECK (status IN ('active', 'standby'))
        ");

    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mutawifs');
    }
};
