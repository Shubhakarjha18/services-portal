<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Demo accounts. Every password is "password" and is documented in the README;
 * this seeder is intended for local and review environments only.
 */
class UserSeeder extends Seeder
{
    private const PASSWORD = 'password';

    public function run(): void
    {
        $roles = Role::all()->mapWithKeys(fn (Role $role) => [$role->name->value => $role->id]);
        $departments = Department::all()->keyBy('code');

        $accounts = [
            ['name' => 'Portal Administrator', 'email' => 'admin@portal.local', 'role' => RoleName::Admin, 'department' => 'HA', 'national_id' => '1000000001'],

            ['name' => 'Dana Officer', 'email' => 'officer@portal.local', 'role' => RoleName::Officer, 'department' => 'HA', 'national_id' => '2000000001'],
            ['name' => 'Milo Reyes', 'email' => 'milo.reyes@portal.local', 'role' => RoleName::Officer, 'department' => 'UP', 'national_id' => '2000000002'],
            ['name' => 'Aya Nur', 'email' => 'aya.nur@portal.local', 'role' => RoleName::Officer, 'department' => 'RT', 'national_id' => '2000000003'],
            ['name' => 'Sam Okafor', 'email' => 'sam.okafor@portal.local', 'role' => RoleName::Officer, 'department' => 'TR', 'national_id' => '2000000004'],

            ['name' => 'Rina Citoyen', 'email' => 'citizen@portal.local', 'role' => RoleName::Public, 'department' => null, 'national_id' => '3000000001'],
            ['name' => 'Tomas Alvarez', 'email' => 'tomas@portal.local', 'role' => RoleName::Public, 'department' => null, 'national_id' => '3000000002'],
            ['name' => 'Lea Fontaine', 'email' => 'lea@portal.local', 'role' => RoleName::Public, 'department' => null, 'national_id' => '3000000003'],
            ['name' => 'Noor Haddad', 'email' => 'noor@portal.local', 'role' => RoleName::Public, 'department' => null, 'national_id' => '3000000004'],
            ['name' => 'Priya Nair', 'email' => 'priya@portal.local', 'role' => RoleName::Public, 'department' => null, 'national_id' => '3000000005'],
        ];

        foreach ($accounts as $account) {
            User::updateOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'password' => Hash::make(self::PASSWORD),
                    'role_id' => $roles[$account['role']->value],
                    'department_id' => $account['department'] === null ? null : $departments[$account['department']]?->id,
                    'phone' => '+1 555 '.random_int(100000, 999999),
                    'national_id' => $account['national_id'],
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]
            );
        }
    }
}
