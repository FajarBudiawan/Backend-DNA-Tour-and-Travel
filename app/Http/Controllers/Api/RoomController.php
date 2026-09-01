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
     * Menampilkan semua kamar dan anggota (title, occupant_name, age) dalam satu kloter.
     */
    public function index(Kloter $kloter): JsonResponse
    {
        $rooms = Room::where('kloter_id', $kloter->id)
            ->with(['hotel', 'members'])
            ->get();

        return response()->json([
            'message' => 'Data pembagian kamar kloter berhasil diambil.',
            'kloter'  => $kloter->load('package'),
            'data'    => [
                'rooms' => $rooms,
            ],
        ]);
    }

    /**
     * Pembagian Kamar Otomatis (Auto Allocation Roommates).
     *
     * // DEPRECATED sementara: endpoint ini bergantung pada registration_id dari tabel registrations.
     * // Sejak migrasi manual-occupant (2026_08_31_000001), cara mencatat penghuni kamar telah berubah
     * // menjadi input manual (title, occupant_name, age). Jangan hapus kode ini — tunggu keputusan
     * // desain ulang sebelum diaktifkan kembali.
     */
    public function autoAssign(Request $request, Kloter $kloter): JsonResponse
    {
        $request->validate([
            'room_type' => 'nullable|in:quad,triple,double,single,quint',
            'hotel_id'  => 'nullable|uuid|exists:hotels,id',
        ]);

        $roomType = $request->room_type ?? 'quad';
        $capacityMap = [
            'quad'   => 4,
            'triple' => 3,
            'double' => 2,
            'single' => 1,
            'quint'  => 5,
        ];
        $capacity = $capacityMap[$roomType] ?? 4;

        $createdRooms = DB::transaction(function () use ($kloter, $roomType, $capacity, $request) {
            $assignedRegistrationIds = RoomMember::whereHas('room', function ($q) use ($kloter) {
                $q->where('kloter_id', $kloter->id);
            })->whereNotNull('registration_id')->pluck('registration_id');

            $unassigned = Registration::where('kloter_id', $kloter->id)
                ->whereNotIn('id', $assignedRegistrationIds)
                ->get();

            if ($unassigned->isEmpty()) {
                return [];
            }

            $males   = $unassigned->where('gender', 'L');
            $females = $unassigned->where('gender', 'P');
            $newRooms = [];

            // Laki-laki
            if ($males->isNotEmpty()) {
                $maleChunks = $males->chunk($capacity);
                $roomCount  = Room::where('kloter_id', $kloter->id)->where('gender', 'L')->count() + 1;

                foreach ($maleChunks as $chunk) {
                    $roomNumber = 'RM-L-' . str_pad($roomCount++, 2, '0', STR_PAD_LEFT);
                    $room = Room::create([
                        'kloter_id'   => $kloter->id,
                        'hotel_id'    => $request->hotel_id,
                        'room_number' => $roomNumber,
                        'room_type'   => $roomType,
                        'capacity'    => $capacity,
                        'gender'      => 'L',
                        'notes'       => 'Pembagian otomatis',
                    ]);

                    foreach ($chunk as $registration) {
                        RoomMember::create([
                            'room_id'         => $room->id,
                            'registration_id' => $registration->id,
                        ]);
                    }

                    $newRooms[] = $room->load('members');
                }
            }

            // Perempuan
            if ($females->isNotEmpty()) {
                $femaleChunks = $females->chunk($capacity);
                $roomCount    = Room::where('kloter_id', $kloter->id)->where('gender', 'P')->count() + 1;

                foreach ($femaleChunks as $chunk) {
                    $roomNumber = 'RM-P-' . str_pad($roomCount++, 2, '0', STR_PAD_LEFT);
                    $room = Room::create([
                        'kloter_id'   => $kloter->id,
                        'hotel_id'    => $request->hotel_id,
                        'room_number' => $roomNumber,
                        'room_type'   => $roomType,
                        'capacity'    => $capacity,
                        'gender'      => 'P',
                        'notes'       => 'Pembagian otomatis',
                    ]);

                    foreach ($chunk as $registration) {
                        RoomMember::create([
                            'room_id'         => $room->id,
                            'registration_id' => $registration->id,
                        ]);
                    }

                    $newRooms[] = $room->load('members');
                }
            }

            return $newRooms;
        });

        return response()->json([
            'message' => 'Pembagian kamar otomatis berhasil dilakukan.',
            'data'    => $createdRooms,
        ], 201);
    }

    /**
     * Membuat kamar secara manual oleh Admin.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'kloter_id'   => 'required|uuid|exists:kloters,id',
            'hotel_id'    => 'nullable|uuid|exists:hotels,id',
            'room_number' => 'nullable|string|max:30',
            'room_type'   => 'required|in:quad,triple,double,single,quint',
            'capacity'    => 'nullable|integer|min:1',
            'gender'      => 'required|in:L,P',
            'notes'       => 'nullable|string|max:255',
        ]);

        if (empty($validated['room_number'])) {
            $roomCount = Room::where('kloter_id', $validated['kloter_id'])->count() + 1;
            $validated['room_number'] = 'TBD-' . str_pad($roomCount, 2, '0', STR_PAD_LEFT);
        }

        $capacityMap = ['quad' => 4, 'triple' => 3, 'double' => 2, 'single' => 1, 'quint' => 5];
        if (!isset($validated['capacity'])) {
            $validated['capacity'] = $capacityMap[$validated['room_type']] ?? 4;
        }

        $room = Room::create($validated);

        return response()->json([
            'message' => 'Kamar berhasil dibuat secara manual.',
            'data'    => $room->load(['hotel', 'members']),
        ], 201);
    }

    /**
     * Perbarui kamar secara manual.
     */
    public function update(Request $request, Room $room): JsonResponse
    {
        $validated = $request->validate([
            'hotel_id'    => 'nullable|uuid|exists:hotels,id',
            'room_number' => 'nullable|string|max:30',
            'room_type'   => 'sometimes|in:quad,triple,double,single,quint',
            'capacity'    => 'sometimes|integer|min:1',
            'gender'      => 'sometimes|in:L,P',
            'notes'       => 'nullable|string|max:255',
        ]);

        $room->update($validated);

        return response()->json([
            'message' => 'Data kamar berhasil diperbarui.',
            'data'    => $room->load(['hotel', 'members']),
        ]);
    }

    /**
     * Hapus kamar (semua room_members ikut terhapus via cascade).
     */
    public function destroy(Room $room): JsonResponse
    {
        $room->delete();

        return response()->json([
            'message' => 'Kamar berhasil dihapus.',
        ]);
    }

    /**
     * Menambahkan penghuni ke kamar secara manual (title, occupant_name, age).
     * Gender divalidasi dari title: MR/MSTR → L, MRS/MISS → P.
     */
    public function addMember(Request $request, Room $room): JsonResponse
    {
        $request->validate([
            'title'         => 'required|in:MR,MRS,MISS,MSTR',
            'occupant_name' => 'required|string|max:255',
            'age'           => 'nullable|integer|min:0|max:120',
        ]);

        // 1. Cek kapasitas kamar
        if ($room->is_full) {
            return response()->json([
                'message' => 'Kamar sudah penuh (kapasitas maksimal: ' . $room->capacity . ' orang).',
            ], 422);
        }

        // 2. Derive gender dari title dan cocokkan dengan gender kamar
        $titleGenderMap = [
            'MR'   => 'L',
            'MSTR' => 'L',
            'MRS'  => 'P',
            'MISS' => 'P',
        ];
        $derivedGender = $titleGenderMap[$request->title];
        if ($derivedGender !== $room->gender) {
            return response()->json([
                'message' => 'Gender penghuni (' . $request->title . ' → ' . $derivedGender . ') tidak cocok dengan gender kamar (' . $room->gender . ').',
            ], 422);
        }

        // 3. Simpan penghuni baru
        RoomMember::create([
            'room_id'       => $room->id,
            'title'         => $request->title,
            'occupant_name' => $request->occupant_name,
            'age'           => $request->age,
        ]);

        // Refresh room setelah penambahan
        $room->refresh();

        return response()->json([
            'message' => 'Penghuni berhasil ditambahkan ke kamar.',
            'data'    => $room->load(['hotel', 'members']),
        ], 201);
    }

    /**
     * Menghapus penghuni dari kamar berdasarkan ID room_member.
     */
    public function removeMember(Room $room, RoomMember $roomMember): JsonResponse
    {
        if ($roomMember->room_id !== $room->id) {
            return response()->json([
                'message' => 'Penghuni tidak berada di kamar tersebut.',
            ], 422);
        }

        $roomMember->delete();
        $room->refresh();

        return response()->json([
            'message' => 'Penghuni berhasil dikeluarkan dari kamar.',
            'data'    => $room->load(['hotel', 'members']),
        ]);
    }
}
