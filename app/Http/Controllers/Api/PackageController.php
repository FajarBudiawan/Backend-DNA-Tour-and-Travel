<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePackageRequest;
use App\Http\Requests\UpdatePackageRequest;
use App\Models\Package;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PackageController extends Controller
{
    /**
     * Menampilkan semua data paket Umrah (dengan pencarian & filter).
     */
    public function index(Request $request): JsonResponse
    {
        $query = Package::with('createdBy')
            ->withCount(['kloters', 'registrations']);

        // Filter pencarian nama atau kategori
        if ($request->filled('q')) {
            $search = $request->q;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('category', 'ilike', "%{$search}%");
            });
        }

        // Filter status (draft, published, inactive)
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter is_featured
        if ($request->has('is_featured')) {
            $query->where('is_featured', filter_var($request->is_featured, FILTER_VALIDATE_BOOLEAN));
        }

        $packages = $query->latest()->get();

        return response()->json([
            'message' => 'Data paket Umrah berhasil diambil.',
            'data' => $packages,
        ]);
    }

    /**
     * Membuat paket Umrah baru.
     */
    public function store(StorePackageRequest $request): JsonResponse
    {
        $status = $request->status ?? 'draft';
        $publishedAt = ($status === 'published') ? now() : null;

        $package = Package::create([
            'name' => $request->name,
            'category' => $request->category,
            'status' => $status,
            'is_featured' => $request->is_featured ?? false,
            'published_at' => $publishedAt,
            'created_by' => auth()->id(),
        ]);

        return response()->json([
            'message' => 'Paket Umrah berhasil dibuat.',
            'data' => $package->load('createdBy'),
        ], 201);
    }

    /**
     * Menampilkan detail satu paket Umrah.
     */
    public function show(Package $package): JsonResponse
    {
        return response()->json([
            'message' => 'Detail paket Umrah berhasil diambil.',
            'data' => $package->load(['createdBy', 'kloters']),
        ]);
    }

    /**
     * Perbarui data paket Umrah.
     */
    public function update(UpdatePackageRequest $request, Package $package): JsonResponse
    {
        $data = $request->validated();

        if (isset($data['status']) && $data['status'] === 'published' && !$package->published_at) {
            $data['published_at'] = now();
        }

        $package->update($data);

        return response()->json([
            'message' => 'Data paket Umrah berhasil diperbarui.',
            'data' => $package->load('createdBy'),
        ]);
    }

    /**
     * Hapus data paket Umrah.
     */
    public function destroy(Package $package): JsonResponse
    {
        // Cek jika paket sudah digunakan oleh pendaftaran atau kloter
        if ($package->registrations()->count() > 0 || $package->kloters()->count() > 0) {
            return response()->json([
                'message' => 'Paket tidak dapat dihapus karena sudah memiliki data pendaftaran atau kloter terikat.',
            ], 422);
        }

        $package->delete();

        return response()->json([
            'message' => 'Paket Umrah berhasil dihapus.',
        ]);
    }
}
