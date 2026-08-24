<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Laravel\Sanctum\HasApiTokens;

class InternalUser extends Authenticatable
{
    use HasApiTokens, HasUuids;

    protected $table = 'internal_users';

    protected $fillable = [
        'role_id',
        'full_name',
        'email',
        'password_hash',
        'phone',
        'status',
        'created_by'
    ];

    protected $hidden = ['password_hash'];

    public function getAuthPassword()
    {
        return $this->password_hash;
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function registrations()
    {
        return $this->hasMany(Registration::class, 'created_by');
    }
}