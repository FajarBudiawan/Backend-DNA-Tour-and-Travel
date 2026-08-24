<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // gen_random_uuid() untuk PK UUID
        DB::statement('CREATE EXTENSION IF NOT EXISTS pgcrypto');
        // Tipe geometry untuk GeofenceZone.polygon_coordinates
        DB::statement('CREATE EXTENSION IF NOT EXISTS postgis');
        // EXCLUDE constraint untuk KloterLeaderAssignment (no overlapping date range)
        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');
    }

    public function down(): void
    {
        DB::statement('DROP EXTENSION IF EXISTS btree_gist');
        DB::statement('DROP EXTENSION IF EXISTS postgis');
        DB::statement('DROP EXTENSION IF EXISTS pgcrypto');
    }
};
