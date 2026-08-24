<?php

namespace Database\Seeders;

use App\Models\InternalUser;
use App\Models\Package;
use Illuminate\Database\Seeder;

class PackageSeeder extends Seeder
{
    public function run(): void
    {
        $admin = InternalUser::first();

        $packages = [
            [
                'name' => 'Paket Umrah Reguler 9 Hari',
                'category' => 'Reguler',
                'status' => 'published',
                'is_featured' => true,
                'published_at' => now(),
                'created_by' => $admin?->id,
            ],
            [
                'name' => 'Paket Umrah VIP 12 Hari',
                'category' => 'VIP',
                'status' => 'published',
                'is_featured' => true,
                'published_at' => now(),
                'created_by' => $admin?->id,
            ],
            [
                'name' => 'Paket Umrah Plus Turki 14 Hari',
                'category' => 'Plus',
                'status' => 'published',
                'is_featured' => false,
                'published_at' => now(),
                'created_by' => $admin?->id,
            ],
        ];

        foreach ($packages as $pkg) {
            Package::firstOrCreate(['name' => $pkg['name']], $pkg);
        }
    }
}
