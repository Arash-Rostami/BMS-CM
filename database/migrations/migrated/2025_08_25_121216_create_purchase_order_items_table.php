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
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->integer('quantity');
            $table->string('unit')->comment('e.g., pcs, kg, ltr');
            $table->decimal('unit_price', 10, 2)->comment('Price per single unit');
            $table->decimal('net_weight', 10, 2)->nullable()->comment('Weight of the product itself, without packaging');
            $table->decimal('gross_weight', 10, 2)->nullable()->comment('Total weight including packaging');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['purchase_order_id', 'deleted_at']);
            $table->index('product_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
    }
};
