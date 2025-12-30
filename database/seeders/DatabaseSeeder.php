<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Define Permissions
        $permissions = [
            // attachments
            'attachments.view', 'attachments.create', 'attachments.edit', 'attachments.delete',
            // banks
            'banks.view', 'banks.create', 'banks.edit', 'banks.delete',
            // bank_profiles
            'bank_profiles.view', 'bank_profiles.create', 'bank_profiles.edit', 'bank_profiles.delete',
            // categories
            'categories.view', 'categories.create', 'categories.edit', 'categories.delete',
            // companies
            'companies.view', 'companies.create', 'companies.edit', 'companies.delete',
            // correspondences
            'correspondences.view', 'correspondences.create', 'correspondences.edit', 'correspondences.delete',
            // correspondence_recipients
            'correspondence_recipients.view', 'correspondence_recipients.create', 'correspondence_recipients.edit', 'correspondence_recipients.delete',
            // currencies
            'currencies.view', 'currencies.create', 'currencies.edit', 'currencies.delete',
            // customs
            'customs.view', 'customs.create', 'customs.edit', 'customs.delete',
            // departments
            'departments.view', 'departments.create', 'departments.edit', 'departments.delete',
            // payments
            'payments.view', 'payments.create', 'payments.edit', 'payments.delete',
            // products
            'products.view', 'products.create', 'products.edit', 'products.delete',
            // proforma_invoices
            'proforma_invoices.view', 'proforma_invoices.create', 'proforma_invoices.edit', 'proforma_invoices.delete',
            // proforma_invoice_items
            'proforma_invoice_items.view', 'proforma_invoice_items.create', 'proforma_invoice_items.edit', 'proforma_invoice_items.delete',
            // purchase_orders
            'purchase_orders.view', 'purchase_orders.create', 'purchase_orders.edit', 'purchase_orders.delete',
            // purchase_requests
            'purchase_requests.view', 'purchase_requests.create', 'purchase_requests.edit', 'purchase_requests.delete',
            // permission
            'permission.view', 'permission.create', 'permission.edit', 'permission.delete',
            // registered_orders
            'registered_orders.view', 'registered_orders.create', 'registered_orders.edit', 'registered_orders.delete',
            // role
            'role.view', 'role.create', 'role.edit', 'role.delete',
            // shipments
            'shipments.view', 'shipments.create', 'shipments.edit', 'shipments.delete',
            // specifications
            'specifications.view', 'specifications.create', 'specifications.edit', 'specifications.delete',
            // statuses
            'statuses.view', 'statuses.create', 'statuses.edit', 'statuses.delete',
            // targets
            'targets.view', 'targets.create', 'targets.edit', 'targets.delete',
            // users
            'users.view', 'users.create', 'users.edit', 'users.delete',
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
