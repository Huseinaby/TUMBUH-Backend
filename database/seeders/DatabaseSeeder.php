<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\productCategories;
use App\Models\Province;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();
        User::create([
            'username' => 'penjual1',
            'email' => 'penjual@tumbuh.app',
            'role' => 'seller',
            'password' => bcrypt('password'),
        ]);

        User::create([
            'username' => 'Abu Husein',
            'email' => 'husein@tumbuh-app.my.id',
            'role' => 'admin',
            'email_verified_at' => now(),
            'password' => bcrypt('password'),
        ]);

        $productCategories = [
            'tanaman',
            'benih',
            'pupuk',
            'buah',
            'sayur',
            'peralatan',
        ];

        foreach ($productCategories as $category) {
            productCategories::create([
                'name' => $category,
            ]);
        }
    }
}
