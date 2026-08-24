<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        Role::firstOrCreate(
            ['name' => 'Admin'],
            [
                'name' => 'Admin',
                'description' => 'Akses penuh ke seluruh sistem',
                'is_system_role' => true,
            ]
        );
    }
}
