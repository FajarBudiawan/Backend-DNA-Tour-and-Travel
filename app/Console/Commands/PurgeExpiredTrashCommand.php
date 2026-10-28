<?php

namespace App\Console\Commands;

use App\Models\Jamaah;
use App\Models\Registration;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PurgeExpiredTrashCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'trash:purge-expired {--dry-run : Menampilkan daftar record kedaluwarsa tanpa menghapus data}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Hapus permanen record yang telah melewati batas masa retensi 30 hari kalender';

    /**
     * Whitelist model yang diproses untuk retensi 30 hari.
     */
    private const RETENTION_MODELS = [
        'Pendaftar' => [
            'model' => Registration::class,
            'name_column' => 'full_name',
            'code_column' => 'registration_number',
        ],
        'Jamaah' => [
            'model' => Jamaah::class,
            'name_column' => 'full_name',
            'code_column' => 'login_id',
        ],
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        // Threshold: 30 hari kalender penuh
        $cutoffDate = Carbon::now()->subDays(30);

        $this->info("=== RETENSI RIWAYAT HAPUS 30 HARI ===");
        $this->info("Waktu Pemeriksaan: " . Carbon::now()->toDateTimeString());
        $this->info("Batas Kedaluwarsa (Cutoff): " . $cutoffDate->toDateTimeString());
        if ($isDryRun) {
            $this->warn("[DRY-RUN MODE AKTIF] Tidak ada data yang akan dihapus dari database.");
        }

        $totalChecked = 0;
        $totalPurged = 0;
        $totalSkipped = 0;
        $totalFailed = 0;

        foreach (self::RETENTION_MODELS as $typeLabel => $config) {
            $modelClass = $config['model'];
            $nameCol = $config['name_column'];
            $codeCol = $config['code_column'];

            $this->line("\nMemeriksa entitas: {$typeLabel}...");

            // Cari record yang berstatus soft-deleted dan deleted_at <= cutoffDate
            $expiredRecords = $modelClass::onlyTrashed()
                ->where('deleted_at', '<=', $cutoffDate)
                ->orderBy('deleted_at', 'asc')
                ->get();

            $count = $expiredRecords->count();
            $totalChecked += $count;
            $this->info("Ditemukan {$count} record {$typeLabel} yang memenuhi syarat purge (>= 30 hari).");

            if ($count === 0) {
                continue;
            }

            foreach ($expiredRecords as $record) {
                $recordId = $record->id;
                $recordCode = $record->{$codeCol} ?? '-';
                $recordName = $record->{$nameCol} ?? 'Tidak bernama';
                $deletedAt = $record->deleted_at;

                // ==============================================================
                // GUARD KEAMANAN RELASI & HISTORI BISNIS SEBELUM PURGE
                // ==============================================================

                // 1. Guard Khusus Registration (Pendaftar)
                if ($record instanceof Registration) {
                    $hasPayments = $record->payments()->count() > 0;
                    if ($hasPayments) {
                        // Data keuangan/pembayaran wajib dipertahankan untuk audit.
                        $this->warn("  [DILEWATI] Pendaftar {$recordCode} ({$recordName}) memiliki data pembayaran riil. Dipertahankan demi audit keuangan.");
                        Log::channel('daily')->info("Purge skipped for Registration {$recordId} ({$recordCode}): Has real financial payments.");
                        $totalSkipped++;
                        continue;
                    }
                }

                // 2. Guard Khusus Jamaah (Audit Keselamatan, Finansial, dan Operasional)
                if ($record instanceof Jamaah) {
                    $reasons = [];

                    // a. Cek Pembayaran Jamaah (Tabel 'payments', FK: restrictOnDelete)
                    if (DB::table('payments')->where('jamaah_id', $recordId)->exists()) {
                        $reasons[] = 'memiliki transaksi keuangan (tabel payments)';
                    }

                    // b. Cek Insiden SOS (Tabel 'sos_incidents', FK: restrictOnDelete)
                    if (DB::table('sos_incidents')->where('jamaah_id', $recordId)->exists()) {
                        $reasons[] = 'memiliki rekaman insiden darurat/SOS (tabel sos_incidents)';
                    }

                    // c. Cek Keanggotaan Kloter (Tabel 'kloter_members', FK: restrictOnDelete)
                    // Menolak purge baik status active, transferred, maupun cancelled demi manifest historis
                    if (DB::table('kloter_members')->where('jamaah_id', $recordId)->exists()) {
                        $reasons[] = 'terdaftar dalam riwayat manifest kloter (tabel kloter_members)';
                    }

                    // d. Cek Presensi (Tabel 'attendances', FK: cascadeOnDelete)
                    // Mencegah cascade delete yang melenyapkan bukti kehadiran ibadah
                    if (DB::table('attendances')->where('jamaah_id', $recordId)->exists()) {
                        $reasons[] = 'memiliki rekam jejak presensi ibadah (tabel attendances)';
                    }

                    // e. Cek Peringatan Geofence (Tabel 'early_warning_events', FK: cascadeOnDelete)
                    // Mencegah cascade delete yang melenyapkan log keselamatan
                    if (DB::table('early_warning_events')->where('jamaah_id', $recordId)->exists()) {
                        $reasons[] = 'memiliki log peringatan dini/geofence (tabel early_warning_events)';
                    }

                    if (!empty($reasons)) {
                        $reasonList = implode(', ', $reasons);
                        $this->warn("  [DILEWATI] Jamaah {$recordCode} ({$recordName}) {$reasonList}. Dipertahankan demi integritas audit.");
                        Log::channel('daily')->info("Purge skipped for Jamaah {$recordId} ({$recordCode}): {$reasonList}");
                        $totalSkipped++;
                        continue;
                    }
                }

                // Jika mode dry-run, catat sebagai kandidat tanpa eksekusi database
                if ($isDryRun) {
                    $this->line("  [DRY-RUN KANDIDAT] {$typeLabel} | ID: {$recordId} | Kode: {$recordCode} | Nama: {$recordName} | Dihapus: {$deletedAt}");
                    $totalPurged++;
                    continue;
                }

                // Eksekusi pembersihan permanen dalam transaksi database per-record
                try {
                    DB::transaction(function () use ($record) {
                        // Force delete record (soft-delete permanent purge)
                        $record->forceDelete();
                    });

                    $this->info("  [BERHASIL PURGE] {$typeLabel} {$recordCode} ({$recordName}) dihapus permanen.");
                    Log::channel('daily')->info("Purged expired {$typeLabel}: {$recordId} ({$recordCode})");
                    $totalPurged++;
                } catch (\Throwable $e) {
                    $this->error("  [GAGAL] Gagal mem-purge {$typeLabel} {$recordCode} (ID: {$recordId}): {$e->getMessage()}");
                    Log::channel('daily')->error("Failed to purge {$typeLabel} {$recordId} ({$recordCode}): " . $e->getMessage());
                    $totalFailed++;
                }
            }
        }

        $this->line("\n=== RINGKASAN HASIL PURGE ===");
        $this->table(
            ['Metrik', 'Jumlah'],
            [
                ['Total Diperiksa', $totalChecked],
                [$isDryRun ? 'Kandidat Purge' : 'Berhasil Dipurge (Permanen)', $totalPurged],
                ['Dilewati (Audit Keuangan/Keamanan/Histori)', $totalSkipped],
                ['Gagal (Error)', $totalFailed],
            ]
        );

        return 0;
    }
}