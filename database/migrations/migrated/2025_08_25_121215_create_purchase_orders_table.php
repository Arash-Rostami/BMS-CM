<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('po_number')->unique()->comment('Unique Purchase Order number');
            $table->foreignId('seller_id')->constrained('companies');
            $table->foreignId('buyer_id')->constrained('companies');
            $table->foreignId('status_id')->constrained('statuses')->cascadeOnDelete();
            $table->date('order_date')->comment('The date the order was created');
            $table->date('validity_date')->comment('Date until which the offer is valid');
            $table->date('expected_delivery_date')->nullable();
            $table->string('incoterms')->nullable()->comment('International Commercial Terms');
            $table->text('shipping_address')->nullable();
            $table->text('packing_details')->nullable();
            $table->foreignId('currency_id')->constrained('currencies');
            $table->text('notes')->nullable()->comment('Additional notes or instructions');
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['buyer_id', 'deleted_at']);
            $table->index(['status_id', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
