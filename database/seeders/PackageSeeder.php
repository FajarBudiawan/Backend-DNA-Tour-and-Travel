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
            'name' => 'YAMANI - 9 Hari',
            'category' => 'Yamani',
            'status' => 'published',
            'is_featured' => false,
            'published_at' => now(),
            'created_by' => $admin?->id,
        ],
        [
            'name' => 'YAMANI - 12 Hari',
            'category' => 'Yamani',
            'status' => 'published',
            'is_featured' => false,
            'published_at' => now(),
            'created_by' => $admin?->id,
        ],
        [
            'name' => 'RAUDHAH - 9 Hari',
            'category' => 'Raudhah',
            'status' => 'published',
            'is_featured' => false,
            'published_at' => now(),
            'created_by' => $admin?->id,
        ],
        [
            'name' => 'RAUDHAH - 12 Hari',
            'category' => 'Raudhah',
            'status' => 'published',
            'is_featured' => false,
            'published_at' => now(),
            'created_by' => $admin?->id,
        ],
        [
            'name' => 'MULTAZAM - 9 Hari',
            'category' => 'Multazam',
            'status' => 'published',
            'is_featured' => false,
            'published_at' => now(),
            'created_by' => $admin?->id,
        ],
        [
            'name' => 'MULTAZAM - 12 Hari',
            'category' => 'Multazam',
            'status' => 'published',
            'is_featured' => false,
            'published_at' => now(),
            'created_by' => $admin?->id,
        ],
        [
            'name' => 'PLUS TURKEY - 12 Hari',
            'category' => 'Plus',
            'status' => 'published',
            'is_featured' => false,
            'published_at' => now(),
            'created_by' => $admin?->id,
        ],
        [
            'name' => 'PLUS MESIR - 12 Hari',
            'category' => 'Plus',
            'status' => 'published',
            'is_featured' => false,
            'published_at' => now(),
            'created_by' => $admin?->id,
        ],
        [
            'name' => 'PLUS DUBAI - 12 Hari',
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
