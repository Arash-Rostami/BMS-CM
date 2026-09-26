<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registered_orders', function (Blueprint $table) {
            $table->id();
            $table->string('ro_number');
            $table->string('contract_no');
            $table->string('official_registration_no')->nullable();
            $table->foreignId('seller_id')->constrained('companies');
            $table->foreignId('buyer_id')->constrained('companies');
            $table->foreignId('status_id')->constrained('statuses');
            $table->date('order_date');
            $table->date('validity_date')->nullable();
            $table->date('expected_delivery_date')->nullable();
            $table->string('incoterms')->nullable();
            $table->foreignId('currency_id')->constrained('currencies');
            $table->string('currency_type')->nullable();
            $table->string('insurance_number')->nullable();
            $table->string('insurance_provider')->nullable();
            $table->date('insurance_date')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status_id', 'deleted_at']);
            $table->index('expected_delivery_date', 'idx_registered_orders_expected_delivery_date');
            $table->index(['currency_id', 'deleted_at'], 'idx_registered_orders_currency_deleted');
            $table->index(['deleted_at', 'created_at'], 'idx_registered_orders_deleted_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registered_orders');
    }
};
