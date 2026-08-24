<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Kloter;
use App\Models\Registration;
use App\Models\Room;
use App\Models\RoomMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RoomController extends Controller
{
    /**
     * Menampilkan semua kamar dan status pembagian roommate dalam satu kloter.
     */
    public function index(Kloter $kloter): JsonResponse
    {
        $rooms = Room::where('kloter_id', $kloter->id)
            ->with(['hotel', 'registrations'])
            ->get();

        // Cari pendaftaran dalam kloter ini yang belum mendapatkan kamar
        $assignedRegistrationIds = RoomMember::whereIn(
            'room_id',
            $rooms->pluck('id')
        )->pluck('registration_id');

        $unassignedRegistrations = Registration::where('kloter_id', $kloter->id)
            ->whereNotIn('id', $assignedRegistrationIds)
            ->get();

        return response()->json([
            'message' => 'Data pembagian kamar kloter berhasil diambil.',
            'kloter' => $kloter->load('package'),
            'data' => [
                'rooms' => $rooms,
                'unassigned_registrations' => $unassignedRegistrations,
            ],
        ]);
    }

    /**
     * Pembagian Kamar Otomatis (Auto Allocation Roommates).
     */
    public function autoAssign(Request $request, Kloter $kloter): JsonResponse
    {
        $request->validate([
            'room_type' => 'nullable|in:quad,triple,double,single',
            'hotel_id' => 'nullable|uuid|exists:hotels,id',
        ]);

        $roomType = $request->room_type ?? 'quad';
        $capacityMap = [
            'quad' => 4,
            'triple' => 3,
            'double' => 2,
            'single' => 1,
        ];
        $capacity = $capacityMap[$roomType] ?? 4;

        $createdRooms = DB::transaction(function () use ($kloter, $roomType, $capacity, $request) {
            // Ambil pendaftaran dalam kloter ini yang belum dapat kamar
            $assignedRegistrationIds = RoomMember::whereHas('room', function ($q) use ($kloter) {
                $q->where('kloter_id', $kloter->id);
            })->pluck('registration_id');

            $unassigned = Registration::where('kloter_id', $kloter->id)
                ->whereNotIn('id', $assignedRegistrationIds)
                ->get();

            if ($unassigned->isEmpty()) {
                return [];
            }

            // Pisahkan berdasarkan jenis kelamin
            $males = $unassigned->where('gender', 'L');
            $females = $unassigned->where('gender', 'P');

            $newRooms = [];

            // 1. Pembagian Kamar Laki-laki
            if ($males->isNotEmpty()) {
                $maleChunks = $males->chunk($capacity);
                $roomCount = Room::where('kloter_id', $kloter->id)->where('gender', 'L')->count() + 1;

                foreach ($maleChunks as $chunk) {
                    $roomNumber = 'RM-L-' . str_pad($roomCount++, 2, '0', STR_PAD_LEFT);
                    $room = Room::create([
                        'kloter_id' => $kloter->id,
                        'hotel_id' => $request->hotel_id,
                        'room_number' => $roomNumber,
                        'room_type' => $roomType,
                        'capacity' => $capacity,
                        'gender' => 'L',
                        'notes' => 'Pembagian otomatis',
                    ]);

                    foreach ($chunk as $registration) {
                        RoomMember::create([
                            'room_id' => $room->id,
                            'registration_id' => $registration->id,
                        ]);
                    }

                    $newRooms[] = $room->load('registrations');
                }
            }

            // 2. Pembagian Kamar Perempuan
            if ($females->isNotEmpty()) {
                $femaleChunks = $females->chunk($capacity);
                $roomCount = Room::where('kloter_id', $kloter->id)->where('gender', 'P')->count() + 1;

                foreach ($femaleChunks as $chunk) {
                    $roomNumber = 'RM-P-' . str_pad($roomCount++, 2, '0', STR_PAD_LEFT);
                    $room = Room::create([
                        'kloter_id' => $kloter->id,
                        'hotel_id' => $request->hotel_id,
                        'room_number' => $roomNumber,
                        'room_type' => $roomType,
                        'capacity' => $capacity,
                        'gender' => 'P',
                        'notes' => 'Pembagian otomatis',
                    ]);

                    foreach ($chunk as $registration) {
                        RoomMember::create([
                            'room_id' => $room->id,
                            'registration_id' => $registration->id,
                        ]);
                    }

                    $newRooms[] = $room->load('registrations');
                }
            }

            return $newRooms;
        });

        return response()->json([
            'message' => 'Pembagian kamar otomatis berhasil dilakukan.',
            'data' => $createdRooms,
        ], 201);
    }

    /**
     * Membuat kamar secara manual oleh Admin (Manual Create Room).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'kloter_id' => 'required|uuid|exists:kloters,id',
            'hotel_id' => 'nullable|uuid|exists:hotels,id',
            'room_number' => 'required|string|max:30',
            'room_type' => 'required|in:quad,triple,double,single',
            'capacity' => 'nullable|integer|min:1',
            'gender' => 'required|in:L,P',
            'notes' => 'nullable|string|max:255',
        ]);

        $capacityMap = ['quad' => 4, 'triple' => 3, 'double' => 2, 'single' => 1];
        if (!isset($validated['capacity'])) {
            $validated['capacity'] = $capacityMap[$validated['room_type']] ?? 4;
        }

        $room = Room::create($validated);

        return response()->json([
            'message' => 'Kamar berhasil dibuat secara manual.',
            'data' => $room->load(['hotel', 'registrations']),
        ], 201);
    }

    /**
     * Perbarui kamar secara manual (Manual Edit Room).
     */
    public function update(Request $request, Room $room): JsonResponse
    {
        $validated = $request->validate([
            'hotel_id' => 'nullable|uuid|exists:hotels,id',
            'room_number' => 'sometimes|string|max:30',
            'room_type' => 'sometimes|in:quad,triple,double,single',
            'capacity' => 'sometimes|integer|min:1',
            'gender' => 'sometimes|in:L,P',
            'notes' => 'nullable|string|max:255',
        ]);

        $room->update($validated);

        return response()->json([
            'message' => 'Data kamar berhasil diperbarui.',
            'data' => $room->load(['hotel', 'registrations']),
        ]);
    }

    /**
     * Hapus kamar (Manual Delete Room).
     */
    public function destroy(Room $room): JsonResponse
    {
        $room->delete();

        return response()->json([
            'message' => 'Kamar berhasil dihapus.',
        ]);
    }

    /**
     * Menambahkan anggota/roommate ke dalam kamar secara manual.
     */
    public function addMember(Request $request, Room $room): JsonResponse
    {
        $request->validate([
            'registration_id' => 'required|uuid|exists:registrations,id',
        ]);

        $registration = Registration::findOrFail($request->registration_id);

        // 1. Cek kapasitas kamar
        if ($room->is_full) {
            return response()->json([
                'message' => 'Kamar sudah penuh (kapasitas maksimal: ' . $room->capacity . ' orang).',
            ], 422);
        }

        // 2. Cek kesesuaian jenis kelamin
        if ($registration->gender !== $room->gender) {
            return response()->json([
                'message' => 'Jenis kelamin jamaah (' . $registration->gender . ') tidak sesuai dengan jenis kelamin kamar (' . $room->gender . ').',
            ], 422);
        }

        // 3. Lepas dari kamar lama jika jamaah sudah ada di kamar lain pada kloter yang sama
        $existingMember = RoomMember::where('registration_id', $registration->id)
            ->whereHas('room', function ($q) use ($room) {
                $q->where('kloter_id', $room->kloter_id);
            })->first();

        if ($existingMember) {
            $existingMember->delete();
        }

        // 4. Masukkan ke kamar baru
        $member = RoomMember::create([
            'room_id' => $room->id,
            'registration_id' => $registration->id,
        ]);

        return response()->json([
            'message' => 'Jamaah berhasil dimasukkan ke dalam kamar.',
            'data' => $room->load(['hotel', 'registrations']),
        ]);
    }

    /**
     * Menghapus anggota/roommate dari kamar secara manual.
     */
    public function removeMember(Room $room, Registration $registration): JsonResponse
    {
        RoomMember::where('room_id', $room->id)
            ->where('registration_id', $registration->id)
            ->delete();

        return response()->json([
            'message' => 'Jamaah berhasil dikeluarkan dari kamar.',
            'data' => $room->load(['hotel', 'registrations']),
        ]);
    }
}
