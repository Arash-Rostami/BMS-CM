<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected array $indexes = [
        'purchase_requests' => 'pr_number',
        'proforma_invoices' => 'invoice_no',
        'registered_orders' => ['ro_number', 'contract_no'],
        'purchase_orders' => 'po_number',
        'bank_profiles' => 'bp_number',
        'payments' => 'payment_no',
        'shipments' => 'shipment_no',
        'customs' => 'custom_no',
    ];

    public function up(): void
    {
        foreach ($this->indexes as $table => $columns) {
            Schema::table($table, function (Blueprint $blueprint) use ($columns): void {
                foreach ((array) $columns as $column) {
                    $blueprint->index($column);
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as $table => $columns) {
            Schema::table($table, function (Blueprint $blueprint) use ($columns): void {
                foreach ((array) $columns as $column) {
                    $blueprint->dropIndex([$column]);
                }
            });
        }
    }
};
