<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRegistrationRequest;
use App\Http\Requests\UpdateRegistrationRequest;
use App\Models\Jamaah;
use App\Models\Registration;
use App\Services\RegistrationEquipmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RegistrationController extends Controller
{
    /**
     * Dependency injection untuk sinkronisasi equipment dengan stock.
     */
    public function __construct(
        private RegistrationEquipmentService $registrationEquipmentService
    ) {
    }

    /**
     * Menampilkan semua data pendaftaran
     * dengan pencarian dan filter.
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

        // Filter pencarian berdasarkan nama, NIK,
        // nomor telepon, atau nomor pendaftaran.
        if ($request->filled('q')) {
            $search = $request->q;

            $query->where(function ($q) use ($search) {
                $q->where(
                    'full_name',
                    'ilike',
                    "%{$search}%"
                )
                    ->orWhere(
                        'nik',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'phone',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'registration_number',
                        'ilike',
                        "%{$search}%"
                    );
            });
        }

        // Filter status.
        if ($request->filled('status')) {
            $query->where(
                'status',
                $request->status
            );
        }

        // Filter package.
        if ($request->filled('package_id')) {
            $query->where(
                'package_id',
                $request->package_id
            );
        }

        // Filter kloter.
        if ($request->filled('kloter_id')) {
            $query->where(
                'kloter_id',
                $request->kloter_id
            );
        }

        $registrations = $query
            ->latest()
            ->get();

        // Sinkronisasi status keuangan.
        $registrations->each(
            function ($registration) {
                $registration->updateFinancialStatus();
            }
        );

        return response()->json([
            'message' =>
                'Data pendaftaran berhasil diambil.',
            'data' => $registrations,
        ]);
    }

    /**
     * Menyimpan pendaftaran baru.
     */
    public function store(
        StoreRegistrationRequest $request
    ): JsonResponse {
        $registration = DB::transaction(
            function () use ($request) {

                // =====================================================
                // REGISTRATION NUMBER
                // =====================================================

                $regNumber =
                    $request->registration_number;

                if (!$regNumber) {
                    $regNumber =
                        'REG-'
                        . date('Ymd')
                        . '-'
                        . strtoupper(
                            Str::random(5)
                        );
                }

                // =====================================================
                // NORMALISASI STATUS
                // =====================================================

                $status =
                    $request->status ?? 'unpaid';

                if ($status === 'dp') {
                    $status = 'dp_paid';
                } elseif ($status === 'paid') {
                    $status = 'fully_paid';
                }

                // =====================================================
                // BUAT REGISTRATION
                // =====================================================

                $registration =
                    Registration::create([
                        'registration_number' =>
                            $regNumber,

                        'pilgrim_id' =>
                            $request->pilgrim_id,

                        'full_name' =>
                            $request->full_name,

                        'passport_number' =>
                            $request->passport_number,

                        'nik' =>
                            $request->nik,

                        'phone' =>
                            $request->phone,

                        'birth_date' =>
                            $request->birth_date,

                        'gender' =>
                            $request->gender,

                        'registration_date' =>
                            $request->registration_date,

                        'departure_date' =>
                            $request->departure_date,

                        'package_id' =>
                            $request->package_id,

                        'kloter_id' =>
                            $request->kloter_id,

                        'meningitis_vaccine_status' =>
                            $request
                                ->meningitis_vaccine_status,

                        'photo_status' =>
                            $request
                                ->photo_status,

                        'total_package_cost' =>
                            $request
                                ->total_package_cost
                            ?? 30000000,

                        'status' =>
                            $status,

                        'created_by' =>
                            auth()->id(),
                    ]);

                // =====================================================
                // INITIAL PAYMENT
                // =====================================================

                if (
                    $request->has(
                        'initial_payment'
                    )
                    && is_array(
                        $request->initial_payment
                    )
                ) {
                    $payData =
                        $request->initial_payment;

                    $registration
                        ->payments()
                        ->create([
                            'amount' =>
                                $payData['amount'],

                            'payment_type' =>
                                $payData['payment_type']
                                ?? 'down_payment',

                            'payment_method' =>
                                $payData['payment_method']
                                ?? 'bca_transfer',

                            'payment_date' =>
                                $payData['payment_date']
                                ?? now(),

                            'recorded_by' =>
                                auth()->id(),

                            'notes' =>
                                $payData['notes']
                                ?? 'Pembayaran awal saat pendaftaran',
                        ]);

                    $registration
                        ->updateFinancialStatus();
                }

                // =====================================================
                // EQUIPMENT
                // =====================================================

                $equipments =
                    $request->equipments;

                /*
                 * Jika equipment tidak dikirim,
                 * buat equipment standar berdasarkan gender.
                 */
                if (
                    empty($equipments)
                    || !is_array($equipments)
                ) {
                    if (
                        $request->gender === 'L'
                    ) {
                        $equipments = [
                            [
                                'equipment_name' =>
                                    'Koper Besar',
                                'is_received' =>
                                    false,
                                'size' =>
                                    null,
                            ],
                            [
                                'equipment_name' =>
                                    'Koper Kabin',
                                'is_received' =>
                                    false,
                                'size' =>
                                    null,
                            ],
                            [
                                'equipment_name' =>
                                    'Seragam Batik',
                                'is_received' =>
                                    false,
                                'size' =>
                                    null,
                            ],
                            [
                                'equipment_name' =>
                                    'Buku Panduan',
                                'is_received' =>
                                    false,
                                'size' =>
                                    null,
                            ],
                            [
                                'equipment_name' =>
                                    'Kain Ihram',
                                'is_received' =>
                                    false,
                                'size' =>
                                    null,
                            ],
                            [
                                'equipment_name' =>
                                    'Tas Selempang',
                                'is_received' =>
                                    false,
                                'size' =>
                                    null,
                            ],
                            [
                                'equipment_name' =>
                                    'Tas Sandal',
                                'is_received' =>
                                    false,
                                'size' =>
                                    null,
                            ],
                            [
                                'equipment_name' =>
                                    'Syall',
                                'is_received' =>
                                    false,
                                'size' =>
                                    null,
                            ],
                            [
                                'equipment_name' =>
                                    'Sabuk',
                                'is_received' =>
                                    false,
                                'size' =>
                                    null,
                            ],
                        ];
                    } else {
                        $equipments = [
                            [
                                'equipment_name' =>
                                    'Koper Besar',
                                'is_received' =>
                                    false,
                                'size' =>
                                    null,
                            ],
                            [
                                'equipment_name' =>
                                    'Koper Kabin',
                                'is_received' =>
                                    false,
                                'size' =>
                                    null,
                            ],
                            [
                                'equipment_name' =>
                                    'Seragam Batik',
                                'is_received' =>
                                    false,
                                'size' =>
                                    null,
                            ],
                            [
                                'equipment_name' =>
                                    'Buku Panduan',
                                'is_received' =>
                                    false,
                                'size' =>
                                    null,
                            ],
                            [
                                'equipment_name' =>
                                    'Kerudung Merah',
                                'is_received' =>
                                    false,
                                'size' =>
                                    null,
                            ],
                            [
                                'equipment_name' =>
                                    'Kerudung Putih',
                                'is_received' =>
                                    false,
                                'size' =>
                                    null,
                            ],
                            [
                                'equipment_name' =>
                                    'Tas Selempang',
                                'is_received' =>
                                    false,
                                'size' =>
                                    null,
                            ],
                            [
                                'equipment_name' =>
                                    'Tas Sandal',
                                'is_received' =>
                                    false,
                                'size' =>
                                    null,
                            ],
                            [
                                'equipment_name' =>
                                    'Syall',
                                'is_received' =>
                                    false,
                                'size' =>
                                    null,
                            ],
                        ];
                    }
                }

                /*
                 * Simpan equipment dengan state awal false.
                 *
                 * Jika request meminta is_received=true,
                 * sinkronisasi stok dilakukan melalui
                 * RegistrationEquipmentService.
                 */
                foreach (
                    $equipments as $equipment
                ) {
                    $isReceived =
                        $equipment['is_received']
                        ?? false;

                    $registrationEquipment =
                        $registration
                            ->equipments()
                            ->create([
                                'equipment_name' =>
                                    $equipment[
                                        'equipment_name'
                                    ],

                                'stock_id' =>
                                    $equipment[
                                        'stock_id'
                                    ]
                                    ?? null,

                                'size' =>
                                    $equipment['size']
                                    ?? null,

                                'is_received' =>
                                    false,

                                'received_at' =>
                                    null,
                            ]);

                    if ($isReceived) {
                        $this
                            ->registrationEquipmentService
                            ->syncEquipment(
                                $registrationEquipment,
                                [
                                    'stock_id' =>
                                        $equipment[
                                            'stock_id'
                                        ]
                                        ?? null,

                                    'size' =>
                                        $equipment[
                                            'size'
                                        ]
                                        ?? null,

                                    'is_received' =>
                                        true,
                                ]
                            );
                    }
                }

                return $registration;
            }
        );

        return response()->json([
            'message' =>
                'Pendaftaran berhasil dibuat.',

            'data' =>
                $registration->load([
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
    public function show(
        Registration $registration
    ): JsonResponse {
        // Sinkronisasi status keuangan.
        $registration
            ->updateFinancialStatus();

        $registration->load([
            'package',
            'kloter',
            'payments.recordedBy',
            'equipments',
            'createdBy',
        ]);

        return response()->json([
            'message' =>
                'Detail pendaftaran berhasil diambil.',

            'data' =>
                $registration,
        ]);
    }

    /**
     * Perbarui data pendaftaran.
     */
    public function update(
        UpdateRegistrationRequest $request,
        Registration $registration
    ): JsonResponse {
        $registration = DB::transaction(
            function () use (
                $request,
                $registration
            ) {

                // =====================================================
                // UPDATE DATA REGISTRATION
                // =====================================================

                $registration->update(
                    $request->only([
                        'pilgrim_id',
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
                    ])
                );

                // =====================================================
                // UPDATE / PROCESS PAYMENT TRANSACTION
                // =====================================================
                if (
                    $request->has('initial_payment')
                    && is_array($request->initial_payment)
                ) {
                    $payData = $request->initial_payment;
                    $amount = isset($payData['amount']) ? (float) $payData['amount'] : 0;

                    $firstPayment = $registration
                        ->payments()
                        ->orderBy('created_at', 'asc')
                        ->first();

                    if ($firstPayment) {
                        if ($amount > 0) {
                            $firstPayment->update([
                                'amount' => $amount,
                                'payment_type' =>
                                    $payData['payment_type']
                                    ?? ($request->status === 'fully_paid'
                                        ? 'full_payment'
                                        : 'down_payment'),
                                'payment_method' =>
                                    $payData['payment_method']
                                    ?? $firstPayment->payment_method
                                    ?? 'bca_transfer',
                                'payment_date' =>
                                    $payData['payment_date']
                                    ?? $firstPayment->payment_date
                                    ?? now(),
                                'notes' =>
                                    $payData['notes']
                                    ?? $firstPayment->notes
                                    ?? 'Pembayaran pendaftaran',
                            ]);
                        } else {
                            if ($request->status === 'unpaid') {
                                $firstPayment->delete();
                            } else {
                                $firstPayment->update(['amount' => 0]);
                            }
                        }
                    } elseif ($amount > 0) {
                        $registration->payments()->create([
                            'amount' => $amount,
                            'payment_type' =>
                                $payData['payment_type']
                                ?? ($request->status === 'fully_paid'
                                    ? 'full_payment'
                                    : 'down_payment'),
                            'payment_method' =>
                                $payData['payment_method']
                                ?? 'bca_transfer',
                            'payment_date' =>
                                $payData['payment_date']
                                ?? now(),
                            'recorded_by' =>
                                auth()->id(),
                            'notes' =>
                                $payData['notes']
                                ?? 'Pembayaran pendaftaran',
                        ]);
                    }

                    $registration->updateFinancialStatus();
                } elseif ($request->has('status') && $request->status === 'unpaid') {
                    $firstPayment = $registration
                        ->payments()
                        ->orderBy('created_at', 'asc')
                        ->first();

                    if ($firstPayment) {
                        $firstPayment->delete();
                    }

                    $registration->updateFinancialStatus();
                } elseif (
                    $request->has('total_package_cost')
                    && !$request->has('status')
                ) {
                    $registration->updateFinancialStatus();
                }

                // =====================================================
                // UPDATE EQUIPMENT
                // =====================================================

                if (
                    $request->has('equipments')
                    && is_array(
                        $request->equipments
                    )
                ) {
                    /*
                     * Ambil seluruh equipment lama.
                     */
                    $existingEquipments =
                        $registration
                            ->equipments()
                            ->get();

                    /*
                     * Menyimpan ID equipment yang benar-benar
                     * diproses dari request.
                     */
                    $processedEquipmentIds = [];

                    /*
                     * =================================================
                     * PROSES SETIAP EQUIPMENT DARI REQUEST
                     * =================================================
                     */
                    foreach (
                        $request->equipments
                        as $equipmentData
                    ) {
                        $equipmentId =
                            $equipmentData['id']
                            ?? null;

                        $equipment = null;

                        // =============================================
                        // 1. CARI BERDASARKAN UUID
                        // =============================================

                        if ($equipmentId) {
                            $equipment =
                                $existingEquipments
                                    ->firstWhere(
                                        'id',
                                        $equipmentId
                                    );
                        }

                        // =============================================
                        // 2. FALLBACK BERDASARKAN NAMA EQUIPMENT
                        //
                        // Dipakai apabila frontend mengirim
                        // ID sementara seperti:
                        // temp-1789303009338
                        // =============================================

                        if (!$equipment) {
                            $equipment =
                                $existingEquipments
                                    ->first(
                                        function (
                                            $existingEquipment
                                        ) use (
                                            $equipmentData,
                                            $processedEquipmentIds
                                        ) {
                                            return
                                                !in_array(
                                                    $existingEquipment->id,
                                                    $processedEquipmentIds,
                                                    true
                                                )
                                                &&
                                                $existingEquipment
                                                    ->equipment_name
                                                    ===
                                                    $equipmentData[
                                                        'equipment_name'
                                                    ];
                                        }
                                    );
                        }

                        // =============================================
                        // 3. EQUIPMENT LAMA → UPDATE
                        // =============================================

                        if ($equipment) {
                            $this
                                ->registrationEquipmentService
                                ->updateEquipment(
                                    $equipment,
                                    [
                                        'equipment_name' =>
                                            $equipmentData[
                                                'equipment_name'
                                            ],

                                        'stock_id' =>
                                            $equipmentData[
                                                'stock_id'
                                            ]
                                            ?? null,

                                        'size' =>
                                            $equipmentData[
                                                'size'
                                            ]
                                            ?? null,

                                        'is_received' =>
                                            $equipmentData[
                                                'is_received'
                                            ]
                                            ?? false,
                                    ]
                                );

                            $processedEquipmentIds[] =
                                $equipment->id;

                            continue;
                        }

                        // =============================================
                        // 4. EQUIPMENT BENAR-BENAR BARU → CREATE
                        // =============================================

                        $isReceived =
                            $equipmentData[
                                'is_received'
                            ]
                            ?? false;

                        $equipment =
                            $registration
                                ->equipments()
                                ->create([
                                    'equipment_name' =>
                                        $equipmentData[
                                            'equipment_name'
                                        ],

                                    'stock_id' =>
                                        $equipmentData[
                                            'stock_id'
                                        ]
                                        ?? null,

                                    'size' =>
                                        $equipmentData[
                                            'size'
                                        ]
                                        ?? null,

                                    'is_received' =>
                                        false,

                                    'received_at' =>
                                        null,
                                ]);

                        /*
                         * Jika equipment baru langsung diterima,
                         * sinkronkan stok.
                         */
                        if ($isReceived) {
                            $this
                                ->registrationEquipmentService
                                ->syncEquipment(
                                    $equipment,
                                    [
                                        'stock_id' =>
                                            $equipmentData[
                                                'stock_id'
                                            ]
                                            ?? null,

                                        'size' =>
                                            $equipmentData[
                                                'size'
                                            ]
                                            ?? null,

                                        'is_received' =>
                                            true,
                                    ]
                                );
                        }

                        $processedEquipmentIds[] =
                            $equipment->id;
                    }

                    // =================================================
                    // 5. HAPUS EQUIPMENT LAMA YANG TIDAK LAGI
                    //    ADA DI REQUEST
                    // =================================================

                    foreach (
                        $existingEquipments
                        as $existingEquipment
                    ) {
                        if (
                            !in_array(
                                $existingEquipment->id,
                                $processedEquipmentIds,
                                true
                            )
                        ) {
                            $this
                                ->registrationEquipmentService
                                ->deleteEquipment(
                                    $existingEquipment
                                );
                        }
                    }
                }

                return $registration;
            }
        );

        return response()->json([
            'message' =>
                'Data pendaftaran berhasil diperbarui.',

            'data' =>
                $registration->load([
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
    public function cancel(
        Registration $registration
    ): JsonResponse {
        $registration->update([
            'status' => 'cancelled',
        ]);

        return response()->json([
            'message' =>
                'Pendaftaran berhasil dibatalkan.',

            'data' =>
                $registration,
        ]);
    }

    /**
     * TODO-DEPRECATED:
     * Method convertToJamaah() dinonaktifkan
     * per revisi [2026-09-02].
     *
     * Jamaah kini diinput manual terpisah oleh Admin.
     */

    // public function convertToJamaah(
    //     Request $request,
    //     Registration $registration
    // ): JsonResponse {
    //     if ($registration->payments()->count() === 0) {
    //         return response()->json([
    //             'message' =>
    //                 'Pendaftaran belum memiliki pembayaran
    //                  dan belum dapat dikonversi menjadi Jamaah.',
    //         ], 422);
    //     }

    //     $existingJamaah = Jamaah::where(
    //         'nik',
    //         $registration->nik
    //     )->first();

    //     if ($existingJamaah) {
    //         return response()->json([
    //             'message' =>
    //                 'Jamaah dengan NIK ini sudah terdaftar.',
    //             'data' => $existingJamaah,
    //         ], 409);
    //     }

    //     $jamaah = DB::transaction(function () use (
    //         $request,
    //         $registration
    //     ) {
    //         $lastJamaah = Jamaah::orderBy(
    //             'created_at',
    //             'desc'
    //         )->first();

    //         $nextNumber = 1;

    //         if (
    //             $lastJamaah
    //             && preg_match(
    //                 '/(\d+)$/',
    //                 $lastJamaah->login_id,
    //                 $matches
    //             )
    //         ) {
    //             $nextNumber =
    //                 (int) $matches[1] + 1;
    //         }

    //         $loginId =
    //             'JAMAAH'
    //             . str_pad(
    //                 $nextNumber,
    //                 3,
    //                 '0',
    //                 STR_PAD_LEFT
    //             );

    //         $jamaah = Jamaah::create([
    //             'login_id' =>
    //                 $loginId,

    //             'nik' =>
    //                 $registration->nik,

    //             'full_name' =>
    //                 $registration->full_name,

    //             'birth_date' =>
    //                 $registration->birth_date,

    //             'gender' =>
    //                 $registration->gender,

    //             'phone' =>
    //                 $registration->phone,

    //             'emergency_contact' =>
    //                 $request->emergency_contact
    //                 ?? null,

    //             'status' =>
    //                 'active',

    //             'created_by' =>
    //                 auth()->id(),
    //         ]);

    //         $registration->update([
    //             'status' => 'converted',
    //         ]);

    //         return $jamaah;
    //     });

    //     return response()->json([
    //         'message' =>
    //             'Pendaftaran berhasil dikonversi
    //              menjadi Jamaah resmi.',

    //         'data' =>
    //             $jamaah->load('createdBy'),

    //     ], 201);
    // }

    /**
     * Hapus data pendaftaran.
     */
    public function destroy(
        Registration $registration
    ): JsonResponse {
        DB::transaction(
            function () use ($registration) {

                // Ambil semua equipment.
                $equipments =
                    $registration
                        ->equipments()
                        ->get();

                // Hapus melalui service agar
                // stok yang sudah diterima dikembalikan.
                foreach (
                    $equipments as $equipment
                ) {
                    $this
                        ->registrationEquipmentService
                        ->deleteEquipment(
                            $equipment
                        );
                }

                // Hapus payment.
                $registration
                    ->payments()
                    ->delete();

                // Hapus registration.
                $registration->delete();
            }
        );

        return response()->json([
            'message' =>
                'Data pendaftaran berhasil dihapus.',
        ]);
    }
}