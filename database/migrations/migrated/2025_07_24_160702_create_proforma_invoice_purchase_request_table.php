<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proforma_invoice_purchase_request', function (Blueprint $table) {
            $table->foreignId('proforma_invoice_id')->constrained()->onDelete('cascade');
            $table->foreignId('purchase_request_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            $table->unique(['proforma_invoice_id', 'purchase_request_id'], 'uidx_pi_pr');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proforma_invoice_purchase_request');
    }
};
