<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Registration;
use App\Models\RegistrationPayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinanceSummaryController extends Controller
{
    /**
     * Menampilkan ringkasan keuangan global (Total Pemasukan, Pengeluaran, Piutang, Saldo Bersih).
     */
    public function summary(Request $request): JsonResponse
    {
        // 1. Total Pemasukan (SUM amount dari registration_payments)
        $totalPemasukan = (float) RegistrationPayment::sum('amount');

        // 2. Total Pengeluaran (SUM amount dari expenses)
        $totalPengeluaran = (float) Expense::sum('amount');

        // 3. Total Piutang (SUM remaining_cost dari registrations yang belum lunas / tidak batal)
        $activeRegistrations = Registration::where('status', '!=', 'cancelled')->get();
        $totalPiutang = (float) $activeRegistrations->sum(function ($registration) {
            return $registration->remaining_cost;
        });

        // 4. Saldo Bersih (total_pemasukan - total_pengeluaran)
        $saldoBersih = $totalPemasukan - $totalPengeluaran;

        return response()->json([
            'message' => 'Ringkasan keuangan berhasil diambil.',
            'data' => [
                'total_pemasukan' => $totalPemasukan,
                'total_pengeluaran' => $totalPengeluaran,
                'total_piutang' => $totalPiutang,
                'saldo_bersih' => $saldoBersih,
            ],
        ]);
    }
}
