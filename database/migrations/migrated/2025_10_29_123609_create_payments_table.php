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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_no')->unique()->nullable();
            $table->date('payment_date')->nullable();
            $table->date('payment_deadline')->nullable();

            $table->foreignId('status_id')->nullable()->constrained('statuses')->nullOnDelete();
            $table->foreignId('payor_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('payee_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('currency_id')->nullable()->constrained('currencies')->nullOnDelete();

            $table->morphs('targetable');

            $table->decimal('payable_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->nullable()->default(0);
            $table->decimal('exchange_rate', 15, 5)->nullable()->default(0);
            $table->decimal('bank_charges', 15, 2)->nullable()->default(0);
            $table->string('beneficiary_name')->nullable();
            $table->text('beneficiary_address')->nullable();
            $table->foreignId('bank_id')->nullable()->constrained('banks')->nullOnDelete();
            $table->text('bank_address')->nullable();
            $table->string('account_no')->nullable();
            $table->string('swift')->nullable();
            $table->string('iban')->nullable();

            $table->text('notes')->nullable();

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
        Schema::dropIfExists('payments');
    }
};
