<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class RoomMember extends Model
{
    use HasUuids;

    protected $table = 'room_members';

    protected $fillable = [
        'room_id',
        'registration_id',
        'title',
        'occupant_name',
        'age',
    ];

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function registration()
    {
        return $this->belongsTo(Registration::class);
    }
}
