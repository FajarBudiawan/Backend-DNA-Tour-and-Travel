<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Jamaah;
use Illuminate\Http\Request;
use App\Http\Resources\JamaahMobileResource;

class JamaahAuthController extends Controller
{
public function login(Request $request)
{
    $request->validate([
        'login_id' => 'required|string',
    ]);

    $jamaah = Jamaah::where('login_id', $request->login_id)->first();

    if (!$jamaah || $jamaah->status !== 'active') {
        return response()->json([
            'message' => 'Jamaah tidak ditemukan atau tidak aktif.',
        ], 401);
    }

    $token = $jamaah->createToken('jamaah-mobile', ['jamaah'])->plainTextToken;

    return response()->json([
        'message' => 'Login berhasil.',
        'token' => $token,
        'jamaah' => $jamaah,
    ]);
}

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

public function logout(request $request)
{
    $request->user()->currentAccessToken()->delete();
    return response()->json([
        'message' => 'Logout berhasil.',
    ]);
}
}