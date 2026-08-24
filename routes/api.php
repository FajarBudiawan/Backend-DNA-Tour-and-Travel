<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\JamaahController;
use App\Http\Controllers\Api\KloterController;
use App\Http\Controllers\Api\PackageController;
use App\Http\Controllers\Api\RegistrationController;
use App\Http\Controllers\Api\RegistrationPaymentController;
use App\Http\Controllers\Api\RoomController;
use Illuminate\Support\Facades\Route;

// ====================
// AUTH
// ====================

// Login tidak memerlukan token
Route::post('/login', [AuthController::class, 'login']);

// Route yang memerlukan login
Route::middleware('auth:sanctum')->group(function () {

    // AUTH
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // ====================
    // MASTER DATA PAKET UMRAH (CRUD)
    // ====================

    Route::get('/packages', [PackageController::class, 'index']);
    Route::post('/packages', [PackageController::class, 'store']);
    Route::get('/packages/{package}', [PackageController::class, 'show']);
    Route::put('/packages/{package}', [PackageController::class, 'update']);
    Route::delete('/packages/{package}', [PackageController::class, 'destroy']);

    // ====================
    // KLOTER KEBERANGKATAN (CRUD)
    // ====================

    Route::get('/kloters', [KloterController::class, 'index']);
    Route::post('/kloters', [KloterController::class, 'store']);
    Route::get('/kloters/{kloter}', [KloterController::class, 'show']);
    Route::put('/kloters/{kloter}', [KloterController::class, 'update']);
    Route::delete('/kloters/{kloter}', [KloterController::class, 'destroy']);

    // ====================
    // PEMBAGIAN KAMAR & ROOMMATE
    // ====================

    // Lihat kamar & jamaah belum dapat kamar per kloter
    Route::get('/kloters/{kloter}/rooms', [RoomController::class, 'index']);

    // Pembagian kamar otomatis per kloter
    Route::post('/kloters/{kloter}/auto-assign-rooms', [RoomController::class, 'autoAssign']);

    // CRUD Kamar Manual
    Route::post('/rooms', [RoomController::class, 'store']);
    Route::put('/rooms/{room}', [RoomController::class, 'update']);
    Route::delete('/rooms/{room}', [RoomController::class, 'destroy']);

    // Manajemen Roommate Manual (Tambah/Keluarkan Jamaah dari Kamar)
    Route::post('/rooms/{room}/members', [RoomController::class, 'addMember']);
    Route::delete('/rooms/{room}/members/{registration}', [RoomController::class, 'removeMember']);

    // ====================
    // PENDAFTARAN
    // ====================

    // Menampilkan semua pendaftaran (dengan pencarian & filter)
    Route::get('/registrations', [RegistrationController::class, 'index']);

    // Membuat pendaftaran baru
    Route::post('/registrations', [RegistrationController::class, 'store']);

    // Menampilkan detail satu pendaftaran
    Route::get('/registrations/{registration}', [RegistrationController::class, 'show']);

    // Memperbarui data pendaftaran
    Route::put('/registrations/{registration}', [RegistrationController::class, 'update']);

    // Menghapus data pendaftaran
    Route::delete('/registrations/{registration}', [RegistrationController::class, 'destroy']);

    // Membatalkan pendaftaran
    Route::post('/registrations/{registration}/cancel', [RegistrationController::class, 'cancel']);

    // Mengonversi pendaftaran ke Jamaah resmi
    Route::post(
        '/registrations/{registration}/convert-to-jamaah',
        [RegistrationController::class, 'convertToJamaah']
    );

    // ====================
    // KEUANGAN / PEMBAYARAN
    // ====================

    // Laporan seluruh transaksi pembayaran (Admin Keuangan)
    Route::get('/payments', [RegistrationPaymentController::class, 'allPayments']);

    // Menampilkan pembayaran dari satu pendaftaran
    Route::get(
        '/registrations/{registration}/payments',
        [RegistrationPaymentController::class, 'index']
    );

    // Menambahkan pembayaran ke pendaftaran
    Route::post(
        '/registrations/{registration}/payments',
        [RegistrationPaymentController::class, 'store']
    );

    // ====================
    // MANAJEMEN JAMAAH (CRUD)
    // ====================

    Route::get('/jamaah', [JamaahController::class, 'index']);
    Route::post('/jamaah', [JamaahController::class, 'store']);
    Route::get('/jamaah/{jamaah}', [JamaahController::class, 'show']);
    Route::put('/jamaah/{jamaah}', [JamaahController::class, 'update']);
    Route::delete('/jamaah/{jamaah}', [JamaahController::class, 'destroy']);
});