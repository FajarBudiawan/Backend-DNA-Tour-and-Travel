<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOtherIncomeRequest;
use App\Http\Requests\UpdateOtherIncomeRequest;
use App\Models\OtherIncome;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OtherIncomeController extends Controller
{
    /**
     * Menampilkan daftar pemasukan lain.
     */
    public function index(Request $request): JsonResponse
    {
        $query = OtherIncome::with('recordedBy');

        // Filter pencarian berdasarkan source atau reference number
        if ($request->filled('q')) {
            $search = $request->q;

            $query->where(function ($q) use ($search) {
                $q->where('source', 'ilike', "%{$search}%")
                    ->orWhere('reference_number', 'ilike', "%{$search}%");
            });
        }

        // Filter berdasarkan kategori
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        // Filter berdasarkan metode pembayaran
        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        // Filter tanggal
        if ($request->filled('date_from')) {
            $query->whereDate('income_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('income_date', '<=', $request->date_to);
        }

        $incomes = $query
            ->latest('income_date')
            ->latest('created_at')
            ->get();

        $totalIncome = (float) $query
            ->clone()
            ->sum('amount');

        return response()->json([
            'message' => 'Daftar pemasukan lain berhasil diambil.',
            'summary' => [
                'total_income' => $totalIncome,
            ],
            'data' => $incomes,
        ]);
    }

    /**
     * Menyimpan pemasukan lain baru.
     */
    public function store(StoreOtherIncomeRequest $request): JsonResponse
    {
        $income = DB::transaction(function () use ($request) {
            return OtherIncome::create([
                'source' => $request->source,
                'category' => $request->category,
                'amount' => $request->amount,
                'payment_method' => $request->payment_method,
                'income_date' => $request->income_date,
                'reference_number' => $request->reference_number,
                'notes' => $request->notes,
                'recorded_by' => auth()->id(),
            ]);
        });

        return response()->json([
            'message' => 'Pemasukan lain berhasil dicatat.',
            'data' => $income->load('recordedBy'),
        ], 201);
    }

    /**
     * Menampilkan detail pemasukan lain.
     */
    public function show(OtherIncome $otherIncome): JsonResponse
    {
        return response()->json([
            'message' => 'Detail pemasukan lain berhasil diambil.',
            'data' => $otherIncome->load('recordedBy'),
        ]);
    }

    /**
     * Memperbarui pemasukan lain.
     */
    public function update(
        UpdateOtherIncomeRequest $request,
        OtherIncome $otherIncome
    ): JsonResponse {
        $otherIncome->update(
            $request->only([
                'source',
                'category',
                'amount',
                'payment_method',
                'income_date',
                'notes',
            ])
        );

        return response()->json([
            'message' => 'Data pemasukan lain berhasil diperbarui.',
            'data' => $otherIncome->fresh()->load('recordedBy'),
        ]);
    }

    /**
     * Menghapus pemasukan lain secara soft delete.
     */
    public function destroy(OtherIncome $otherIncome): JsonResponse
    {
        $otherIncome->delete();

        return response()->json([
            'message' => 'Pemasukan lain berhasil dihapus.',
        ]);
    }
}