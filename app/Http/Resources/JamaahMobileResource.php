<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;


class JamaahMobileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $kloter = $this->kloter;

        $primaryTourLeader = $kloter?->tourLeaders
            ?->sortBy([
                ['pivot.assigned_at', 'asc'],
                ['id', 'asc'],
            ])
            ->first();

        $primaryMutawif = $kloter?->mutawifs
            ?->sortBy([
                ['pivot.assigned_at', 'asc'],
                ['id', 'asc'],
            ])
            ->first();

        return [
            'id' => $this->id,
            'login_id' => $this->login_id,
            'full_name' => $this->full_name,
            'phone' => $this->phone,

            'profile' => [
                'birth_date' => $this->birth_date?->format('Y-m-d'),
                'gender' => $this->gender,
                'nationality' => $this->nationality,
                'passport_number' => $this->passport_number,
                'visa_number' => $this->visa_number,
            ],

            'journey' => [
                'package' => $this->package ? [
                    'id' => $this->package->id,
                    'name' => $this->package->name,
                    'category' => $this->package->category,
                ] : null,

                'kloter' => $kloter ? [
                    'id' => $kloter->id,
                    'code' => $kloter->code,
                    'departure_date' => $kloter->departure_date?->format('Y-m-d'),
                    'return_date' => $kloter->return_date?->format('Y-m-d'),
                    'status' => $kloter->status,
                ] : null,

                'hotel' => [
                    'makkah' => $this->hotel_makkah
                        ?: $kloter?->hotelMakkah?->name,

                    'madinah' => $this->hotel_madinah
                        ?: $kloter?->hotelMadinah?->name,
                ],

                'tour_leader' => $primaryTourLeader ? [
                    'id' => $primaryTourLeader->id,
                    'full_name' => $primaryTourLeader->full_name,
                    'phone' => $primaryTourLeader->phone,
                ] : null,

                'mutawif' => $primaryMutawif ? [
                    'id' => $primaryMutawif->id,
                    'name' => $primaryMutawif->name,
                    'language' => $primaryMutawif->language,
                ] : null,
            ],
        ];
    }
}