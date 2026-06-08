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
        Schema::create('registered_orders', function (Blueprint $table) {
            $table->id();
            $table->string('ro_number')->unique();
            $table->string('contract_no')->unique();
            $table->string('official_registration_no')->unique();
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
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('updated_by_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('contract_no');
            $table->index(['status_id', 'deleted_at']);
            $table->index('buyer_id');
            $table->index('seller_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('registered_orders');
    }
};
