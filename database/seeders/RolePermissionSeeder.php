<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            'admin' => 'Administrator / Owner',
            'store_manager' => 'Store Manager',
            'cashier' => 'Cashier / Staff',
            'site_manager' => 'Site Manager',
        ];

        foreach ($roles as $name => $label) {
            Role::findOrCreate($name);
        }

        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@hardware.local'],
            [
                'name' => 'System Administrator',
                'phone' => '0770000000',
                'password' => Hash::make('password'),
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        $admin->syncRoles(['admin']);

        $demoUsers = [
            [
                'name' => 'Store Manager',
                'email' => 'store@hardware.local',
                'phone' => '0770000001',
                'role' => 'store_manager',
            ],
            [
                'name' => 'Cashier',
                'email' => 'cashier@hardware.local',
                'phone' => '0770000002',
                'role' => 'cashier',
            ],
            [
                'name' => 'Site Manager',
                'email' => 'site@hardware.local',
                'phone' => '0770000003',
                'role' => 'site_manager',
            ],
        ];

        foreach ($demoUsers as $demoUser) {
            $user = User::query()->updateOrCreate(
                ['email' => $demoUser['email']],
                [
                    'name' => $demoUser['name'],
                    'phone' => $demoUser['phone'],
                    'password' => Hash::make('password'),
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]
            );

            $user->syncRoles([$demoUser['role']]);
        }
    }
}
