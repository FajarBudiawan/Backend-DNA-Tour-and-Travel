<?php

namespace App\Policies;

use App\Models\InternalUser;
use App\Models\TourLeader;

class BroadcastPolicy
{
    /**
     * Menentukan apakah aktor berwenang membuat siaran (global atau kloter).
     *
     * @param mixed $user
     * @param string $targetType 'all' atau 'kloter'
     * @param array<int, string> $kloterIds
     * @return bool
     */
    public function create($user, string $targetType, array $kloterIds = []): bool
    {
        $token = $user?->currentAccessToken();
        if (!$token) {
            return false;
        }

        // 1. Admin berhak penuh mengirim broadcast (global maupun ke kloter mana saja)
        if ($token->can('admin')) {
            return true;
        }

        // 2. Tour Leader: hanya boleh mengirim jika terotorisasi sebagai tour_leader
        if ($token->can('tour_leader') && $user instanceof TourLeader) {
            // Tour Leader DITOLAK jika mencoba mengirim siaran global (semua jamaah)
            if ($targetType === 'all') {
                return false;
            }

            // Target kloter wajib disertakan
            if (empty($kloterIds)) {
                return false;
            }

            // Periksa apakah semua kloter yang ditargetkan benar-benar ditugaskan kepada Tour Leader ini
            $assignedKloterIds = $user->kloters()->pluck('kloters.id')->map(fn($id) => (string) $id)->all();

            foreach ($kloterIds as $kloterId) {
                if (!in_array((string) $kloterId, $assignedKloterIds, true)) {
                    return false; // Ada kloter di luar penugasan resminya -> DITOLAK
                }
            }

            return true;
        }

        return false;
    }

    /**
     * Menentukan apakah aktor boleh melihat detail broadcast.
     */
    public function view($user, $broadcast): bool
    {
        $token = $user?->currentAccessToken();
        if (!$token) {
            return false;
        }

        if ($token->can('admin')) {
            return true;
        }

        if ($token->can('tour_leader') && $user instanceof TourLeader) {
            // Boleh melihat broadcast yang dibuat oleh dirinya sendiri
            if ($broadcast->sender_type === TourLeader::class && (string) $broadcast->sender_id === (string) $user->id) {
                return true;
            }

            // Atau jika siaran menargetkan kloter yang menjadi tanggung jawabnya
            $assignedKloterIds = $user->kloters()->pluck('kloters.id')->map(fn($id) => (string) $id)->all();
            $broadcastKloterIds = $broadcast->recipientKloters()->pluck('kloter_id')->map(fn($id) => (string) $id)->all();

            return !empty(array_intersect($assignedKloterIds, $broadcastKloterIds));
        }

        // Jamaah atau Family: verifikasi bahwa Jamaah (atau Jamaah yang terhubung dengan Family) adalah penerima resmi broadcast
        if ($token->can('jamaah') || $token->can('family')) {
            // Pada role family, jika token diterbitkan via FamilyAuthController, $user adalah instance Jamaah.
            // Jika role family, pastikan relasi keluarga jamaah tersebut aktif di family_relations
            if ($token->can('family')) {
                $hasActiveRelation = \App\Models\FamilyRelation::where('jamaah_id', $user->id)
                    ->where('status', 'active')
                    ->exists();

                if (!$hasActiveRelation) {
                    return false;
                }
            }

            return \App\Models\Notification::where('broadcast_id', $broadcast->id)
                ->where('recipient_type', \App\Models\Jamaah::class)
                ->where('recipient_id', $user->id)
                ->exists();
        }

        return false;
    }

    /**
     * Menentukan apakah aktor boleh memperbarui (mengedit catatan) broadcast.
     */
    public function update($user, $broadcast): bool
    {
        $token = $user?->currentAccessToken();
        if (!$token) {
            return false;
        }

        if ($token->can('admin')) {
            return true;
        }

        if ($token->can('tour_leader') && $user instanceof TourLeader) {
            // Hanya pembuat asli yang boleh memperbarui
            return $broadcast->sender_type === TourLeader::class && (string) $broadcast->sender_id === (string) $user->id;
        }

        return false;
    }
}
