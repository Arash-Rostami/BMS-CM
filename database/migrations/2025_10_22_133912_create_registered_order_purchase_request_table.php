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
        Schema::create('registered_order_purchase_request', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registered_order_id')
                ->constrained('registered_orders')
                ->cascadeOnDelete();
            $table->foreignId('purchase_request_id')
                ->constrained('purchase_requests')
                ->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['registered_order_id', 'purchase_request_id'], 'ro_pr_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('registered_order_purchase_request');
    }
};
