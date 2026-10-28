<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('
            ALTER TABLE broadcasts
            DROP CONSTRAINT IF EXISTS broadcasts_sender_type_check
        ');

        DB::statement("
            ALTER TABLE broadcasts
            ADD CONSTRAINT broadcasts_sender_type_check
            CHECK (
                sender_type IN (
                    'internal_user',
                    'tour_leader',
                    'App\\Models\\InternalUser',
                    'App\\Models\\TourLeader'
                )
            )
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('
            ALTER TABLE broadcasts
            DROP CONSTRAINT IF EXISTS broadcasts_sender_type_check
        ');

        DB::statement("
            ALTER TABLE broadcasts
            ADD CONSTRAINT broadcasts_sender_type_check
            CHECK (
                sender_type IN (
                    'internal_user',
                    'tour_leader'
                )
            )
        ");
    }
};
