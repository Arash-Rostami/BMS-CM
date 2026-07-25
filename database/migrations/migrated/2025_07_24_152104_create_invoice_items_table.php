<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proforma_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proforma_invoice_id')->constrained('proforma_invoices')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products');
            $table->string('description')->nullable()->comment('Item description in local language');
            $table->string('origin')->nullable()->comment('Country of origin (ISO code)');
            $table->string('hs_code')->nullable()->comment('Harmonized System code for customs');
            $table->unsignedDecimal('quantity', 15, 5)->nullable()->comment('Number of units');
            $table->decimal('net_weight', 15, 5)->nullable()->comment('Weight per unit (kg), net');
            $table->decimal('gross_weight', 15, 5)->nullable()->comment('Weight per unit (kg), gross');
            $table->string('unit')->comment('e.g., pcs, kg, ltr');
            $table->decimal('unit_price', 15, 5)->nullable()->comment('Price per unit in invoice currency');
            $table->decimal('freight_charges', 15, 5)->nullable();
            $table->decimal('total_amount', 15, 5)->nullable()->comment('quantity × unit_price');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['proforma_invoice_id', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proforma_invoice_items');
    }
};
