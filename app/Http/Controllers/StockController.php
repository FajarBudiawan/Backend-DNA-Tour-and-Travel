<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStockRequest;
use App\Http\Requests\UpdateStockRequest;
use App\Http\Resources\StockResource;
use App\Models\Stock;
use App\Services\StockService;
use App\Http\Resources\StockTransactionResource;

class StockController extends Controller
{
    public function __construct(
        private StockService $stockService
    ) {
    }

    public function index()
    {
        $stocks = Stock::with('sizes')
            ->latest()
            ->get();

        return StockResource::collection($stocks);
    }

    public function store(StoreStockRequest $request)
    {
        $stock = $this->stockService->create(
            $request->validated()
        );

        return new StockResource(
            $stock->load('sizes')
        );
    }

    public function show(Stock $stock)
    {
        return new StockResource(
            $stock->load('sizes')
        );
    }

    public function update(
        UpdateStockRequest $request,
        Stock $stock
    ) {
        $stock = $this->stockService->update(
            $stock,
            $request->validated()
        );

        return new StockResource(
            $stock->load('sizes')
        );
    }

    public function destroy(Stock $stock)
    {
        $stock->delete();

        return response()->json([
            'message' => 'Stok berhasil dihapus.'
        ]);
    }

    public function transactions(Stock $stock)
    {
        $transactions = $stock->transactions()
            ->latest('created_at')
            ->get();

        return StockTransactionResource::collection($transactions);
    }
}