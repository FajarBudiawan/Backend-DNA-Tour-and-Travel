<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStockItemRequest;
use App\Http\Requests\UpdateStockItemRequest;
use App\Models\StockItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockItemController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query=StockItem::query()->when($request->filled('q'),fn($q)=>$q->where('name','ilike','%'.$request->q.'%'))
            ->when($request->filled('category'),fn($q)=>$q->where('category',$request->category))
            ->when($request->filled('location'),fn($q)=>$q->where('location',$request->location));
        $items=$query->orderBy('name')->get();
        return response()->json(['message'=>'Daftar stok berhasil diambil.','summary'=>['total_items'=>$items->count(),'low_stock'=>$items->where('stock_status','low')->count(),'out_of_stock'=>$items->where('stock_status','out_of_stock')->count()],'data'=>$items]);
    }
    public function store(StoreStockItemRequest $request): JsonResponse { $x=StockItem::create($request->validated()); return response()->json(['message'=>'Barang stok berhasil dibuat.','data'=>$x],201); }
    public function show(StockItem $stockItem): JsonResponse { return response()->json(['message'=>'Detail stok berhasil diambil.','data'=>$stockItem]); }
    public function update(UpdateStockItemRequest $request, StockItem $stockItem): JsonResponse { $stockItem->update($request->validated()); return response()->json(['message'=>'Barang stok berhasil diperbarui.','data'=>$stockItem->fresh()]); }
    public function destroy(StockItem $stockItem): JsonResponse { $stockItem->delete(); return response()->json(['message'=>'Barang stok berhasil dihapus.']); }
    public function adjust(Request $request, StockItem $stockItem): JsonResponse
    {
        $data=$request->validate(['delta'=>'required|integer|not_in:0','notes'=>'nullable|string|max:255']);
        $item=DB::transaction(function() use($data,$stockItem){
            $locked=StockItem::lockForUpdate()->findOrFail($stockItem->id);
            $next=$locked->quantity+$data['delta'];
            if($next<0) throw ValidationException::withMessages(['delta'=>'Jumlah pengurangan melebihi stok yang tersedia.']);
            $locked->update(['quantity'=>$next]);
            return $locked->fresh();
        });
        return response()->json(['message'=>'Jumlah stok berhasil disesuaikan.','data'=>$item]);
    }
}
