<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $superadminRole = Role::where('name', 'Superadmin')->first();
        $adminRole = Role::where('name', 'Admin')->first();
        $userRole = Role::where('name', 'User')->first();

        // 1. Superadmin Account
        User::updateOrCreate(
            ['email' => 'admin@gmail.com'],
            [
                'name' => 'Superadmin User',
                'password' => Hash::make('password'),
                'role_id' => $superadminRole?->id,
                'is_protected' => true,
                'email_verified_at' => now(),
            ]
        );

        // 2. Admin (tour operator) account
        User::updateOrCreate(
            ['email' => 'operator@gmail.com'],
            [
                'name' => 'Admin',
                'password' => Hash::make('password'),
                'role_id' => $adminRole?->id,
                'is_protected' => false,
                'email_verified_at' => now(),
            ]
        );

        // 3. Primary Tourist Account
        User::updateOrCreate(
            ['email' => 'tourist@gmail.com'],
            [
                'name' => 'Juan dela Cruz',
                'password' => Hash::make('password'),
                'role_id' => $userRole?->id,
                'is_protected' => false,
                'email_verified_at' => now(),
            ]
        );

        // 4. Additional Realistic Tourist Accounts
        $additionalTourists = [
            [
                'name' => 'Maria Santos',
                'email' => 'maria.santos@example.com',
            ],
            [
                'name' => 'Sarah Jenkins',
                'email' => 'sarah.jenkins@example.com',
            ],
            [
                'name' => 'Liam Smith',
                'email' => 'liam.smith@example.com',
            ],
            [
                'name' => 'Kenji Sato',
                'email' => 'kenji.sato@example.com',
            ],
            [
                'name' => 'Hans Weber',
                'email' => 'hans.weber@example.com',
            ],
            [
                'name' => 'Claire Dubois',
                'email' => 'claire.dubois@example.com',
            ],
            [
                'name' => 'Alex Rivera',
                'email' => 'alex.rivera@example.com',
            ],
            [
                'name' => 'David Miller',
                'email' => 'david.miller@example.com',
            ],
        ];

        foreach ($additionalTourists as $tourist) {
            User::updateOrCreate(
                ['email' => $tourist['email']],
                [
                    'name' => $tourist['name'],
                    'password' => Hash::make('password'),
                    'role_id' => $userRole?->id,
                    'is_protected' => false,
                    'email_verified_at' => now(),
                ]
            );
        }
    }
}