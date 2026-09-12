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
        Schema::create('stock_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('stock_id')
                ->constrained('stocks')
                ->restrictOnDelete();

            $table->string('type', 20);
            $table->integer('quantity');
            $table->string('size', 20)->nullable();

            $table->string('reference_type', 50)->nullable();
            $table->uuid('reference_id')->nullable();

            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_transactions');
    }
};