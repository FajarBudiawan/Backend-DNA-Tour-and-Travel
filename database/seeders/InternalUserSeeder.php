<?php

namespace Database\Seeders;

use App\Models\InternalUser;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class InternalUserSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole = Role::where('name', 'Admin')->first() ?? Role::first();

        if ($adminRole) {
            InternalUser::firstOrCreate(
                ['email' => 'admin@example.com'],
                [
                    'role_id' => $adminRole->id,
                    'full_name' => 'Super Admin Travel',
                    'email' => 'admin@example.com',
                    'password_hash' => Hash::make('password123'),
                    'phone' => '081234567890',
                    'status' => 'active',
                ]
            );
        }
    }
}
