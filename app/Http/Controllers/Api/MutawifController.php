<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMutawifRequest;
use App\Http\Requests\UpdateMutawifRequest;
use App\Http\Resources\MutawifResource;
use App\Models\Kloter;
use App\Models\Mutawif;
use Illuminate\Http\Request;

class MutawifController extends Controller
{
    public function index(Request $request)
    {
        $query = Mutawif::query()
            ->with('kloters');

        if ($request->filled('q')) {
            $search = $request->q;

            $query->where(function ($q) use ($search) {
                $q->where('code', 'ilike', "%{$search}%")
                    ->orWhere('name', 'ilike', "%{$search}%")
                    ->orWhere('language', 'ilike', "%{$search}%")
                    ->orWhere('experience', 'ilike', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $mutawifs = $query
            ->orderBy('name')
            ->get();

        return MutawifResource::collection($mutawifs);
    }

    public function store(StoreMutawifRequest $request)
    {
        $mutawif = Mutawif::create($request->validated());

        return new MutawifResource(
            $mutawif->load('kloters')
        );
    }

    public function show(Mutawif $mutawif)
    {
        return new MutawifResource(
            $mutawif->load('kloters')
        );
    }

    public function update(
        UpdateMutawifRequest $request,
        Mutawif $mutawif
    ) {
        $mutawif->update($request->validated());

        return new MutawifResource(
            $mutawif->load('kloters')
        );
    }

    public function destroy(Mutawif $mutawif)
    {
        $mutawif->delete();

        return response()->json([
            'message' => 'Muttawif berhasil dihapus.',
        ]);
    }

    public function assignKloter(
        Mutawif $mutawif,
        Kloter $kloter
    ) {
        $mutawif->kloters()->syncWithoutDetaching([
            $kloter->id => [
                'assigned_at' => now(),
            ],
        ]);

        return new MutawifResource(
            $mutawif->load('kloters')
        );
    }

    public function removeKloter(
        Mutawif $mutawif,
        Kloter $kloter
    ) {
        $mutawif->kloters()->detach($kloter->id);

        return response()->json([
            'message' => 'Muttawif berhasil dilepas dari Kloter.',
        ]);
    }
}