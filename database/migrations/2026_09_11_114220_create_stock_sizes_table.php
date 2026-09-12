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
        Schema::create('stock_sizes', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('stock_id')
                ->constrained('stocks')
                ->cascadeOnDelete();

            $table->string('size', 20);
            $table->integer('quantity')->default(0);

            $table->unique(['stock_id', 'size']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_sizes');
    }
};