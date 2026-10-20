<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeleteDeviceTokenRequest;
use App\Http\Requests\StoreDeviceTokenRequest;
use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * @group Device Token FCM
 *
 * API untuk mendaftarkan dan menonaktifkan FCM device token perangkat mobile.
 * Token digunakan sebagai tujuan pengiriman push notification Firebase Cloud Messaging.
 *
 * Endpoint ini digunakan oleh Jamaah, Family (login sebagai Jamaah), InternalUser (admin), dan TourLeader.
 */
class DeviceTokenController extends Controller
{
    /**
     * Daftarkan atau perbarui FCM device token
     *
     * Mendaftarkan FCM token perangkat ke akun yang sedang login.
     * Jika token yang sama sudah pernah terdaftar (atas akun siapa pun), kepemilikannya
     * akan dipindahkan secara aman ke akun yang sedang aktif saat ini (pola Upsert).
     * Hal ini mencegah kebocoran notifikasi ketika perangkat berganti akun (misalnya logout → login akun berbeda).
     *
     * Field `tokenable_type`, `tokenable_id`, dan `is_active` **tidak dapat dikirim oleh client**
     * dan selalu diisi secara otomatis oleh server.
     *
     * @authenticated
     * @bodyParam fcm_token string required Token registrasi FCM dari Firebase SDK. Example: fTHHz7u8TQyI6...
     * @bodyParam platform string required Platform perangkat. Nilai: `android` atau `ios`. Example: android
     * @bodyParam device_id string Identifier unik perangkat (opsional, misalnya Android ID atau iOS identifierForVendor). Example: a1b2c3d4e5f6
     */
    public function store(StoreDeviceTokenRequest $request): JsonResponse
    {
        $user  = $request->user();
        $token = $user->currentAccessToken();

        // Tentukan class model pemilik berdasarkan ability token Sanctum.
        // Family menggunakan model Jamaah dengan ability 'family'.
        $ownerClass = $this->resolveOwnerClass($token);

        if ($ownerClass === null) {
            return response()->json([
                'message' => 'Unauthorized.',
            ], 403);
        }

        $validated = $request->validated();
        $hasDeviceId = array_key_exists('device_id', $validated);

        // Siapkan atribut pembaruan dasar
        $attributes = [
            'tokenable_type' => $ownerClass,
            'tokenable_id'   => $user->id,
            'platform'       => $validated['platform'],
            'is_active'      => true,
            'last_used_at'   => now(),
        ];

        // Kebijakan device_id:
        // 1. Jika field disertakan dalam payload (termasuk null eksplisit): perbarui nilai.
        // 2. Jika field tidak disertakan dalam payload: pertahankan nilai yang sudah ada di database.
        if ($hasDeviceId) {
            $attributes['device_id'] = $validated['device_id'];
        }

        // Penanganan Race Condition & Registrasi Token:
        // Gunakan try-catch di luar transaksi untuk menangani UniqueConstraintViolationException
        // jika dua request simultan mencoba melakukan insert untuk fcm_token yang sama.
        $deviceToken = null;
        $maxAttempts = 3;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $deviceToken = DB::transaction(function () use ($validated, $attributes, $hasDeviceId) {
                    $existing = DeviceToken::where('fcm_token', $validated['fcm_token'])
                        ->lockForUpdate()
                        ->first();

                    if ($existing) {
                        // Jika device_id tidak disertakan, pertahankan nilai lama
                        $updateData = $attributes;
                        if (!$hasDeviceId) {
                            $updateData['device_id'] = $existing->device_id;
                        }

                        $existing->fill($updateData);
                        $existing->save();

                        return $existing;
                    }

                    // Buat token baru jika belum ada
                    $createData = array_merge(['fcm_token' => $validated['fcm_token']], $attributes);
                    if (!$hasDeviceId) {
                        $createData['device_id'] = null;
                    }

                    return DeviceToken::create($createData);
                });

                break; // Berhasil, keluar dari loop
            } catch (\Illuminate\Database\UniqueConstraintViolationException|\Illuminate\Database\QueryException $e) {
                // Periksa apakah ini pelanggaran unique constraint
                $isUniqueViolation = ($e instanceof \Illuminate\Database\UniqueConstraintViolationException)
                    || (str_contains($e->getMessage(), '23505') || str_contains($e->getMessage(), 'unique constraint'));

                if ($isUniqueViolation && $attempt < $maxAttempts) {
                    // Terjadi race condition saat simultaneous insert.
                    // Transaksi sebelumnya otomatis di-rollback oleh DB::transaction.
                    // Tunggu sesaat (backoff acak 10-50ms) dan coba kembali (attempt berikutnya akan menemukan record via lockForUpdate).
                    usleep(random_int(10000, 50000));
                    continue;
                }

                // Jika bukan unique constraint violation atau sudah mencapai batas attempt, lempar kembali
                throw $e;
            }
        }

        // Catat aktivitas tanpa menyimpan nilai token lengkap di log
        Log::info('FCM device token registered.', [
            'owner_class' => $ownerClass,
            'owner_id'    => $user->id,
            'platform'    => $validated['platform'],
            'device_id'   => $validated['device_id'] ?? null,
            'token_id'    => $deviceToken->id,
            'was_updated' => ! $deviceToken->wasRecentlyCreated,
        ]);

        return response()->json([
            'message' => 'Device token berhasil didaftarkan.',
            'data'    => [
                'id'           => $deviceToken->id,
                'platform'     => $deviceToken->platform,
                'device_id'    => $deviceToken->device_id,
                'is_active'    => $deviceToken->is_active,
                'last_used_at' => $deviceToken->last_used_at,
            ],
        ], $deviceToken->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * Nonaktifkan FCM device token perangkat ini
     *
     * Menonaktifkan satu token FCM yang ditentukan dalam request.
     * Hanya token milik akun yang sedang login yang dapat dinonaktifkan.
     * Token milik akun lain tidak dapat dinonaktifkan melalui endpoint ini.
     *
     * Endpoint ini **tidak** menghapus seluruh token milik akun.
     * Endpoint ini dirancang untuk dipanggil saat pengguna logout **dari satu perangkat tertentu**,
     * sedangkan sesi/token Sanctum logout ditangani oleh endpoint `/logout` masing-masing auth controller.
     *
     * @authenticated
     * @bodyParam fcm_token string required Token FCM perangkat yang ingin dinonaktifkan. Example: fTHHz7u8TQyI6...
     */
    public function destroy(DeleteDeviceTokenRequest $request): JsonResponse
    {
        $user  = $request->user();
        $token = $user->currentAccessToken();

        $ownerClass = $this->resolveOwnerClass($token);

        if ($ownerClass === null) {
            return response()->json([
                'message' => 'Unauthorized.',
            ], 403);
        }

        $validated = $request->validated();

        // Cari token — hanya izinkan jika token tersebut memang milik user yang sedang login
        $deviceToken = DeviceToken::where('fcm_token', $validated['fcm_token'])
            ->where('tokenable_type', $ownerClass)
            ->where('tokenable_id', $user->id)
            ->first();

        if (! $deviceToken) {
            // Kembalikan 404 generik — jangan ungkapkan bahwa token tersebut milik akun lain
            return response()->json([
                'message' => 'Device token tidak ditemukan.',
            ], 404);
        }

        $deviceToken->update([
            'is_active' => false,
        ]);

        Log::info('FCM device token deactivated.', [
            'owner_class' => $ownerClass,
            'owner_id'    => $user->id,
            'token_id'    => $deviceToken->id,
        ]);

        return response()->json([
            'message' => 'Device token berhasil dinonaktifkan.',
        ]);
    }

    /**
     * Menentukan FQCN model pemilik berdasarkan ability token Sanctum.
     *
     * Family menggunakan model Jamaah dengan ability 'family'.
     * Jika ability tidak dikenal, kembalikan null (→ 403).
     */
    private function resolveOwnerClass($token): ?string
    {
        if ($token && ($token->can('jamaah') || $token->can('family'))) {
            return \App\Models\Jamaah::class;
        }

        if ($token && $token->can('admin')) {
            return \App\Models\InternalUser::class;
        }

        if ($token && $token->can('tour_leader')) {
            return \App\Models\TourLeader::class;
        }

        return null;
    }
}
