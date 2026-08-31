<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTourLeaderRequest;
use App\Http\Requests\UpdateTourLeaderRequest;
use App\Models\Kloter;
use App\Models\TourLeader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TourLeaderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = TourLeader::with('kloters:id,name,code')->when($request->filled('q'), fn ($q) =>
            $q->where(fn ($x) => $x->where('full_name', 'ilike', '%'.$request->q.'%')->orWhere('login_id', 'ilike', '%'.$request->q.'%'))
        )->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))->latest()->get();
        return response()->json(['message' => 'Daftar tour leader berhasil diambil.', 'data' => $items]);
    }

    public function store(StoreTourLeaderRequest $request): JsonResponse
    {
        $item = TourLeader::create($request->validated());
        return response()->json(['message' => 'Tour leader berhasil dibuat.', 'data' => $item], 201);
    }

    public function show(TourLeader $tourLeader): JsonResponse
    {
        return response()->json(['message' => 'Detail tour leader berhasil diambil.', 'data' => $tourLeader->load('kloters:id,name,code')]);
    }

    public function update(UpdateTourLeaderRequest $request, TourLeader $tourLeader): JsonResponse
    {
        $tourLeader->update($request->validated());
        return response()->json(['message' => 'Tour leader berhasil diperbarui.', 'data' => $tourLeader->fresh()->load('kloters:id,name,code')]);
    }

    public function destroy(TourLeader $tourLeader): JsonResponse
    {
        if ($tourLeader->kloters()->exists()) return response()->json(['message' => 'Tour leader masih ditugaskan ke kloter. Lepaskan penugasan terlebih dahulu.'], 409);
        $tourLeader->delete();
        return response()->json(['message' => 'Tour leader berhasil dihapus.']);
    }

    public function assign(Request $request, Kloter $kloter): JsonResponse
    {
        $data = $request->validate(['tour_leader_id' => 'required|uuid|exists:tour_leaders,id']);
        DB::transaction(function () use ($data, $kloter) {
            if (DB::table('kloter_leader_assignments')->where('kloter_id', $kloter->id)->exists())
                throw ValidationException::withMessages(['kloter_id' => 'Kloter sudah memiliki tour leader.']);
            if (DB::table('kloter_leader_assignments')->where('tour_leader_id', $data['tour_leader_id'])->exists())
                throw ValidationException::withMessages(['tour_leader_id' => 'Tour leader sudah ditugaskan ke kloter lain.']);
            $kloter->tourLeaders()->attach($data['tour_leader_id'], ['id' => (string) \Illuminate\Support\Str::uuid(), 'assigned_at' => now()]);
        });
        return response()->json(['message' => 'Tour leader berhasil ditugaskan.', 'data' => $kloter->load('tourLeaders')]);
    }

    public function unassign(Kloter $kloter): JsonResponse
    {
        if ($kloter->tourLeaders()->detach() === 0) return response()->json(['message' => 'Kloter belum memiliki tour leader.'], 404);
        return response()->json(['message' => 'Penugasan tour leader berhasil dilepas.']);
    }
}
