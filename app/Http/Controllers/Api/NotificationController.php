<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * GET /api/notifications
     * Tampilkan list notifikasi user yang login
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $token = $user->currentAccessToken();

        $query = Notification::query();

        // Filter berdasarkan role
        if ($token->can('jamaah')) {
            $query->where('recipient_type', \App\Models\Jamaah::class)
                  ->where('recipient_id', $user->id);
        } elseif ($token->can('admin')) {
            $query->where('recipient_type', \App\Models\InternalUser::class)
                  ->where('recipient_id', $user->id);
        } elseif ($token->can('tour_leader')) {
            $query->where('recipient_type', \App\Models\TourLeader::class)
                  ->where('recipient_id', $user->id);
        } else {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        // Urutkan dari terbaru
        $query->latest('created_at');

        return response()->json([
            'message' => 'Notifications retrieved successfully',
            'data' => $query->paginate(50)
        ]);
    }

    /**
     * PATCH /api/notifications/{id}/read
     * Mark notifikasi sebagai sudah dibaca
     */
    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $notification = Notification::find($id);

        if (!$notification) {
            return response()->json(['message' => 'Notification not found.'], 404);
        }

        $user = $request->user();
        $token = $user->currentAccessToken();

        // Tentukan user model class
        $userModel = null;
        if ($token->can('jamaah')) {
            $userModel = \App\Models\Jamaah::class;
        } elseif ($token->can('admin')) {
            $userModel = \App\Models\InternalUser::class;
        } elseif ($token->can('tour_leader')) {
            $userModel = \App\Models\TourLeader::class;
        }

        // Cek ownership — hanya penerima yang boleh mark as read
        if (
            $notification->recipient_id !== $user->id ||
            $notification->recipient_type !== $userModel
        ) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        // Kalau sudah read sebelumnya, skip
        if ($notification->isRead()) {
            return response()->json([
                'message' => 'Notification already marked as read.',
                'data' => $notification
            ]);
        }

        // Mark as read
        $notification->markAsRead();

        return response()->json([
            'message' => 'Notification marked as read.',
            'data' => $notification
        ]);
    }
}