<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'name' => 'Superadmin',
                'description' => 'System Administrator with full management and reporting access',
            ],
            [
                'name' => 'Admin',
                'description' => 'Tour Operator with dispatch and QR scanner access',
            ],
            [
                'name' => 'User',
                'description' => 'Registered Tourist and Guest Customer',
            ],
        ];

        foreach ($roles as $roleData) {
            Role::updateOrCreate(
                ['name' => $roleData['name']],
                ['description' => $roleData['description']]
            );
        }
    }
}