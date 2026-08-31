<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_items', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('name', 150);
            $table->string('category', 100);
            $table->unsignedInteger('quantity')->default(0);
            $table->unsignedInteger('min_stock')->default(0);
            $table->string('unit', 30);
            $table->string('location', 150);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['category', 'location']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_items');
    }
};
