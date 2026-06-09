<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Permission;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Define Permissions — singular snake_case prefix matching Eloquent model names
        $permissions = [
            'attachment.view', 'attachment.create', 'attachment.edit', 'attachment.delete',
            'bank.view', 'bank.create', 'bank.edit', 'bank.delete',
            'bank_profile.view', 'bank_profile.create', 'bank_profile.edit', 'bank_profile.delete',
            'category.view', 'category.create', 'category.edit', 'category.delete',
            'company.view', 'company.create', 'company.edit', 'company.delete',
            'correspondence.view', 'correspondence.create', 'correspondence.edit', 'correspondence.delete',
            'correspondence_recipient.view', 'correspondence_recipient.create', 'correspondence_recipient.edit', 'correspondence_recipient.delete',
            'currency.view', 'currency.create', 'currency.edit', 'currency.delete',
            'custom.view', 'custom.create', 'custom.edit', 'custom.delete',
            'department.view', 'department.create', 'department.edit', 'department.delete',
            'payment.view', 'payment.create', 'payment.edit', 'payment.delete',
            'product.view', 'product.create', 'product.edit', 'product.delete',
            'proforma_invoice.view', 'proforma_invoice.create', 'proforma_invoice.edit', 'proforma_invoice.delete',
            'proforma_invoice_item.view', 'proforma_invoice_item.create', 'proforma_invoice_item.edit', 'proforma_invoice_item.delete',
            'purchase_order.view', 'purchase_order.create', 'purchase_order.edit', 'purchase_order.delete',
            'purchase_request.view', 'purchase_request.create', 'purchase_request.edit', 'purchase_request.delete',
            'permission.view', 'permission.create', 'permission.edit', 'permission.delete',
            'registered_order.view', 'registered_order.create', 'registered_order.edit', 'registered_order.delete',
            'role.view', 'role.create', 'role.edit', 'role.delete',
            'shipment.view', 'shipment.create', 'shipment.edit', 'shipment.delete',
            'specification.view', 'specification.create', 'specification.edit', 'specification.delete',
            'status.view', 'status.create', 'status.edit', 'status.delete',
            'target.view', 'target.create', 'target.edit', 'target.delete',
            'user.view', 'user.create', 'user.edit', 'user.delete',
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
                // 'role' column is deprecated but if you still have it in DB, you might want to fill it or ignore it.
            ]
        );

        // Assign a role to the user
        $user->assignRole('admin_senior');
    }
}
