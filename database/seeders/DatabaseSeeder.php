<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@kios.test'],
            ['name' => 'Admin', 'role' => 'admin', 'password' => Hash::make('password')],
        );

        User::updateOrCreate(
            ['email' => 'kasir@kios.test'],
            ['name' => 'Kasir', 'role' => 'kasir', 'password' => Hash::make('password')],
        );

        $catalog = [
            'Minuman' => [
                ['Aqua Botol 600ml', '8991002101011', 3500, 2800, 40],
                ['Teh Botol Sosro 350ml', '8991002101028', 4500, 3600, 30],
                ['Kopi Kapal Api Sachet', '8991002101035', 1500, 1100, 60],
            ],
            'Makanan' => [
                ['Indomie Goreng', '8991002102018', 3500, 2900, 50],
                ['Chitato 68g', '8991002102025', 11000, 9000, 20],
                ['Roti Tawar Sari Roti', '8991002102032', 16000, 13500, 12],
            ],
            'Rokok' => [
                ['Sampoerna Mild 16', '8991002103015', 32000, 29500, 25],
                ['Gudang Garam Surya 12', '8991002103022', 27000, 24500, 25],
            ],
            'Kebutuhan Rumah' => [
                ['Sabun Lifebuoy 85g', '8991002104012', 4000, 3200, 18],
                ['Rinso Sachet 44g', '8991002104029', 2000, 1500, 35],
                ['Gas LPG 3kg (isi ulang)', null, 22000, 19000, 8],
            ],
        ];

        foreach ([
            ['Bu Sari', '081200000001', 200000],
            ['Pak Joko', '081200000002', null],
            ['Warung Mbak Tini', '081200000003', 500000],
        ] as [$name, $phone, $limit]) {
            Customer::updateOrCreate(
                ['name' => $name],
                ['phone' => $phone, 'credit_limit' => $limit],
            );
        }

        foreach ($catalog as $catName => $items) {
            $category = Category::firstOrCreate(['name' => $catName]);

            foreach ($items as [$name, $barcode, $price, $cost, $stock]) {
                Product::updateOrCreate(
                    ['name' => $name],
                    [
                        'category_id' => $category->id,
                        'barcode' => $barcode,
                        'price' => $price,
                        'cost' => $cost,
                        'stock' => $stock,
                        'low_stock' => 10,
                        'is_active' => true,
                    ],
                );
            }
        }
    }
}
