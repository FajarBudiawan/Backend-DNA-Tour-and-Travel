<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Volume tinggi (update lokasi tiap beberapa detik per jamaah/TL) -> partisi per bulan
        // supaya query & maintenance tetap cepat. bigint id, bukan uuid, untuk performa insert.
        DB::statement(<<<SQL
            CREATE TABLE trackings (
                id BIGSERIAL,
                owner_type VARCHAR(20) NOT NULL, -- 'jamaah' | 'tour_leader'
                owner_id UUID NOT NULL,
                kloter_id UUID NOT NULL REFERENCES kloters(id) ON DELETE CASCADE,
                latitude DECIMAL(10,7) NOT NULL,
                longitude DECIMAL(10,7) NOT NULL,
                recorded_at TIMESTAMP NOT NULL DEFAULT now(),
                PRIMARY KEY (id, recorded_at)
            ) PARTITION BY RANGE (recorded_at)
        SQL);

        DB::statement("CREATE INDEX idx_trackings_owner ON trackings (owner_type, owner_id, recorded_at DESC)");
        DB::statement("CREATE INDEX idx_trackings_kloter ON trackings (kloter_id, recorded_at DESC)");

        // Partisi bulan berjalan + bulan depan; job terjadwal perlu menambah partisi baru tiap bulan
        $start = now()->startOfMonth();
        foreach ([0, 1] as $offset) {
            $from = $start->copy()->addMonths($offset)->toDateString();
            $to = $start->copy()->addMonths($offset + 1)->toDateString();
            $suffix = $start->copy()->addMonths($offset)->format('Y_m');
            DB::statement("CREATE TABLE trackings_{$suffix} PARTITION OF trackings FOR VALUES FROM ('{$from}') TO ('{$to}')");
        }
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS trackings CASCADE');
    }
};
