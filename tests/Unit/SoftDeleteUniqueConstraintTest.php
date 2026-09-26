<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SoftDeleteUniqueConstraintTest extends TestCase
{
    public function test_soft_deletable_tables_carry_no_db_level_unique_index(): void
    {
        $columnsByTable = [
            'users' => ['email'],
            'products' => ['code'],
            'purchase_requests' => ['pr_number'],
            'shipments' => ['shipment_no'],
            'purchase_orders' => ['po_number'],
            'proforma_invoices' => ['invoice_no'],
            'registered_orders' => ['ro_number', 'official_registration_no'],
            'payments' => ['payment_no'],
            'customs' => ['custom_no'],
            'bank_profiles' => ['bp_number'],
            'statuses' => ['type', 'name'],
        ];

        foreach ($columnsByTable as $table => $columns) {
            foreach (Schema::getIndexes($table) as $index) {
                if ($index['primary'] || ! $index['unique']) {
                    continue;
                }

                $overlap = array_intersect($columns, $index['columns']);

                if ($overlap !== []) {
                    $this->fail(
                        "{$table}.".implode(',', $overlap).
                        " carries a DB-level unique index ({$index['name']}) again — this collides with soft-deleted rows ".
                        '(see modelsPattern.md §9). Uniqueness for these columns must stay application-level only.'
                    );
                }
            }
        }

        $this->assertTrue(true);
    }
}
