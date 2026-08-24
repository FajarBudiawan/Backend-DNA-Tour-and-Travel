<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_checklists', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('jamaah_id')->constrained('jamaah')->cascadeOnDelete();
            $table->string('document_type', 50); // KTP, Paspor, KK, Buku Nikah, Kartu Vaksin
            $table->boolean('is_collected')->default(false);
            $table->timestamp('collected_at')->nullable();
            $table->foreignUuid('checked_by')->nullable()->constrained('internal_users')->nullOnDelete();
            $table->string('notes', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_checklists');
    }
};
