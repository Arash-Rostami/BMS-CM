<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected array $columns = [
        'purchase_requests' => ['pr_number'],
        'proforma_invoices' => ['invoice_no'],
        'registered_orders' => ['ro_number', 'contract_no'],
        'purchase_orders' => ['po_number'],
        'bank_profiles' => ['bp_number'],
        'payments' => ['payment_no'],
        'shipments' => ['shipment_no'],
        'customs' => ['custom_no'],
    ];

    public function up(): void
    {
        foreach ($this->columns as $table => $fields) {
            foreach ($fields as $field) {
                $activeColumn = "{$field}_active";

                DB::statement("ALTER TABLE `{$table}` ADD COLUMN `{$activeColumn}` VARCHAR(255) GENERATED ALWAYS AS (CASE WHEN `deleted_at` IS NULL THEN `{$field}` ELSE NULL END) STORED NULL");

                Schema::table($table, function (Blueprint $blueprint) use ($activeColumn): void {
                    $blueprint->unique($activeColumn);
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->columns as $table => $fields) {
            foreach ($fields as $field) {
                $activeColumn = "{$field}_active";

                Schema::table($table, function (Blueprint $blueprint) use ($activeColumn): void {
                    $blueprint->dropUnique([$activeColumn]);
                });

                Schema::table($table, function (Blueprint $blueprint) use ($activeColumn): void {
                    $blueprint->dropColumn($activeColumn);
                });
            }
        }
    }
};
