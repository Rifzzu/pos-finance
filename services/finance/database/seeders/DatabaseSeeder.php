<?php

namespace Database\Seeders;

use App\Models\Account;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['1101', 'Kas'],
            ['1102', 'Bank - Kartu Debit'],
            ['1103', 'Piutang QRIS'],
            ['2101', 'Utang PPN'],
            ['4101', 'Penjualan'],
        ] as [$code, $name]) {
            Account::updateOrCreate(['code' => $code], ['name' => $name]);
        }
    }
}
