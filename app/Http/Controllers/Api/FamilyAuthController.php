<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\JamaahMobileResource;
use App\Models\Jamaah;
use Illuminate\Http\Request;

class FamilyAuthController extends Controller
{
    /**
     * Login Family menggunakan ID Jamaah (login_id).
     * Menerbitkan Sanctum token dengan ability ['family'].
     */
    public function login(Request $request)
    {
        $request->validate([
            'login_id' => 'required|string|max:50',
        ]);

        $jamaah = Jamaah::where('login_id', $request->login_id)->first();

        if (!$jamaah || $jamaah->status !== 'active') {
            return response()->json([
                'message' => 'Jamaah tidak ditemukan atau tidak aktif.',
            ], 401);
        }

        $token = $jamaah->createToken('family-mobile', ['family'])->plainTextToken;

        return response()->json([
            'message' => 'Login Family berhasil.',
            'token' => $token,
            'jamaah' => $jamaah,
        ]);
    }

    /**
     * Mendapatkan data Jamaah untuk Family mode.
     */
    public function me(Request $request)
    {
        $jamaah = $request->user()->load([
            'package',
            'kloter.tourLeaders',
            'kloter.mutawifs',
            'kloter.hotelMakkah',
            'kloter.hotelMadinah',
        ]);

        return response()->json([
            'jamaah' => new JamaahMobileResource($jamaah),
        ]);
    }

    /**
     * Logout Family (revoke current access token).
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logout Family berhasil.',
        ]);
    }
}