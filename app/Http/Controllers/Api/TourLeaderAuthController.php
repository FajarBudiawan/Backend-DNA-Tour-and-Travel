<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TourLeader;
use Illuminate\Http\Request;

class TourLeaderAuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'login_id' => 'required|string|max:50',
        ]);

        $tourLeader = TourLeader::where(
            'login_id',
            $request->login_id
        )->first();

        if (!$tourLeader || $tourLeader->status === 'inactive') {
            return response()->json([
                'message' => 'Tour Leader tidak ditemukan atau tidak aktif.',
            ], 401);
        }

        $token = $tourLeader->createToken(
            'tour-leader-mobile',
            ['tour_leader']
        )->plainTextToken;

        return response()->json([
            'message' => 'Login Tour Leader berhasil.',
            'token' => $token,
            'tour_leader' => $tourLeader,
        ]);
    }

    public function me(Request $request)
    {
        $tourLeader = $request->user()->load([
            'kloters',
        ]);

        return response()->json([
            'tour_leader' => $tourLeader,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logout Tour Leader berhasil.',
        ]);
    }
}