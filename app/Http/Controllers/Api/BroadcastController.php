<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBroadcastRequest;
use App\Http\Requests\UpdateBroadcastRequest;
use App\Models\Broadcast;
use App\Policies\BroadcastPolicy;
use App\Services\BroadcastService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class BroadcastController extends Controller
{
    public function __construct(
        protected BroadcastService $broadcastService,
        protected BroadcastPolicy $policy
    ) {}

    /**
     * GET /api/broadcasts
     * Menampilkan riwayat broadcast.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();

        if (!$token || (!$token->can('admin') && !$token->can('tour_leader'))) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $query = Broadcast::with(['recipientKloters:id,code,name']);

        // Jika Tour Leader: hanya tampilkan broadcast miliknya atau yang menargetkan kloternya
        if ($token->can('tour_leader')) {
            $assignedKloterIds = $user->kloters()->pluck('kloters.id')->all();

            $query->where(function ($q) use ($user, $assignedKloterIds) {
                $q->where(function ($sub) use ($user) {
                    $sub->where('sender_type', get_class($user))
                        ->where('sender_id', (string) $user->id);
                })->orWhereHas('recipientKloters', function ($sub) use ($assignedKloterIds) {
                    $sub->whereIn('kloters.id', $assignedKloterIds);
                });
            });
        }

        $query->latest('sent_at');

        $perPage = min((int) $request->input('per_page', 15), 50);

        return response()->json([
            'message' => 'Broadcasts retrieved successfully',
            'data' => $query->paginate($perPage),
        ]);
    }

    /**
     * POST /api/broadcasts
     * Membuat dan menerbitkan siaran.
     */
    public function store(StoreBroadcastRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $targetType = $validated['target_type'];
        $kloterIds = $validated['kloter_ids'] ?? [];

        // Otorisasi melalui BroadcastPolicy
        if (!$this->policy->create($user, $targetType, $kloterIds)) {
            return response()->json([
                'message' => 'Forbidden. Anda tidak memiliki hak untuk mengirim siaran ke target ini.',
            ], 403);
        }

        $idempotencyKey = $request->header('X-Idempotency-Key');

        try {
            $result = $this->broadcastService->createAndPublish($validated, $user, $idempotencyKey);

            $status = $result['is_replay'] ? 200 : 201;

            return response()->json([
                'message' => $result['is_replay']
                    ? 'Broadcast telah diproses sebelumnya (idempotent replay).'
                    : 'Broadcast informasi berhasil diterbitkan dan sedang dikirim.',
                'data' => [
                    'broadcast' => $result['broadcast']->load('recipientKloters:id,code,name'),
                    'recipients_count' => $result['recipients_count'],
                    'notifications_queued' => $result['notifications_queued'],
                ],
            ], $status);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 409); // Conflict: key sama dengan payload berbeda
        } catch (\Throwable $e) {
            Log::error('Gagal menerbitkan broadcast', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
            ]);

            return response()->json([
                'message' => 'Terjadi kesalahan sistem saat memproses broadcast.',
            ], 500);
        }
    }

    /**
     * GET /api/broadcasts/{id}
     * Menampilkan detail broadcast dan statistik keterbacaan.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $broadcast = Broadcast::with(['recipientKloters:id,code,name'])->find($id);

        if (!$broadcast) {
            return response()->json(['message' => 'Broadcast tidak ditemukan.'], 404);
        }

        $user = $request->user();
        if (!$this->policy->view($user, $broadcast)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $totalNotifications = $broadcast->notifications()->count();
        $readCount = $broadcast->notifications()->whereNotNull('read_at')->count();

        return response()->json([
            'message' => 'Broadcast retrieved successfully',
            'data' => [
                'broadcast' => $broadcast,
                'stats' => [
                    'total_recipients' => $totalNotifications,
                    'read_count' => $readCount,
                    'unread_count' => $totalNotifications - $readCount,
                ],
            ],
        ]);
    }

    /**
     * PATCH /api/broadcasts/{id}
     * Memperbarui judul/pesan catatan broadcast (audit trail).
     */
    public function update(UpdateBroadcastRequest $request, string $id): JsonResponse
    {
        $broadcast = Broadcast::find($id);

        if (!$broadcast) {
            return response()->json(['message' => 'Broadcast tidak ditemukan.'], 404);
        }

        $user = $request->user();
        if (!$this->policy->update($user, $broadcast)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validated = $request->validated();
        $broadcast->update($validated);

        return response()->json([
            'message' => 'Broadcast berhasil diperbarui.',
            'data' => $broadcast->fresh(['recipientKloters:id,code,name']),
        ]);
    }
}
