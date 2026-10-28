<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Jamaah;
use App\Models\Registration;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TrashController extends Controller
{
    /**
     * Tipe-tipe entitas yang diizinkan dalam Riwayat Hapus (Whitelist).
     */
    private const ALLOWED_TYPES = [
        'pendaftar' => [
            'model' => Registration::class,
            'label' => 'Pendaftar',
            'name_column' => 'full_name',
            'code_column' => 'registration_number',
        ],
        'jamaah' => [
            'model' => Jamaah::class,
            'label' => 'Jamaah',
            'name_column' => 'full_name',
            'code_column' => 'login_id',
        ],
    ];

    /**
     * Menampilkan daftar semua record yang sedang berada di Riwayat Hapus (Soft Deleted).
     */
    public function index(Request $request): JsonResponse
    {
        $items = collect();
        $typeFilter = $request->query('type'); // all, pendaftar, jamaah

        $targetTypes = self::ALLOWED_TYPES;
        if ($typeFilter && isset(self::ALLOWED_TYPES[strtolower($typeFilter)])) {
            $targetTypes = [strtolower($typeFilter) => self::ALLOWED_TYPES[strtolower($typeFilter)]];
        }

        foreach ($targetTypes as $key => $config) {
            $modelClass = $config['model'];
            $label = $config['label'];
            $nameCol = $config['name_column'];
            $codeCol = $config['code_column'];

            // Query record yang ter-soft delete
            $query = $modelClass::onlyTrashed();

            if ($request->filled('q')) {
                $q = $request->query('q');
                $query->where(function ($sub) use ($nameCol, $codeCol, $q) {
                    $sub->where($nameCol, 'ilike', "%{$q}%")
                        ->orWhere($codeCol, 'ilike', "%{$q}%");
                });
            }

            $records = $query->latest('deleted_at')->get();

            foreach ($records as $record) {
                $deletedAt = $record->deleted_at ? Carbon::parse($record->deleted_at) : now();
                $retentionDeadline = (clone $deletedAt)->addDays(30);
                
                // Hitung sisa hari kalender
                // endOfDay memastikan perhitungan 30 hari kalender akurat
                $now = Carbon::now();
                $diffInDays = $now->diffInDays($retentionDeadline, false);
                $daysLeft = max(0, (int) ceil($diffInDays));

                $items->push([
                    'id' => "trash-{$key}-{$record->id}",
                    'original_id' => $record->id,
                    'type' => $label,
                    'type_key' => $key,
                    'code' => $record->{$codeCol} ?? '-',
                    'name' => $record->{$nameCol} ?? 'Tidak bernama',
                    'deleted_at' => $deletedAt->toIso8601String(),
                    'retention_deadline' => $retentionDeadline->toIso8601String(),
                    'days_left' => $daysLeft,
                    'is_warning' => ($daysLeft <= 5 && $daysLeft >= 0),
                    'extra_info' => [
                        'phone' => $record->phone ?? null,
                        'nik' => $record->nik ?? null,
                        'status' => $record->status ?? null,
                    ],
                ]);
            }
        }

        // Urutkan berdasarkan waktu penghapusan terbaru
        $sortedItems = $items->sortByDesc('deleted_at')->values();

        return response()->json([
            'message' => 'Data riwayat penghapusan berhasil diambil.',
            'data' => $sortedItems,
            'summary' => [
                'total' => $sortedItems->count(),
                'total_pendaftar' => $sortedItems->where('type_key', 'pendaftar')->count(),
                'total_jamaah' => $sortedItems->where('type_key', 'jamaah')->count(),
                'total_warning' => $sortedItems->where('is_warning', true)->count(),
            ],
        ]);
    }

    /**
     * Memulihkan (restore) record dari Riwayat Hapus ke status aktif.
     */
    public function restore(string $type, string $id): JsonResponse
    {
        $normalizedType = strtolower($type);
        if (!isset(self::ALLOWED_TYPES[$normalizedType])) {
            return response()->json([
                'message' => "Tipe entitas '{$type}' tidak didukung.",
            ], 422);
        }

        $config = self::ALLOWED_TYPES[$normalizedType];
        $modelClass = $config['model'];

        $record = $modelClass::onlyTrashed()->where('id', $id)->first();

        if (!$record) {
            return response()->json([
                'message' => "Data {$config['label']} dengan ID tersebut tidak ditemukan di Riwayat Hapus.",
            ], 404);
        }

        DB::transaction(function () use ($record) {
            $record->restore();
        });

        return response()->json([
            'message' => "Data {$config['label']} berhasil dipulihkan.",
            'data' => [
                'id' => $record->id,
                'name' => $record->{$config['name_column']},
                'type' => $config['label'],
            ],
        ]);
    }
}
