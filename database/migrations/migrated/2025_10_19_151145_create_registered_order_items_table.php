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
        Schema::create('registered_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registered_order_id')->constrained('registered_orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products');
            $table->decimal('quantity', 15, 2);
            $table->string('unit');
            $table->decimal('unit_price', 15, 2);
            $table->decimal('net_weight', 15, 2)->nullable();
            $table->decimal('gross_weight', 15, 2)->nullable();
            $table->decimal('entrance_fee', 15, 2)->nullable();
            $table->decimal('shipping_cost', 15, 2)->nullable();
            $table->decimal('extra_cost', 15, 2)->nullable();
            $table->decimal('line_total', 15, 2);
            $table->text('packing_details')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['registered_order_id', 'deleted_at']);
            $table->index('product_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('registered_order_items');
    }
};
