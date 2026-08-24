<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('jamaah_id')->constrained('jamaah')->restrictOnDelete();
            $table->foreignUuid('package_id')->constrained('packages')->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->enum('payment_type', ['installment', 'full_payment']);
            $table->date('payment_date');
            $table->foreignUuid('recorded_by')->constrained('internal_users')->restrictOnDelete();
            $table->string('notes', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['jamaah_id', 'package_id']); // untuk SUM(amount) cepat
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
