<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMuthawwifRequest;
use App\Http\Requests\UpdateMuthawwifRequest;
use App\Models\Kloter;
use App\Models\Muthawwif;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MuthawwifController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = Muthawwif::with('kloters:id,name,code')->when($request->filled('q'), fn ($q) => $q->where('name', 'ilike', '%'.$request->q.'%'))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))->latest()->get();
        return response()->json(['message'=>'Daftar muthawwif berhasil diambil.','data'=>$items]);
    }
    public function store(StoreMuthawwifRequest $request): JsonResponse { $x=Muthawwif::create($request->validated()); return response()->json(['message'=>'Muthawwif berhasil dibuat.','data'=>$x],201); }
    public function show(Muthawwif $muthawwif): JsonResponse { return response()->json(['message'=>'Detail muthawwif berhasil diambil.','data'=>$muthawwif->load('kloters:id,name,code')]); }
    public function update(UpdateMuthawwifRequest $request, Muthawwif $muthawwif): JsonResponse { $muthawwif->update($request->validated()); return response()->json(['message'=>'Muthawwif berhasil diperbarui.','data'=>$muthawwif->fresh()->load('kloters:id,name,code')]); }
    public function destroy(Muthawwif $muthawwif): JsonResponse { if($muthawwif->kloters()->exists()) return response()->json(['message'=>'Muthawwif masih ditugaskan ke kloter.'],409); $muthawwif->delete(); return response()->json(['message'=>'Muthawwif berhasil dihapus.']); }
    public function assign(Request $request, Kloter $kloter): JsonResponse
    {
        $data=$request->validate(['muthawwif_id'=>'required|uuid|exists:muthawwifs,id']);
        DB::transaction(function() use($data,$kloter){
            if(DB::table('kloter_muthawwif_assignments')->where('kloter_id',$kloter->id)->exists()) throw ValidationException::withMessages(['kloter_id'=>'Kloter sudah memiliki muthawwif.']);
            if(DB::table('kloter_muthawwif_assignments')->where('muthawwif_id',$data['muthawwif_id'])->exists()) throw ValidationException::withMessages(['muthawwif_id'=>'Muthawwif sudah ditugaskan ke kloter lain.']);
            $kloter->muthawwifs()->attach($data['muthawwif_id'],['id'=>(string)Str::uuid(),'assigned_at'=>now()]);
        });
        return response()->json(['message'=>'Muthawwif berhasil ditugaskan.','data'=>$kloter->load('muthawwifs')]);
    }
    public function unassign(Kloter $kloter): JsonResponse { if($kloter->muthawwifs()->detach()===0) return response()->json(['message'=>'Kloter belum memiliki muthawwif.'],404); return response()->json(['message'=>'Penugasan muthawwif berhasil dilepas.']); }
}
