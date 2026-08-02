<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_request_id')->constrained('purchase_requests')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products');
            $table->decimal('quantity', 15, 5)->nullable()->comment('Quantity requested');
            $table->string('unit')->nullable()->comment('Unit of measure (e.g., pcs)');
            $table->decimal('estimated_cost', 15, 5)->nullable()->comment('Estimated/Expected cost per unit');
            $table->foreignId('status_id')->nullable()->constrained('statuses');
            $table->text('notes')->nullable()->comment('Additional remarks');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['purchase_request_id', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_items');
    }
};
