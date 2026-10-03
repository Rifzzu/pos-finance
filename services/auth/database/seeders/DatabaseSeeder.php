<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $password = Hash::make(env('DEMO_PASSWORD', 'Password123!'));

        $users = [
            ['Super Admin', 'superadmin@demo.test', 'superadmin', null],
            ['Kasir BDG', 'kasir.bdg@demo.test', 'kasir', 1],
            ['Supervisor BDG', 'supervisor.bdg@demo.test', 'supervisor_pos', 1],
            ['Staff Finance', 'staff.finance@demo.test', 'staff_finance', null],
            ['Manager Finance', 'manager.finance@demo.test', 'manager_finance', null],
            ['Kasir GRT', 'kasir.grt@demo.test', 'kasir', 2],
        ];

        foreach ($users as [$name, $email, $role, $outletId]) {
            $user = User::firstOrNew(['email' => $email]);
            $isNew = ! $user->exists;
            $user->fill(['name' => $name, 'role' => $role, 'outlet_id' => $outletId, 'is_active' => true]);
            if ($isNew) {
                $user->password = $password;
            }
            $user->save();
        }
    }
}
