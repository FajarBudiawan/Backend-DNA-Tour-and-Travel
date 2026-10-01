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
        Schema::create('notifications', function (Blueprint $table) {
            // Primary key
            $table->uuid('id')->primary();
            
            // Foreign key ke SOS incident (nullable, bisa null untuk notification non-SOS)
            $table->uuid('sos_incident_id')->nullable();
            $table->foreign('sos_incident_id')
                  ->references('id')
                  ->on('sos_incidents')
                  ->cascadeOnDelete();
            
            // POLYMORPHIC RELATIONSHIP
            // Recipient bisa: InternalUser, TourLeader, atau Jamaah
            $table->string('recipient_type');     // Full class name: 'App\Models\InternalUser'
            $table->string('recipient_id');       // UUID siapa (JANGAN FK constraint!)
            
            // Notification content
            $table->string('type');               // 'sos_created', 'sos_status_updated', etc
            $table->string('title');              // "Laporan Darurat Baru"
            $table->text('message');              // Full message text
            
            // Status tracking
            $table->timestamp('read_at')->nullable();      // Kapan dibaca (null = belum dibaca)
            $table->timestamp('sent_at')->nullable();      // Kapan terkirim ke Firebase
            $table->enum('delivery_status', ['pending', 'sent', 'failed'])
                  ->default('pending');
            
            // Timestamps
            $table->timestamps();
            
            // INDEXES (penting untuk query performance)
            // Index untuk query: "Mana notif yang belum dibaca milik user X?"
            $table->index(['recipient_type', 'recipient_id', 'read_at']);
            
            // Index untuk query: "Mana notif untuk incident #123?"
            $table->index('sos_incident_id');
            
            // Index untuk query: "Mana notif terbaru milik user X?"
            $table->index(['created_at', 'recipient_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};