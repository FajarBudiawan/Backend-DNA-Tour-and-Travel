<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_quotas', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('package_id')->unique()->constrained('packages')->cascadeOnDelete();
            $table->integer('max_quota');
            $table->string('computed_status', 30)->nullable(); // dihitung dari KloterMember aktif
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_quotas');
    }
};
