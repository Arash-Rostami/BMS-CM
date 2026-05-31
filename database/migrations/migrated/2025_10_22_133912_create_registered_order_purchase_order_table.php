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
        Schema::create('registered_order_purchase_order', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registered_order_id')
                ->constrained('registered_orders')
                ->cascadeOnDelete();
            $table->foreignId('purchase_order_id')
                ->constrained('purchase_orders')
                ->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['registered_order_id', 'purchase_order_id'], 'ro_po_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('registered_order_purchase_order');
    }
};
