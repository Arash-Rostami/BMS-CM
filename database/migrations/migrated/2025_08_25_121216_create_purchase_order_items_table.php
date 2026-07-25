<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('quantity', 15, 5);
            $table->string('unit')->comment('e.g., pcs, kg, ltr');
            $table->decimal('unit_price', 15, 5)->comment('Price per single unit');
            $table->decimal('net_weight', 15, 5)->nullable()->comment('Weight of the product itself, without packaging');
            $table->decimal('gross_weight', 15, 5)->nullable()->comment('Total weight including packaging');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['purchase_order_id', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
    }
};
