<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\FinanceSummaryController;
use App\Http\Controllers\Api\HotelController;
use App\Http\Controllers\Api\JamaahController;
use App\Http\Controllers\Api\KloterController;
use App\Http\Controllers\Api\KloterScheduleController;
use App\Http\Controllers\Api\PackageController;
use App\Http\Controllers\Api\PackageItineraryController;
use App\Http\Controllers\Api\RegistrationController;
use App\Http\Controllers\Api\RegistrationPaymentController;
use App\Http\Controllers\Api\RoomController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\JamaahAuthController;
use App\Http\Controllers\Api\FamilyAuthController;
use App\Http\Controllers\Api\ScheduleController;
use App\Http\Controllers\Api\TourLeaderController;
use App\Http\Controllers\Api\MutawifController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\Api\OtherIncomeController;
use App\Http\Controllers\Api\TourLeaderAuthController;
use App\Http\Controllers\Api\SosIncidentController;
use App\Http\Controllers\Api\SosResponseController;

// ====================
// AUTH
// ====================

// Login Admin tidak memerlukan token
Route::post('/login', [AuthController::class, 'login']);

// Login Jamaah (Mobile App) tidak memerlukan token
Route::post('/jamaah/login', [JamaahAuthController::class, 'login']);

// Login Family (Mobile App) tidak memerlukan token
Route::post('/family/login', [FamilyAuthController::class, 'login']);


// ====================
// AUTH JAMAAH
// ====================

Route::middleware(['auth:sanctum', 'abilities:jamaah'])->group(function () {

    Route::get('/jamaah/me', [JamaahAuthController::class, 'me']);

    Route::post('/jamaah/logout', [JamaahAuthController::class, 'logout']);

});


// ====================
// AUTH FAMILY
// ====================

Route::middleware(['auth:sanctum', 'abilities:family'])->group(function () {

    Route::get('/family/me', [FamilyAuthController::class, 'me']);

    Route::post('/family/logout', [FamilyAuthController::class, 'logout']);

});


// ====================
// AUTH TOUR LEADER
// ====================

Route::post('/tour-leader/login', [TourLeaderAuthController::class, 'login']);

Route::middleware(['auth:sanctum', 'abilities:tour_leader'])->group(function () {

    Route::get('/tour-leader/me', [TourLeaderAuthController::class, 'me']);

    Route::post('/tour-leader/logout', [TourLeaderAuthController::class, 'logout']);

});


// ====================
// ROUTE ADMIN
// ====================

Route::middleware(['auth:sanctum', 'abilities:admin'])->group(function () {

    // ====================
    // AUTH ADMIN
    // ====================

    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/me', [AuthController::class, 'me']);


    // ====================
    // MASTER DATA HOTEL
    // ====================

    Route::get('/hotels', [HotelController::class, 'index']);

    Route::post('/hotels/find-or-create', [HotelController::class, 'findOrCreate']);


    // ====================
    // MASTER DATA PAKET UMRAH (CRUD)
    // ====================

    Route::get('/packages', [PackageController::class, 'index']);

    Route::post('/packages', [PackageController::class, 'store']);

    Route::get('/packages/{package}', [PackageController::class, 'show']);

    Route::put('/packages/{package}', [PackageController::class, 'update']);

    Route::delete('/packages/{package}', [PackageController::class, 'destroy']);


    // ====================
    // TEMPLATE ITINERARY PER PAKET
    // ====================

    Route::get(
        '/packages/{package}/itineraries',
        [PackageItineraryController::class, 'index']
    );

    Route::post(
        '/packages/{package}/itineraries',
        [PackageItineraryController::class, 'store']
    );

    Route::put(
        '/packages/{package}/itineraries/{itinerary}',
        [PackageItineraryController::class, 'update']
    );

    Route::delete(
        '/packages/{package}/itineraries/{itinerary}',
        [PackageItineraryController::class, 'destroy']
    );


    // ====================
    // KLOTER KEBERANGKATAN (CRUD)
    // ====================

    Route::get('/kloters', [KloterController::class, 'index']);

    Route::post('/kloters', [KloterController::class, 'store']);

    Route::get('/kloters/{kloter}', [KloterController::class, 'show']);

    Route::put('/kloters/{kloter}', [KloterController::class, 'update']);

    Route::delete('/kloters/{kloter}', [KloterController::class, 'destroy']);


    // ====================
    // RUNDOWN JADWAL PER KLOTER
    // ====================

    Route::get(
        '/kloters/{kloter}/schedules',
        [KloterScheduleController::class, 'index']
    );

    Route::post(
        '/kloters/{kloter}/schedules',
        [KloterScheduleController::class, 'store']
    );

    Route::post(
        '/kloters/{kloter}/schedules/generate-from-template',
        [KloterScheduleController::class, 'generateFromTemplate']
    );

    Route::put(
        '/kloters/{kloter}/schedules/{schedule}',
        [KloterScheduleController::class, 'update']
    );

    Route::delete(
        '/kloters/{kloter}/schedules/{schedule}',
        [KloterScheduleController::class, 'destroy']
    );


    // ====================
    // PEMBAGIAN KAMAR & ROOMMATE
    // ====================

    Route::get(
        '/kloters/{kloter}/rooms',
        [RoomController::class, 'index']
    );

    Route::post(
        '/kloters/{kloter}/auto-assign-rooms',
        [RoomController::class, 'autoAssign']
    );

    // CRUD Kamar Manual
    Route::post('/rooms', [RoomController::class, 'store']);

    Route::get('/rooms/{room}', [RoomController::class, 'show']);

    Route::put('/rooms/{room}', [RoomController::class, 'update']);

    Route::delete('/rooms/{room}', [RoomController::class, 'destroy']);

    // Manajemen Roommate Manual
    Route::post(
        '/rooms/{room}/members',
        [RoomController::class, 'addMember']
    );

    Route::put(
        '/rooms/{room}/members/{roomMember}',
        [RoomController::class, 'updateMember']
    );

    Route::delete(
        '/rooms/{room}/members/{roomMember}',
        [RoomController::class, 'removeMember']
    );


    // ====================
    // PENDAFTARAN
    // ====================

    Route::get(
        '/registrations',
        [RegistrationController::class, 'index']
    );

    Route::post(
        '/registrations',
        [RegistrationController::class, 'store']
    );

    Route::get(
        '/registrations/{registration}',
        [RegistrationController::class, 'show']
    );

    Route::put(
        '/registrations/{registration}',
        [RegistrationController::class, 'update']
    );

    Route::delete(
        '/registrations/{registration}',
        [RegistrationController::class, 'destroy']
    );

    // Membatalkan pendaftaran
    Route::post(
        '/registrations/{registration}/cancel',
        [RegistrationController::class, 'cancel']
    );

    // TODO-DEPRECATED:
    // Route convert-to-jamaah dinonaktifkan per revisi [2026-09-02].
    // Jamaah kini diinput manual terpisah oleh Admin.
    //
    // Route::post(
    //     '/registrations/{registration}/convert-to-jamaah',
    //     [RegistrationController::class, 'convertToJamaah']
    // );


    // ====================
    // KEUANGAN / PEMBAYARAN & PENGELUARAN
    // ====================

    Route::get(
        '/payments',
        [RegistrationPaymentController::class, 'allPayments']
    );

    Route::get(
        '/registrations/{registration}/payments',
        [RegistrationPaymentController::class, 'index']
    );

    Route::post(
        '/registrations/{registration}/payments',
        [RegistrationPaymentController::class, 'store']
    );

    Route::delete(
        '/payments/{payment}',
        [RegistrationPaymentController::class, 'destroy']
    );

    Route::put(
        '/payments/{payment}',
        [RegistrationPaymentController::class, 'update']
    );


    // ====================
    // MODULE EXPENSES
    // ====================

    Route::get('/expenses', [ExpenseController::class, 'index']);

    Route::post('/expenses', [ExpenseController::class, 'store']);

    Route::get('/expenses/{expense}', [ExpenseController::class, 'show']);

    Route::put('/expenses/{expense}', [ExpenseController::class, 'update']);

    Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy']);


    // ====================
    // SUMMARY KEUANGAN GLOBAL
    // ====================

    Route::get(
        '/finance/summary',
        [FinanceSummaryController::class, 'summary']
    );


    // ====================
    // OTHER INCOME / PEMASUKAN LAIN
    // ====================

    Route::get(
        '/other-incomes',
        [OtherIncomeController::class, 'index']
    );

    Route::post(
        '/other-incomes',
        [OtherIncomeController::class, 'store']
    );

    Route::get(
        '/other-incomes/{otherIncome}',
        [OtherIncomeController::class, 'show']
    );

    Route::put(
        '/other-incomes/{otherIncome}',
        [OtherIncomeController::class, 'update']
    );

    Route::delete(
        '/other-incomes/{otherIncome}',
        [OtherIncomeController::class, 'destroy']
    );


    // ====================
    // MANAJEMEN JAMAAH (CRUD)
    // ====================

    Route::get('/jamaah', [JamaahController::class, 'index']);

    Route::post('/jamaah', [JamaahController::class, 'store']);

    Route::get('/jamaah/{jamaah}', [JamaahController::class, 'show']);

    Route::put('/jamaah/{jamaah}', [JamaahController::class, 'update']);

    Route::delete('/jamaah/{jamaah}', [JamaahController::class, 'destroy']);


    // ====================
    // PERJALANAN / SCHEDULE (CRUD)
    // ====================

    Route::get('/schedules', [ScheduleController::class, 'index']);

    Route::post('/schedules', [ScheduleController::class, 'store']);

    Route::get('/schedules/{schedule}', [ScheduleController::class, 'show']);

    Route::put('/schedules/{schedule}', [ScheduleController::class, 'update']);

    Route::patch(
        '/schedules/{schedule}/status',
        [ScheduleController::class, 'updateStatus']
    );

    Route::delete(
        '/schedules/{schedule}',
        [ScheduleController::class, 'destroy']
    );


    // ====================
    // TOUR LEADER (CRUD)
    // ====================

    Route::apiResource(
        'tour-leaders',
        TourLeaderController::class
    );

    Route::post(
        'tour-leaders/{tour_leader}/kloters/{kloter}',
        [TourLeaderController::class, 'assignKloter']
    );

    Route::delete(
        'tour-leaders/{tour_leader}/kloters/{kloter}',
        [TourLeaderController::class, 'removeKloter']
    );


    // ====================
    // MUTAWIF (CRUD)
    // ====================

    Route::apiResource(
        'mutawifs',
        MutawifController::class
    );

    Route::post(
        'mutawifs/{mutawif}/kloters/{kloter}',
        [MutawifController::class, 'assignKloter']
    );

    Route::delete(
        'mutawifs/{mutawif}/kloters/{kloter}',
        [MutawifController::class, 'removeKloter']
    );


    // ====================
    // STOCK (CRUD)
    // ====================

    Route::get('/stocks', [StockController::class, 'index']);

    Route::post('/stocks', [StockController::class, 'store']);

    Route::get('/stocks/{stock}', [StockController::class, 'show']);

    Route::put('/stocks/{stock}', [StockController::class, 'update']);

    Route::delete('/stocks/{stock}', [StockController::class, 'destroy']);

    Route::get(
        '/stocks/{stock}/transactions',
        [StockController::class, 'transactions']
    );
});


// ====================
// EMERGENCY / SOS
// ====================
//
// Sengaja berada DI LUAR group Admin,
// Jamaah, dan Tour Leader.
//
// Ketiga role menggunakan endpoint yang sama,
// kemudian SosIncidentController menentukan
// hak akses berdasarkan ability token.
//

Route::middleware('auth:sanctum')->group(function () {

    Route::get(
        '/sos-incidents',
        [SosIncidentController::class, 'index']
    );

    Route::post(
        '/sos-incidents',
        [SosIncidentController::class, 'store']
    );

    Route::get(
        '/sos-incidents/{sosIncident}',
        [SosIncidentController::class, 'show']
    );

    Route::patch(
        '/sos-incidents/{sosIncident}/status',
        [SosIncidentController::class, 'updateStatus']
    );

    Route::get(
        '/sos-incidents/{sosIncident}/responses',
        [SosResponseController::class, 'index']
    );

    Route::post(
        '/sos-incidents/{sosIncident}/responses',
        [SosResponseController::class, 'store']
    );
});