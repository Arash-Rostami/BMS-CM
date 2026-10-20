<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([PermissionSeeder::class, PurchaseRequestStatusSeeder::class]);

        // 2. Define Roles
        $roles = [
            // AGENT
            'agent_junior', 'agent_mid', 'agent_senior',
            // ACCOUNTANT
            'accountant_junior', 'accountant_mid', 'accountant_senior',
            // MANAGER
            'manager_junior', 'manager_mid', 'manager_senior',
            // PARTNER
            'partner_junior', 'partner_mid', 'partner_senior',
            // ADMIN
            'admin_junior', 'admin_mid', 'admin_senior',
        ];

        foreach ($roles as $roleName) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        }

        // 3. Create a test user and assign a role
        $user = User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => bcrypt('password'), // Ensure you set a password
                'status' => 'active',
                // 'role' column is deprecated but if you still have it in DB, you might want to fill it or ignore it.
            ]
        );

        // Assign a role to the user
        $user->assignRole('admin_senior');
    }
}
