<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSosIncidentRequest;
use App\Http\Requests\UpdateSosIncidentStatusRequest;
use App\Models\SosIncident;
use App\Models\SosStatusHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @group Modul Darurat / Emergency / SOS
 *
 * API untuk mengelola laporan kejadian darurat (SOS Incident) dari Jamaah dan penanganannya oleh Admin/Tour Leader.
 */
class SosIncidentController extends Controller
{
    /**
     * Menampilkan daftar SOS Incident
     *
     * Menampilkan daftar laporan SOS yang dapat diakses oleh user sesuai ability token autentikasi.
     *
     * **Scope Akses Berdasarkan Role:**
     * * **Admin (`admin`):** Memiliki akses penuh untuk melihat seluruh laporan SOS dari semua kloter.
     * * **Tour Leader (`tour_leader`):** Hanya dapat melihat laporan SOS dari kloter yang ditugaskan kepadanya.
     * * **Jamaah (`jamaah`):** Hanya dapat melihat laporan SOS miliknya sendiri.
     *
     * Data diurutkan berdasarkan `triggered_at` terbaru dan menyertakan eager-load relasi `jamaah` (id, full_name, phone), `kloter` (id, name), serta jumlah tanggapan (`responses_count`).
     *
     * @authenticated
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = SosIncident::with([
            'jamaah:id,full_name,phone',
            'kloter:id,name',
        ])
            ->withCount('responses')
            ->latest('triggered_at');

        if ($user->currentAccessToken()->can('admin')) {
            // Admin dapat melihat semua SOS.
        } elseif ($user->currentAccessToken()->can('tour_leader')) {
            $kloterIds = $user->kloters()
                ->pluck('kloters.id');

            $query->whereIn('kloter_id', $kloterIds);
        } elseif ($user->currentAccessToken()->can('jamaah')) {
            $query->where('jamaah_id', $user->id);
        } else {
            return response()->json([
                'message' => 'Unauthorized.',
            ], 403);
        }

        return response()->json([
            'data' => $query->get(),
        ]);
    }

    /**
     * Menampilkan detail satu SOS Incident
     *
     * Menampilkan rincian informasi satu laporan kejadian SOS Incident beserta relasi Jamaah, Kloter, Responses, dan Status Histories.
     *
     * **Scope Akses Berdasarkan Role:**
     * * **Admin (`admin`):** Dapat melihat detail laporan SOS manapun.
     * * **Tour Leader (`tour_leader`):** Hanya dapat melihat detail SOS jika kloter incident sesuai dengan kloter assignment-nya.
     * * **Jamaah (`jamaah`):** Hanya dapat melihat detail SOS miliknya sendiri.
     *
     * @authenticated
     * @urlParam sosIncident string required UUID laporan SOS Incident. Example: 9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d
     */
    public function show(
        Request $request,
        SosIncident $sosIncident
    ): JsonResponse {
        if (! $this->canAccessIncident($request, $sosIncident)) {
            return response()->json([
                'message' => 'Forbidden.',
            ], 403);
        }

        $sosIncident->load([
            'jamaah:id,full_name,phone',
            'kloter:id,name',
            'responses.jamaah:id,full_name',
            'responses.tourLeader:id,full_name',
            'responses.internalUser:id,full_name',
            'statusHistories.internalUser:id,full_name',
            'statusHistories.tourLeader:id,full_name',
        ]);

        return response()->json([
            'data' => $sosIncident,
        ]);
    }

    /**
     * Membuat laporan SOS baru
     *
     * Membuat laporan kejadian darurat (SOS Incident) baru.
     *
     * **Aturan Autentikasi & Authorization:**
     * * **Jamaah (`jamaah`):** Dapat membuat SOS untuk dirinya sendiri. Field `jamaah_id` **tidak perlu dikirim** oleh client karena diisi otomatis dari token Jamaah.
     * * **Admin (`admin`):** Dapat membuat SOS atas nama Jamaah dan **wajib menentukan** `jamaah_id` di request body. Jika `jamaah_id` kosong/tidak disertakan, mengembalikan response error `422 Unprocessable Entity`.
     * * **Tour Leader (`tour_leader`):** **Tidak diperbolehkan** membuat SOS dan menghasilkan response error `403 Forbidden` (`Tour Leader tidak dapat membuat SOS.`).
     *
     * **Aturan Penentuan Field Otomatis oleh Backend:**
     * * `kloter_id`: Diambil otomatis dari `jamaah.kloter_id`.
     * * `status`: Bernilai awal `'triggered'`.
     * * `triggered_at`: Diisi otomatis dengan timestamp `now()`.
     * * Record awal pada `sos_status_histories` dibuat otomatis (`old_status: null`, `new_status: 'triggered'`).
     *
     * @authenticated
     * @bodyParam jamaah_id string UUID Jamaah yang mengalami darurat. Wajib dikirim oleh Admin, diabaikan/diisi otomatis jika login sebagai Jamaah. Example: 9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d
     * @bodyParam type string required Jenis kejadian darurat (misal: "Darurat Medis", "Tersesat", "Kecelakaan"). Example: Darurat Medis
     * @bodyParam description string Rincian deskripsi kondisi darurat yang dialami. Example: Jamaah mengalami pusing dan sesak napas.
     * @bodyParam location_name string Nama lokasi atau deskripsi landmark tempat kejadian. Example: Pelataran Tawaf Gate 1
     * @bodyParam latitude number required Koordinat latitude lokasi kejadian (-90 s/d 90). Example: 21.4225000
     * @bodyParam longitude number required Koordinat longitude lokasi kejadian (-180 s/d 180). Example: 39.8262000
     */
    public function store(
        StoreSosIncidentRequest $request
    ): JsonResponse {
        $user = $request->user();
        $token = $user->currentAccessToken();

        /*
         * Pastikan token memiliki ability yang memang
         * diperbolehkan untuk mengakses fitur SOS.
         */
        if (
            ! $token->can('admin') &&
            ! $token->can('jamaah') &&
            ! $token->can('tour_leader')
        ) {
            return response()->json([
                'message' => 'Unauthorized.',
            ], 403);
        }

        $data = $request->validated();

        /*
         * Jamaah tidak boleh menentukan jamaah_id sendiri.
         * ID Jamaah diambil dari user yang sedang login.
         */
        if ($token->can('jamaah')) {
            $data['jamaah_id'] = $user->id;
        }

        /*
         * Admin wajib menentukan jamaah_id.
         */
        if (
            $token->can('admin')
            && empty($data['jamaah_id'])
        ) {
            return response()->json([
                'message' => 'jamaah_id is required for admin.',
            ], 422);
        }

        /*
         * Tour Leader tidak boleh membuat SOS.
         */
        if ($token->can('tour_leader')) {
            return response()->json([
                'message' => 'Tour Leader tidak dapat membuat SOS.',
            ], 403);
        }

        /*
         * Ambil kloter dari Jamaah agar client tidak dapat
         * mengirim kloter_id yang berbeda.
         */
        $jamaah = \App\Models\Jamaah::find($data['jamaah_id']);

        if (! $jamaah) {
            return response()->json([
                'message' => 'Jamaah tidak ditemukan.',
            ], 422);
        }

        $data['kloter_id'] = $jamaah->kloter_id;
        $data['status'] = 'triggered';
        $data['triggered_at'] = now();

        $incident = DB::transaction(function () use ($data) {
            $incident = SosIncident::create($data);

            SosStatusHistory::create([
                'sos_incident_id' => $incident->id,
                'old_status' => null,
                'new_status' => 'triggered',
                'changed_at' => now(),
            ]);

            return $incident;
        });

        $incident->load([
            'jamaah:id,full_name,phone',
            'kloter:id,name',
        ]);

        return response()->json([
            'message' => 'SOS berhasil dibuat.',
            'data' => $incident,
        ], 201);
    }

    /**
     * Mengubah status SOS Incident
     *
     * Memperbarui status penanganan laporan SOS Incident dan mencatat riwayat perubahannya ke `sos_status_histories`.
     *
     * **Aturan Hak Akses:**
     * * **Jamaah (`jamaah`):** **Tidak dapat** mengubah status SOS. Menghasilkan response `403 Forbidden` (`Jamaah tidak dapat mengubah status SOS.`).
     * * **Admin (`admin`):** Dapat mengubah status SOS mana saja jika memiliki akses. Aktor pencatat pada `sos_status_histories` disimpan di kolom `changed_by`.
     * * **Tour Leader (`tour_leader`):** Dapat mengubah status SOS jika memiliki akses terhadap incident (kloter assignment). Aktor pencatat pada `sos_status_histories` disimpan di kolom `tour_leader_id`.
     *
     * **Nilai Status yang Diperbolehkan:**
     * * `triggered`: Laporan SOS baru terpicu.
     * * `acknowledged`: Laporan telah diterima/dikonfirmasi oleh Petugas/TL.
     * * `in_action`: Petugas/TL sedang dalam penanganan di lapangan.
     * * `resolved`: Kejadian darurat telah selesai ditangani.
     * * `false_alarm`: Laporan palsu / tidak sengaja tertekan.
     *
     * @authenticated
     * @urlParam sosIncident string required UUID laporan SOS Incident. Example: 9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d
     * @bodyParam status string required Status baru yang valid (`triggered`, `acknowledged`, `in_action`, `resolved`, `false_alarm`). Example: acknowledged
     */
    public function updateStatus(
        UpdateSosIncidentStatusRequest $request,
        SosIncident $sosIncident
    ): JsonResponse {
        if (! $this->canAccessIncident($request, $sosIncident)) {
            return response()->json([
                'message' => 'Forbidden.',
            ], 403);
        }

        $user = $request->user();

        if (
            ! $user->currentAccessToken()->can('admin') &&
            ! $user->currentAccessToken()->can('tour_leader')
        ) {
            return response()->json([
                'message' => 'Jamaah tidak dapat mengubah status SOS.',
            ], 403);
        }

        $newStatus = $request->validated('status');
        $oldStatus = $sosIncident->status;

        if ($oldStatus === $newStatus) {
            return response()->json([
                'message' => 'Status SOS sudah berada pada status tersebut.',
                'data' => $sosIncident,
            ]);
        }

        /*
         * Saat ini kita izinkan perubahan status selama user
         * memiliki akses terhadap incident.
         *
         * Validasi urutan status dapat diperketat setelah
         * seluruh flow Emergency selesai diintegrasikan.
         */
        DB::transaction(function () use (
            $sosIncident,
            $oldStatus,
            $newStatus,
            $user
        ) {
            $sosIncident->update([
                'status' => $newStatus,
            ]);

            $historyData = [
                'sos_incident_id' => $sosIncident->id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'changed_at' => now(),
            ];

            if ($user->currentAccessToken()->can('tour_leader')) {
                $historyData['tour_leader_id'] = $user->id;
            }

            if ($user->currentAccessToken()->can('admin')) {
                $historyData['changed_by'] = $user->id;
            }

            SosStatusHistory::create($historyData);
        });

        return response()->json([
            'message' => 'Status SOS berhasil diperbarui.',
            'data' => $sosIncident->fresh(),
        ]);
    }

    /**
     * Memeriksa apakah user memiliki akses terhadap incident.
     */
    private function canAccessIncident(
        Request $request,
        SosIncident $sosIncident
    ): bool {
        $user = $request->user();

        if ($user->currentAccessToken()->can('admin')) {
            return true;
        }

        if ($user->currentAccessToken()->can('jamaah')) {
            return $sosIncident->jamaah_id === $user->id;
        }

        if ($user->currentAccessToken()->can('tour_leader')) {
            return $user->kloters()
                ->where('kloters.id', $sosIncident->kloter_id)
                ->exists();
        }

        return false;
    }
}