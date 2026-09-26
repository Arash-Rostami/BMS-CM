<?php

namespace Database\Seeders;

use App\Models\Permission;
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
        // 1. Define Permissions — singular snake_case prefix matching Eloquent model names
        $permissions = [
            'bank.view', 'bank.create', 'bank.edit', 'bank.delete', 'bank.restore',
            'bank_profile.view', 'bank_profile.create', 'bank_profile.edit', 'bank_profile.delete', 'bank_profile.restore',
            'category.view', 'category.create', 'category.edit', 'category.delete', 'category.restore',
            'company.view', 'company.create', 'company.edit', 'company.delete', 'company.restore',
            'correspondence.view', 'correspondence.create', 'correspondence.edit', 'correspondence.delete', 'correspondence.restore',
            'currency.view', 'currency.create', 'currency.edit', 'currency.delete', 'currency.restore',
            'custom.view', 'custom.create', 'custom.edit', 'custom.delete', 'custom.restore',
            'department.view', 'department.create', 'department.edit', 'department.delete', 'department.restore',
            'payment.view', 'payment.create', 'payment.edit', 'payment.delete', 'payment.restore',
            'product.view', 'product.create', 'product.edit', 'product.delete', 'product.restore',
            'proforma_invoice.view', 'proforma_invoice.create', 'proforma_invoice.edit', 'proforma_invoice.delete', 'proforma_invoice.restore',
            'purchase_order.view', 'purchase_order.create', 'purchase_order.edit', 'purchase_order.delete', 'purchase_order.restore',
            'purchase_request.view', 'purchase_request.create', 'purchase_request.edit', 'purchase_request.delete', 'purchase_request.restore',
            'permission.view', 'permission.create', 'permission.edit', 'permission.delete', 'permission.restore',
            'registered_order.view', 'registered_order.create', 'registered_order.edit', 'registered_order.delete', 'registered_order.restore',
            'role.view', 'role.create', 'role.edit', 'role.delete', 'role.restore',
            'shipment.view', 'shipment.create', 'shipment.edit', 'shipment.delete', 'shipment.restore',
            'status.view', 'status.create', 'status.edit', 'status.delete', 'status.restore',
            'target.view', 'target.create', 'target.edit', 'target.delete', 'target.restore',
            'user.view', 'user.create', 'user.edit', 'user.delete', 'user.restore',
        ];

        foreach ($permissions as $permissionName) {
            Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']);
        }

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
