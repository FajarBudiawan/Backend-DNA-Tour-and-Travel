<?php

namespace App\Services;

use App\Jobs\ProcessBroadcastJob;
use App\Models\Broadcast;
use App\Models\BroadcastLog;
use App\Models\FamilyRelation;
use App\Models\InternalUser;
use App\Models\Jamaah;
use App\Models\Kloter;
use App\Models\Notification;
use App\Models\TourLeader;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class BroadcastService
{
    /**
     * Mengambil daftar penerima berhak berdasarkan target.
     *
     * @param string $targetType 'all' atau 'kloter'
     * @param array<int, string> $kloterIds
     * @param bool $sendToFamily
     * @return Collection<int, array{recipient_type: string, recipient_id: string, name: string}>
     */
    public function getEligibleRecipients(string $targetType, array $kloterIds = [], bool $sendToFamily = true): Collection
    {
        $recipients = collect();

        if ($targetType === 'all') {
            // Target Global: Seluruh Jamaah pada perjalanan aktif (kloter dengan status 'active')
            $activeKloterIds = Kloter::where('status', 'active')->pluck('id');

            $jamaahs = Jamaah::where('status', 'active')
                ->whereIn('kloter_id', $activeKloterIds)
                ->get();

            foreach ($jamaahs as $jamaah) {
                $recipients->push([
                    'recipient_type' => Jamaah::class,
                    'recipient_id' => (string) $jamaah->id,
                    'name' => $jamaah->full_name ?? 'Jamaah',
                ]);
            }

            // Tambahkan juga Tour Leader yang bertugas di kloter-kloter aktif tersebut
            $activeTourLeaders = TourLeader::where('status', 'active')
                ->whereHas('kloters', function ($query) use ($activeKloterIds) {
                    $query->whereIn('kloters.id', $activeKloterIds);
                })
                ->get();

            foreach ($activeTourLeaders as $tl) {
                $recipients->push([
                    'recipient_type' => TourLeader::class,
                    'recipient_id' => (string) $tl->id,
                    'name' => $tl->full_name ?? 'Tour Leader',
                ]);
            }

            // Seluruh Admin aktif juga menerima siaran global
            $activeAdmins = InternalUser::where('status', 'active')->get();
            foreach ($activeAdmins as $admin) {
                $recipients->push([
                    'recipient_type' => InternalUser::class,
                    'recipient_id' => (string) $admin->id,
                    'name' => $admin->full_name ?? 'Admin',
                ]);
            }
        } elseif ($targetType === 'kloter') {
            if (empty($kloterIds)) {
                return $recipients;
            }

            // Target Kloter: Hanya Jamaah yang tergabung di kloter target
            $jamaahs = Jamaah::where('status', 'active')
                ->whereIn('kloter_id', $kloterIds)
                ->get();

            foreach ($jamaahs as $jamaah) {
                $recipients->push([
                    'recipient_type' => Jamaah::class,
                    'recipient_id' => (string) $jamaah->id,
                    'name' => $jamaah->full_name ?? 'Jamaah',
                ]);
            }

            // Tour Leader yang ditugaskan di kloter target
            $tourLeaders = TourLeader::where('status', 'active')
                ->whereHas('kloters', function ($query) use ($kloterIds) {
                    $query->whereIn('kloters.id', $kloterIds);
                })
                ->get();

            foreach ($tourLeaders as $tl) {
                $recipients->push([
                    'recipient_type' => TourLeader::class,
                    'recipient_id' => (string) $tl->id,
                    'name' => $tl->full_name ?? 'Tour Leader',
                ]);
            }
        }

        // Deduplikasi penerima secara ketat
        return $recipients->unique(fn($item) => $item['recipient_type'] . ':' . $item['recipient_id'])->values();
    }

    /**
     * Membuat dan menerbitkan siaran dengan transaksi aman dan idempotensi.
     *
     * @param array $data
     * @param mixed $actor
     * @param string|null $idempotencyKey
     * @return array
     */
    public function createAndPublish(array $data, $actor, ?string $idempotencyKey = null): array
    {
        // 1. Cek Idempotensi jika key disediakan
        if (!empty($idempotencyKey)) {
            $existing = Broadcast::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                // Verifikasi apakah payload sama
                if ($existing->message !== $data['message'] || $existing->title !== ($data['title'] ?? $existing->title)) {
                    throw new InvalidArgumentException('Idempotency key telah digunakan dengan payload berbeda.');
                }

                return [
                    'broadcast' => $existing,
                    'recipients_count' => $existing->notifications()->count(),
                    'notifications_queued' => $existing->notifications()->where('delivery_status', 'pending')->count(),
                    'is_replay' => true,
                ];
            }
        }

        $actorType = get_class($actor);
        $actorId = (string) $actor->id;
        $targetType = $data['target_type'] ?? 'all';
        $kloterIds = $data['kloter_ids'] ?? [];
        $sendToFamily = (bool) ($data['send_to_family'] ?? true);

        // Ambil target penerima terverifikasi
        $recipients = $this->getEligibleRecipients($targetType, $kloterIds, $sendToFamily);

        $createdNotificationIds = [];
        $broadcast = null;

        // 2. Jalankan pembuatan master broadcast dan inbox dalam transaksi database
        DB::transaction(function () use (
            &$broadcast,
            &$createdNotificationIds,
            $data,
            $actorType,
            $actorId,
            $targetType,
            $kloterIds,
            $idempotencyKey,
            $recipients
        ) {
            $broadcast = Broadcast::create([
                'id' => (string) Str::uuid(),
                'title' => $data['title'] ?? 'Pengumuman Penting',
                'sender_type' => $actorType,
                'sender_id' => $actorId,
                'message' => $data['message'],
                'category' => $data['category'] ?? 'general',
                'priority' => $data['priority'] ?? 'normal',
                'target_type' => $targetType,
                'status' => 'published',
                'idempotency_key' => $idempotencyKey,
                'sent_at' => now(),
            ]);

            // Kaitkan relasi kloter target jika target_type kloter
            if ($targetType === 'kloter' && !empty($kloterIds)) {
                $pivotData = [];
                foreach ($kloterIds as $kId) {
                    $pivotData[$kId] = ['id' => (string) Str::uuid()];
                }
                $broadcast->recipientKloters()->sync($pivotData);
            }

            // Fan-out: buat record individual di notifications dan broadcast_logs
            foreach ($recipients as $recipient) {
                $notif = Notification::create([
                    'id' => (string) Str::uuid(),
                    'broadcast_id' => $broadcast->id,
                    'recipient_type' => $recipient['recipient_type'],
                    'recipient_id' => $recipient['recipient_id'],
                    'type' => 'broadcast',
                    'title' => $broadcast->title,
                    'message' => $broadcast->message,
                    'delivery_status' => 'pending',
                ]);

                $createdNotificationIds[] = $notif->id;

                // Catat log historis
                BroadcastLog::create([
                    'id' => (string) Str::uuid(),
                    'broadcast_id' => $broadcast->id,
                    'recipient_type' => $recipient['recipient_type'] === TourLeader::class ? 'tour_leader' : 'jamaah',
                    'recipient_id' => $recipient['recipient_id'],
                    'status' => 'sent',
                ]);
            }
        });

        // 3. Dispatch background job hanya setelah commit berhasil
        if (!empty($createdNotificationIds) && $broadcast) {
            DB::afterCommit(function () use ($broadcast, $createdNotificationIds) {
                dispatch(new ProcessBroadcastJob($broadcast, $createdNotificationIds));
            });
        }

        return [
            'broadcast' => $broadcast,
            'recipients_count' => count($recipients),
            'notifications_queued' => count($createdNotificationIds),
            'is_replay' => false,
        ];
    }
}
