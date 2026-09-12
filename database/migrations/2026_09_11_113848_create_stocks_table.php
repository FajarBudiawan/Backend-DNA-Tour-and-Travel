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
        // Sequence untuk menghasilkan code stok: STK-001, STK-002, dst.
        DB::statement("CREATE SEQUENCE stock_code_seq START 1");

        Schema::create('stocks', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('code', 20)->unique();
            $table->string('name', 200);
            $table->string('category', 50);
            $table->integer('quantity')->default(0);
            $table->integer('min_stock')->default(0);
            $table->string('unit', 30);
            $table->string('location')->nullable();
            $table->text('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stocks');

        DB::statement("DROP SEQUENCE IF EXISTS stock_code_seq");
    }
};