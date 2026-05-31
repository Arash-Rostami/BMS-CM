<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('proforma_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_no')->unique();
            $table->date('invoice_date');
            $table->string('contract_no')->nullable();
            $table->string('buyer_comm_card_num')->nullable();
            $table->foreignId('seller_id')->nullable()->constrained('companies');
            $table->unsignedBigInteger('buyer_id')->nullable();
            $table->date('validity_date')->nullable()->comment('Invoice validity expiry date');
            $table->string('beneficiary_country')->nullable();
            $table->string('origin_country')->nullable();
            $table->string('destination_country')->nullable();
            $table->boolean('allow_trans_shipment')->default(false);
            $table->boolean('allow_partial_shipment')->default(false);
            $table->string('transport_mode')->nullable();
            $table->string('port_of_discharge')->nullable();
            $table->string('port_of_loading')->nullable();
            $table->string('delivery_terms')->nullable();
            $table->foreignId('main_currency_id')->constrained('currencies');
            $table->foreignId('secondary_currency_id')->nullable()->constrained('currencies');
            $table->decimal('discount', 15, 2)->nullable();
            $table->decimal('freight_charges', 15, 2)->nullable();
            $table->decimal('other_charges', 15, 2)->nullable()->comment('Additional miscellaneous charges');
            $table->decimal('total_amount', 15, 2)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('updated_by_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('proforma_invoices');
    }
};
