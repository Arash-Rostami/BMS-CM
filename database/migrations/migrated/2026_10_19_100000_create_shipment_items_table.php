<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('shipments')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->comment('Null when an old invoice line could not be matched to a product')->constrained('products')->nullOnDelete();
            $table->string('name')->nullable();
            $table->string('english_name')->nullable();
            $table->string('hs_code')->nullable();
            $table->string('origin')->nullable();
            $table->string('unit')->nullable();
            $table->decimal('quantity', 65, 5)->nullable();
            $table->decimal('unit_price', 65, 5)->nullable();
            $table->decimal('total_amount', 65, 5)->nullable();
            $table->decimal('net_weight', 65, 5)->nullable();
            $table->decimal('gross_weight', 65, 5)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_items');
    }
};
