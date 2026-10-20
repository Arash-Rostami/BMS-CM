<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'activity.view', 'activity.create', 'activity.edit', 'activity.delete', 'activity.restore',
            'bank.view', 'bank.create', 'bank.edit', 'bank.delete', 'bank.restore',
            'bank_profile.view', 'bank_profile.create', 'bank_profile.edit', 'bank_profile.delete', 'bank_profile.restore',
            'calendar_rule.view', 'calendar_rule.create', 'calendar_rule.edit', 'calendar_rule.delete', 'calendar_rule.restore',
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
            'report.view', 'report.create', 'report.edit', 'report.delete', 'report.restore',
            'report.view_executive', 'report.view_operations', 'report.view_fx_supply', 'report.view_in_transit', 'report.view_customs', 'report.view_fx_commitments', 'report.view_requester',
            'role.view', 'role.create', 'role.edit', 'role.delete', 'role.restore',
            'shipment.view', 'shipment.create', 'shipment.edit', 'shipment.delete', 'shipment.restore',
            'status.view', 'status.create', 'status.edit', 'status.delete', 'status.restore',
            'status.grant_purchase_request_commercial_manager_approval', 'status.grant_purchase_request_sales_manager_approval',
            'target.view', 'target.create', 'target.edit', 'target.delete', 'target.restore',
            'user.view', 'user.create', 'user.edit', 'user.delete', 'user.restore',
        ];

        foreach ($permissions as $permissionName) {
            Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']);
        }
    }
}
