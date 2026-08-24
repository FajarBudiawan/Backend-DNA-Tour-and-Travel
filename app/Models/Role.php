<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Role extends Model
{
    use HasUuids;

    protected $table = 'roles';
    protected $fillable = ['name', 'description', 'is_system_role'];
    public $timestamps = false;
}
