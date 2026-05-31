<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bank_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('bp_number')->unique()->nullable();
            $table->foreignId('status_id')->nullable()->constrained('statuses')->nullOnDelete();
            $table->foreignId('registered_order_id')->constrained('registered_orders')->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('bank_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('currency_id')->nullable()->constrained('currencies')->nullOnDelete();

            $table->string('order_number')->nullable();
            $table->string('supply_source')->nullable();

            $table->decimal('requested_amount', 15, 2)->default(0)->comment('Amount requested in requested_currency');
            $table->decimal('purchased_equivalent', 15, 2)->default(0)->comment('Amount actually purchased in requested currency or local equivalent');
            $table->decimal('commission_rate', 5, 5)->default(0)->comment('Commission percentage charged (e.g., 1.50)');
            $table->decimal('exchange_rate', 15, 5)->default(0)->comment('Rate used to convert requested currency to reporting currency');
            $table->decimal('final_rate', 15, 5)->default(0)->comment('Final or effective rate after fees and adjustments');
            $table->foreignId('requested_currency_id')->nullable()->constrained('currencies');
            $table->foreignId('purchased_currency_id')->nullable()->constrained('currencies');
            $table->decimal('conversion_rate', 15, 5)->nullable();
            $table->decimal('documents_amount', 15, 2)->default(0)->comment('Total value of documents/fees associated with the transaction');

            $table->date('creation_date')->nullable()->comment('Date the record is created in Official Platform');
            $table->date('allocation_date')->nullable()->comment('Date allocation of funds was made or reserved');
            $table->date('purchase_date')->nullable()->comment('Date when purchase/execution occurred');
            $table->date('delivery_date')->nullable()->comment('Expected or actual delivery date for goods/services');

            $table->text('notes')->nullable()->comment('Free-text notes for internal use');

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_profiles');
    }
};
