<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proforma_invoice_registered_order', function (Blueprint $table) {
            $table->foreignId('proforma_invoice_id')->constrained('proforma_invoices')->cascadeOnDelete();
            $table->foreignId('registered_order_id')->constrained('registered_orders')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['proforma_invoice_id', 'registered_order_id'], 'uidx_pi_ro');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proforma_invoice_registered_order');
    }
};
