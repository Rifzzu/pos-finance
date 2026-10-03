<?php

namespace Database\Seeders;

use App\Models\Outlet;
use App\Models\Product;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['id' => 1, 'code' => 'BDG', 'name' => 'Outlet Bandung', 'address' => 'Jl. Merdeka No. 1, Bandung'],
            ['id' => 2, 'code' => 'GRT', 'name' => 'Outlet Garut', 'address' => 'Jl. Ahmad Yani No. 10, Garut'],
            ['id' => 3, 'code' => 'SKB', 'name' => 'Outlet Sukabumi', 'address' => 'Jl. Suryakencana No. 5, Sukabumi'],
        ] as $o) {
            Outlet::updateOrCreate(['id' => $o['id']], $o + ['is_active' => true]);
        }

        // Harga S1 wajib: 50.000, 100.000, 200.000 + 1 nonaktif.
        foreach ([
            ['PRD-001', 'Kopi Tubruk 200g', '50000.00', true],
            ['PRD-002', 'Beras Premium 5kg', '100000.00', true],
            ['PRD-003', 'Minyak Goreng 5L', '200000.00', true],
            ['PRD-004', 'Teh Celup 50s', '25000.00', true],
            ['PRD-005', 'Gula Pasir 1kg', '18000.00', true],
            ['PRD-006', 'Telur Ayam 1kg', '30000.00', true],
            ['PRD-007', 'Susu UHT 1L', '22000.00', true],
            ['PRD-008', 'Roti Tawar', '15000.00', true],
            ['PRD-009', 'Mie Instan Goreng', '3500.00', true],
            ['PRD-010', 'Sabun Mandi', '12000.00', true],
            ['PRD-011', 'Shampo 340ml', '45000.00', true],
            ['PRD-012', 'Pasta Gigi 190g', '28000.00', true],
            ['PRD-013', 'Deterjen 800g', '24000.00', true],
            ['PRD-014', 'Tissue 250s', '16000.00', true],
            ['PRD-015', 'Air Mineral 600ml', '4000.00', true],
            ['PRD-099', 'Produk Arsip (nonaktif)', '99999.00', false],
        ] as [$sku, $name, $price, $active]) {
            Product::updateOrCreate(
                ['sku' => $sku],
                ['name' => $name, 'price' => $price, 'is_active' => $active]
            );
        }
    }
}
