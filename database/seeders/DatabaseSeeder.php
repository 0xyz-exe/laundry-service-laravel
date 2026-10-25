<?php

namespace Database\Seeders;

use App\Models\LaundryService;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['name' => 'Wash & Fold', 'unit' => 'kg', 'price_minor' => 800000, 'description' => 'Wash, dry and fold, per kilogram.'],
            ['name' => 'Express Wash', 'unit' => 'kg', 'price_minor' => 1200000, 'description' => 'Priority wash and fold, per kilogram.'],
            ['name' => 'Dry Cleaning', 'unit' => 'item', 'price_minor' => 2500000, 'description' => 'Garment dry cleaning, per item.'],
            ['name' => 'Bedding Service', 'unit' => 'item', 'price_minor' => 4000000, 'description' => 'Large bedding item cleaning, per item.'],
        ] as $service) {
            LaundryService::firstOrCreate(['name' => $service['name']], $service + ['active' => true]);
        }
    }
}
