<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRegistrationRequest;
use App\Http\Requests\UpdateRegistrationRequest;
use App\Models\Jamaah;
use App\Models\Registration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RegistrationController extends Controller
{
    /**
     * Menampilkan semua data pendaftaran (dengan pencarian & filter).
     */
    public function index(Request $request): JsonResponse
    {
        $query = Registration::with([
            'package',
            'kloter',
            'payments',
            'equipments',
            'createdBy',
        ]);

        // Filter pencarian berdasarkan nama, NIK, phone, atau nomor pendaftaran
        if ($request->filled('q')) {
            $search = $request->q;
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'ilike', "%{$search}%")
                  ->orWhere('nik', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('registration_number', 'ilike', "%{$search}%");
            });
        }

        // Filter berdasarkan status (unpaid, dp, paid, cancelled, converted)
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter berdasarkan paket
        if ($request->filled('package_id')) {
            $query->where('package_id', $request->package_id);
        }

        // Filter berdasarkan kloter
        if ($request->filled('kloter_id')) {
            $query->where('kloter_id', $request->kloter_id);
        }

        $registrations = $query->latest()->get();

        // Sinkronkan status keuangan secara otomatis untuk setiap data
        $registrations->each(function ($registration) {
            $registration->updateFinancialStatus();
        });

        return response()->json([
            'message' => 'Data pendaftaran berhasil diambil.',
            'data' => $registrations,
        ]);
    }

    /**
     * Menyimpan pendaftaran baru.
     */
    public function store(StoreRegistrationRequest $request): JsonResponse
    {
        $registration = DB::transaction(function () use ($request) {
            // Auto generate nomor pendaftaran jika tidak dikirim
            $regNumber = $request->registration_number;
            if (!$regNumber) {
                $regNumber = 'REG-' . date('Ymd') . '-' . strtoupper(Str::random(5));
            }

            // Normalisasi status jika dikirim manual saat pendaftaran (misal: 'dp' -> 'dp_paid', 'paid' -> 'fully_paid')
            $status = $request->status ?? 'unpaid';
            if ($status === 'dp') {
                $status = 'dp_paid';
            } elseif ($status === 'paid') {
                $status = 'fully_paid';
            }

            $registration = Registration::create([
                'registration_number' => $regNumber,
                'full_name' => $request->full_name,
                'passport_number' => $request->passport_number,
                'nik' => $request->nik,
                'phone' => $request->phone,
                'birth_date' => $request->birth_date,
                'gender' => $request->gender,

                'registration_date' => $request->registration_date,
                'departure_date' => $request->departure_date,

                'package_id' => $request->package_id,
                'kloter_id' => $request->kloter_id,

                'meningitis_vaccine_status' => $request->meningitis_vaccine_status,
                'photo_status' => $request->photo_status,

                'total_package_cost' => $request->total_package_cost ?? 30000000,
                'status' => $status,

                'created_by' => auth()->id(),
            ]);

            // Jika ada pembayaran awal (initial_payment) saat pendaftaran dibuat
            if ($request->has('initial_payment') && is_array($request->initial_payment)) {
                $payData = $request->initial_payment;
                $registration->payments()->create([
                    'amount' => $payData['amount'],
                    'payment_type' => $payData['payment_type'] ?? 'down_payment',
                    'payment_method' => $payData['payment_method'] ?? 'bca_transfer',
                    'payment_date' => $payData['payment_date'] ?? now(),
                    'recorded_by' => auth()->id(),
                    'notes' => $payData['notes'] ?? 'Pembayaran awal saat pendaftaran',
                ]);
                $registration->updateFinancialStatus();
            }

            // Simpan perlengkapan (atau auto-generate perlengkapan standar berdasarkan gender jika tidak dikirim)
            $equipments = $request->equipments;
            if (empty($equipments) || !is_array($equipments)) {
                if ($request->gender === 'L') {
                    $equipments = [
                        ['equipment_name' => 'Koper Besar', 'is_received' => false, 'size' => null],
                        ['equipment_name' => 'Koper Kabin', 'is_received' => false, 'size' => null],
                        ['equipment_name' => 'Seragam Batik', 'is_received' => false, 'size' => null],
                        ['equipment_name' => 'Buku Panduan', 'is_received' => false, 'size' => null],
                        ['equipment_name' => 'Kain Ihram', 'is_received' => false, 'size' => null],
                        ['equipment_name' => 'Tas Selempang', 'is_received' => false, 'size' => null],
                        ['equipment_name' => 'Tas Sandal', 'is_received' => false, 'size' => null],
                        ['equipment_name' => 'Syall', 'is_received' => false, 'size' => null],
                        ['equipment_name' => 'Sabuk', 'is_received' => false, 'size' => null],
                    ];
                } else {
                    $equipments = [
                        ['equipment_name' => 'Koper Besar', 'is_received' => false, 'size' => null],
                        ['equipment_name' => 'Koper Kabin', 'is_received' => false, 'size' => null],
                        ['equipment_name' => 'Seragam Batik', 'is_received' => false, 'size' => null],
                        ['equipment_name' => 'Buku Panduan', 'is_received' => false, 'size' => null],
                        ['equipment_name' => 'Kerudung Merah', 'is_received' => false, 'size' => null],
                        ['equipment_name' => 'Kerudung Putih', 'is_received' => false, 'size' => null],
                        ['equipment_name' => 'Tas Selempang', 'is_received' => false, 'size' => null],
                        ['equipment_name' => 'Tas Sandal', 'is_received' => false, 'size' => null],
                        ['equipment_name' => 'Syall', 'is_received' => false, 'size' => null],
                    ];
                }
            }

            foreach ($equipments as $equipment) {
                $isReceived = $equipment['is_received'] ?? false;
                $registration->equipments()->create([
                    'equipment_name' => $equipment['equipment_name'],
                    'size' => $equipment['size'] ?? null,
                    'is_received' => $isReceived,
                    'received_at' => $isReceived ? now() : null,
                ]);
            }

            return $registration;
        });

        return response()->json([
            'message' => 'Pendaftaran berhasil dibuat.',
            'data' => $registration->load([
                'package',
                'kloter',
                'payments',
                'equipments',
                'createdBy',
            ]),
        ], 201);
    }

    /**
     * Menampilkan detail satu pendaftaran.
     */
    public function show(Registration $registration): JsonResponse
    {
        // Otomatis sinkronkan status keuangan untuk data yang dipanggil
        $registration->updateFinancialStatus();

        $registration->load([
            'package',
            'kloter',
            'payments.recordedBy',
            'equipments',
            'createdBy',
        ]);

        return response()->json([
            'message' => 'Detail pendaftaran berhasil diambil.',
            'data' => $registration,
        ]);
    }

    /**
     * Perbarui data pendaftaran.
     */
    public function update(
        UpdateRegistrationRequest $request,
        Registration $registration
    ): JsonResponse {
        $registration = DB::transaction(function () use ($request, $registration) {
            $registration->update($request->only([
                'full_name',
                'passport_number',
                'nik',
                'phone',
                'birth_date',
                'gender',
                'registration_date',
                'departure_date',
                'package_id',
                'kloter_id',
                'meningitis_vaccine_status',
                'photo_status',
                'total_package_cost',
                'status',
            ]));

            // Jika total_package_cost diubah tanpa mengirim status manual, hitung ulang status keuangan
            if ($request->has('total_package_cost') && !$request->has('status')) {
                $registration->updateFinancialStatus();
            }

            // Jika perlengkapan dikirimkan, perbarui data perlengkapan
            if ($request->has('equipments') && is_array($request->equipments)) {
                $registration->equipments()->delete();
                foreach ($request->equipments as $equipment) {
                    $isReceived = $equipment['is_received'] ?? false;
                    $registration->equipments()->create([
                        'equipment_name' => $equipment['equipment_name'],
                        'size' => $equipment['size'] ?? null,
                        'is_received' => $isReceived,
                        'received_at' => $isReceived ? now() : null,
                    ]);
                }
            }

            return $registration;
        });

        return response()->json([
            'message' => 'Data pendaftaran berhasil diperbarui.',
            'data' => $registration->load([
                'package',
                'kloter',
                'payments',
                'equipments',
                'createdBy',
            ]),
        ]);
    }

    /**
     * Membatalkan pendaftaran.
     */
    public function cancel(Registration $registration): JsonResponse
    {
        $registration->update(['status' => 'cancelled']);

        return response()->json([
            'message' => 'Pendaftaran berhasil dibatalkan.',
            'data' => $registration,
        ]);
    }

    /**
     * TODO-DEPRECATED: Method convertToJamaah() dinonaktifkan per revisi [2026-09-02].
     * Jamaah kini diinput manual terpisah oleh Admin lewat endpoint POST /api/jamaah.
     * Data Pendaftaran dan data Jamaah adalah dua entitas independen — tidak ada auto-convert.
     *
     * Method ini sengaja TIDAK dihapus untuk menjaga histori kode.
     * Route-nya sudah di-comment di routes/api.php.
     *
     * @deprecated
     */
    // public function convertToJamaah(
    //     Request $request,
    //     Registration $registration
    // ): JsonResponse {
    //     // 1. Pastikan pendaftaran sudah memiliki pembayaran.
    //     if ($registration->payments()->count() === 0) {
    //         return response()->json([
    //             'message' => 'Pendaftaran belum memiliki pembayaran dan belum dapat dikonversi menjadi Jamaah.',
    //         ], 422);
    //     }
    //
    //     // 2. Pastikan NIK belum terdaftar sebagai Jamaah.
    //     $existingJamaah = Jamaah::where('nik', $registration->nik)->first();
    //
    //     if ($existingJamaah) {
    //         return response()->json([
    //             'message' => 'Jamaah dengan NIK ini sudah terdaftar.',
    //             'data' => $existingJamaah,
    //         ], 409);
    //     }
    //
    //     $jamaah = DB::transaction(function () use ($request, $registration) {
    //         $lastJamaah = Jamaah::orderBy('created_at', 'desc')->first();
    //         $nextNumber = 1;
    //         if ($lastJamaah && preg_match('/(\d+)$/', $lastJamaah->login_id, $matches)) {
    //             $nextNumber = (int) $matches[1] + 1;
    //         }
    //         $loginId = 'JAMAAH' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
    //         $jamaah = Jamaah::create([
    //             'login_id'          => $loginId,
    //             'nik'               => $registration->nik,
    //             'full_name'         => $registration->full_name,
    //             'birth_date'        => $registration->birth_date,
    //             'gender'            => $registration->gender,
    //             'phone'             => $registration->phone,
    //             'emergency_contact' => $request->emergency_contact ?? null,
    //             'status'            => 'active',
    //             'created_by'        => auth()->id(),
    //         ]);
    //         $registration->update(['status' => 'converted']);
    //         return $jamaah;
    //     });
    //
    //     return response()->json([
    //         'message' => 'Pendaftaran berhasil dikonversi menjadi Jamaah resmi.',
    //         'data' => $jamaah->load('createdBy'),
    //     ], 201);
    // }

    /**
     * Hapus data pendaftaran.
     *
     * Catatan revisi [2026-09-02]: Guard 'converted' dihapus karena alur Jamaah
     * kini terpisah — tidak ada lagi status 'converted' yang mengunci Pendaftaran.
     * Data lama berstatus 'converted' di database tetap bisa dihapus oleh admin.
     */
    public function destroy(Registration $registration): JsonResponse
    {
        // Guard status 'cancelled' — data yang sudah dibatalkan tidak perlu dihapus paksa
        // (biarkan admin tetap bisa hapus jika perlu; tidak ada guard di sini)

        DB::transaction(function () use ($registration) {
            $registration->equipments()->delete();
            $registration->payments()->delete();
            $registration->delete();
        });

        return response()->json([
            'message' => 'Data pendaftaran berhasil dihapus.',
        ]);
    }
}