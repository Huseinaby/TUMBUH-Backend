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
            'username' => 'penjual2',
            'email' => 'penjual1@tumbuh.app',
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

        $productProvince = [
            'Jawa Barat',
            'Jawa Tengah',
            'Jawa Timur',
            'Bali',
            'NTB',
            'NTT',
            'Sumatera Utara',
            'Sumatera Selatan',
            'Kalimantan Barat',
            'Kalimantan Timur',
        ];

        foreach ($productProvince as $province) {
            Province::create([
                'name' => $province,
            ]);
        }

        $products = [
            [
                'user_id' => 1,
                'province_id' => 1,
                'product_category_id' => 1,
                'name' => 'Wijaya Kusuma',
                'description' => 'Tanaman Wijaya Kusuma',
                'price' => 100000,
                'stock' => 10,
            ],
            [
                'user_id' => 2,
                'province_id' => 8,
                'product_category_id' => 6,
                'name' => 'Cangkul',
                'description' => 'Cangkul untuk berkebun',
                'price' => 50000,
            ],
        ];

        foreach ($products as $product) {
            Product::create($product);
        }


    }
}
