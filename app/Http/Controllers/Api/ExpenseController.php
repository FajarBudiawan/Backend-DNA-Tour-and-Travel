<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;
use App\Models\Expense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExpenseController extends Controller
{
    /**
     * Menampilkan daftar pengeluaran kas dengan filter dan ringkasan agregat.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Expense::with('recordedBy');

        // Filter pencarian berdasarkan nama vendor atau nomor referensi
        if ($request->filled('q')) {
            $search = $request->q;
            $query->where(function ($q) use ($search) {
                $q->where('vendor', 'ilike', "%{$search}%")
                  ->orWhere('reference_number', 'ilike', "%{$search}%");
            });
        }

        // Filter berdasarkan metode pembayaran
        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        // Filter rentang tanggal pengeluaran
        if ($request->filled('date_from')) {
            $query->whereDate('expense_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('expense_date', '<=', $request->date_to);
        }

        // Salin query dasar untuk menghitung ringkasan agregat (sebelum filter kategori diterapkan)
        $summaryBase = clone $query;

        $summary = [
            'akomodasi_tiket' => (float) (clone $summaryBase)->where('category', 'akomodasi_tiket')->sum('amount'),
            'perlengkapan' => (float) (clone $summaryBase)->where('category', 'perlengkapan')->sum('amount'),
            'operasional_bus' => (float) (clone $summaryBase)->where('category', 'operasional_bus')->sum('amount'),
            'total_expense' => (float) (clone $summaryBase)->sum('amount'),
        ];

        // Filter berdasarkan kategori jika ada
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        $expenses = $query->latest('expense_date')->latest('created_at')->get();

        return response()->json([
            'message' => 'Daftar pengeluaran kas berhasil diambil.',
            'summary' => $summary,
            'data' => $expenses,
        ]);
    }

    /**
     * Menyimpan pengeluaran kas baru.
     */
    public function store(StoreExpenseRequest $request): JsonResponse
    {
        $expense = DB::transaction(function () use ($request) {
            return Expense::create([
                'vendor' => $request->vendor,
                'category' => $request->category,
                'amount' => $request->amount,
                'payment_method' => $request->payment_method,
                'expense_date' => $request->expense_date,
                'reference_number' => $request->reference_number,
                'notes' => $request->notes,
                'recorded_by' => auth()->id(),
            ]);
        });

        return response()->json([
            'message' => 'Pengeluaran kas berhasil dicatat.',
            'data' => $expense->load('recordedBy'),
        ], 201);
    }

    /**
     * Menampilkan detail satu pengeluaran kas.
     */
    public function show(Expense $expense): JsonResponse
    {
        return response()->json([
            'message' => 'Detail pengeluaran kas berhasil diambil.',
            'data' => $expense->load('recordedBy'),
        ]);
    }

    /**
     * Memperbarui data pengeluaran kas.
     */
    public function update(
        UpdateExpenseRequest $request,
        Expense $expense
    ): JsonResponse {
        $expense->update($request->only([
            'vendor',
            'category',
            'amount',
            'payment_method',
            'expense_date',
            'reference_number',
            'notes',
        ]));

        return response()->json([
            'message' => 'Data pengeluaran kas berhasil diperbarui.',
            'data' => $expense->fresh()->load('recordedBy'),
        ]);
    }

    /**
     * Menghapus data pengeluaran kas.
     */
    public function destroy(Expense $expense): JsonResponse
    {
        $expense->delete();

        return response()->json([
            'message' => 'Data pengeluaran kas berhasil dihapus.',
        ]);
    }
}
