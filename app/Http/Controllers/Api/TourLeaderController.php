<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTourLeaderRequest;
use App\Http\Requests\UpdateTourLeaderRequest;
use App\Http\Resources\TourLeaderResource;
use App\Models\Kloter;
use App\Models\TourLeader;
use Illuminate\Http\Request;

class TourLeaderController extends Controller
{
    public function index(Request $request)
    {
        $tourLeaders = TourLeader::query()
            ->when($request->q, function ($query, $q) {
                $query->where(function ($query) use ($q) {
                    $query->where('full_name', 'like', "%{$q}%")
                        ->orWhere('login_id', 'like', "%{$q}%")
                        ->orWhere('phone', 'like', "%{$q}%")
                        ->orWhere('experience', 'like', "%{$q}%");
                });
            })
            ->when($request->status, function ($query, $status) {
                $query->where('status', $status);
            })
            ->with('kloters')
            ->orderBy('full_name')
            ->get();

        return response()->json([
            'message' => 'Daftar Tour Leader berhasil diambil.',
            'data' => TourLeaderResource::collection($tourLeaders),
        ]);
    }

    public function store(StoreTourLeaderRequest $request)
    {
        $tourLeader = TourLeader::create($request->validated());

        $tourLeader->load('kloters');

        return response()->json([
            'message' => 'Tour Leader berhasil ditambahkan.',
            'data' => new TourLeaderResource($tourLeader),
        ], 201);
    }

    public function show(TourLeader $tourLeader)
    {
        $tourLeader->load('kloters');

        return response()->json([
            'message' => 'Detail Tour Leader berhasil diambil.',
            'data' => new TourLeaderResource($tourLeader),
        ]);
    }

    public function update(
        UpdateTourLeaderRequest $request,
        TourLeader $tourLeader
    ) {
        $tourLeader->update($request->validated());

        $tourLeader->load('kloters');

        return response()->json([
            'message' => 'Tour Leader berhasil diperbarui.',
            'data' => new TourLeaderResource($tourLeader->fresh()->load('kloters')),
        ]);
    }

    public function destroy(TourLeader $tourLeader)
    {
        $tourLeader->delete();

        return response()->json([
            'message' => 'Tour Leader berhasil dihapus.',
        ]);
    }

    public function assignKloter(
        TourLeader $tourLeader,
        Kloter $kloter
    ) {
        $tourLeader->kloters()->syncWithoutDetaching([
            $kloter->id => [
                'assigned_at' => now(),
            ],
        ]);

        $tourLeader->load('kloters');

        return response()->json([
            'message' => 'Tour Leader berhasil ditugaskan ke Kloter.',
            'data' => new TourLeaderResource($tourLeader),
        ]);
    }

    public function removeKloter(
        TourLeader $tourLeader,
        Kloter $kloter
    ) {
        $tourLeader->kloters()->detach($kloter->id);

        return response()->json([
            'message' => 'Tour Leader berhasil dilepas dari Kloter.',
        ]);
    }
}