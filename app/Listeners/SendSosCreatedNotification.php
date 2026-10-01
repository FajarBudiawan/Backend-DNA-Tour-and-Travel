<?php

namespace App\Listeners;

use App\Events\SosCreated;
use App\Jobs\ProcessSosNotificationJob;
use App\Models\InternalUser;
use App\Models\Notification;
use App\Models\TourLeader;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendSosCreatedNotification implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Timeout untuk listener execution
     */
    public $timeout = 30;

    /**
     * Jumlah retry jika gagal
     */
    public $tries = 3;

    /**
     * Handle the event.
     */
    public function handle(SosCreated $event): void
    {
        try {
            // Refresh incident dengan relationships
            $incident = $event->incident->fresh(['jamaah']);  // ✅ TAMBAH INI
            
            // 1. Ambil semua Admin
            $admins = InternalUser::all();

            // 2. Ambil semua Tour Leader
            $tourLeaders = TourLeader::whereHas('kloters', function ($query) use ($incident) {
                $query->where('kloter_id', $incident->kloter_id);
            })->get();

            Log::info('Notification recipients identified', [
                'admins_count' => $admins->count(),
                'tour_leaders_count' => $tourLeaders->count(),
            ]);

            // 3. Create notifications untuk admin
            foreach ($admins as $admin) {
                try {
                    $jamaahName = $incident->jamaah?->nama ?? 'Unknown';
                    Notification::create([
                        'sos_incident_id' => $incident->id,
                        'recipient_type' => InternalUser::class,
                        'recipient_id' => $admin->id,
                        'type' => 'sos_created',
                        'title' => 'Laporan Darurat Baru',
                        'message' => "SOS baru dari {$jamaahName}",  // ✅ ADD NULL CHECK
                        'delivery_status' => 'pending',
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to create notification for admin', [
                        'admin_id' => $admin->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // 4. Create notifications untuk tour leader
            foreach ($tourLeaders as $tourLeader) {
                try {
                    Notification::create([
                        'sos_incident_id' => $incident->id,
                        'recipient_type' => TourLeader::class,
                        'recipient_id' => $tourLeader->id,
                        'type' => 'sos_created',
                        'title' => 'Laporan Darurat dari Jamaah',
                        'message' => "{$jamaahName} melaporkan darurat di {$incident->location_name}",  // ✅ ADD NULL CHECK
                        'delivery_status' => 'pending',
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to create notification for tour leader', [
                        'tour_leader_id' => $tourLeader->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // 5. Dispatch job
            dispatch(new ProcessSosNotificationJob($incident));

            Log::info('SOS created notifications queued', [
                'incident_id' => $incident->id,
                'admins_notified' => $admins->count(),
                'tour_leaders_notified' => $tourLeaders->count(),
            ]);
        } catch (\Exception $e) {
            Log::error('SendSosCreatedNotification failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),  // ✅ Log full trace
            ]);
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(SosCreated $event, \Throwable $exception): void
    {
        Log::critical('SendSosCreatedNotification listener failed', [
            'incident_id' => $event->incident->id,
            'exception' => $exception->getMessage(),
        ]);
        // Alert admin atau kirim email
    }
}