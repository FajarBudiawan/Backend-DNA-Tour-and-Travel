<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRegistrationPaymentRequest;
use App\Models\Registration;
use App\Models\RegistrationPayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RegistrationPaymentController extends Controller
{
    /**
     * Menampilkan seluruh data transaksi pembayaran (Untuk Admin Keuangan).
     */
    public function allPayments(Request $request): JsonResponse
    {
        $query = RegistrationPayment::with([
            'registration',
            'recordedBy',
        ]);

        // Filter berdasarkan metode pembayaran
        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        // Filter berdasarkan jenis pembayaran (down_payment / full_payment)
        if ($request->filled('payment_type')) {
            $query->where('payment_type', $request->payment_type);
        }

        // Filter rentang tanggal pembayaran
        if ($request->filled('date_from')) {
            $query->whereDate('payment_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('payment_date', '<=', $request->date_to);
        }

        // Pencarian berdasarkan nama jamaah atau nomor pendaftaran
        if ($request->filled('q')) {
            $search = $request->q;
            $query->whereHas('registration', function ($q) use ($search) {
                $q->where('full_name', 'ilike', "%{$search}%")
                  ->orWhere('registration_number', 'ilike', "%{$search}%");
            });
        }

        $payments = $query->latest('payment_date')->get();

        return response()->json([
            'message' => 'Seluruh data transaksi pembayaran berhasil diambil.',
            'data' => $payments,
        ]);
    }

    /**
     * Menampilkan semua pembayaran dari satu pendaftaran.
     */
    public function index(Registration $registration): JsonResponse
    {
        $payments = $registration->payments()
            ->with('recordedBy')
            ->latest('payment_date')
            ->get();

        return response()->json([
            'message' => 'Data pembayaran pendaftaran berhasil diambil.',
            'financial_summary' => [
                'total_package_cost' => (float) $registration->total_package_cost,
                'total_paid' => $registration->total_paid,
                'remaining_cost' => $registration->remaining_cost,
                'status' => $registration->status,
            ],
            'data' => $payments,
        ]);
    }

    /**
     * Menambahkan pembayaran ke pendaftaran.
     */
    public function store(
        StoreRegistrationPaymentRequest $request,
        Registration $registration
    ): JsonResponse {
        $payment = DB::transaction(function () use ($request, $registration) {
            $payment = RegistrationPayment::create([
                'registration_id' => $registration->id,
                'amount' => $request->amount,
                'payment_type' => $request->payment_type,
                'payment_method' => $request->payment_method,
                'payment_date' => $request->payment_date,
                'recorded_by' => auth()->id(),
                'notes' => $request->notes,
            ]);

            // Perbarui status keuangan pendaftaran secara otomatis (dp_paid, fully_paid)
            $registration->unsetRelation('payments');
            $registration->updateFinancialStatus();

            return $payment;
        });

        // Unset relation agar relasi registration dimuat ulang dengan status terbaru dari database
        $payment->unsetRelation('registration');

        return response()->json([
            'message' => 'Pembayaran pendaftaran berhasil dicatat.',
            'data' => $payment->load([
                'registration',
                'recordedBy',
            ]),
        ], 201);
    }
}