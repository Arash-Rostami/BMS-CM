<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_no')->nullable();
            $table->date('payment_date')->nullable();
            $table->date('payment_deadline')->nullable();

            $table->foreignId('status_id')->nullable()->constrained('statuses')->nullOnDelete();
            $table->foreignId('payor_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('payee_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('currency_id')->nullable()->constrained('currencies')->nullOnDelete();

            $table->morphs('targetable');

            $table->decimal('payable_amount', 65, 5)->default(0);
            $table->decimal('total_amount', 65, 5)->nullable()->default(0);
            $table->decimal('exchange_rate', 65, 5)->nullable()->default(0);
            $table->decimal('bank_charges', 65, 5)->nullable()->default(0);
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

            $table->index(['targetable_type', 'targetable_id', 'deleted_at'], 'idx_payments_targetable_deleted');
            $table->index(['status_id', 'deleted_at']);
            $table->index(['payment_date', 'payment_deadline'], 'idx_payments_date_deadline');
            $table->index(['payee_id', 'deleted_at'], 'idx_payments_payee_deleted');
            $table->index(['currency_id', 'deleted_at'], 'idx_payments_currency_deleted');
            $table->index(['deleted_at', 'created_at'], 'idx_payments_deleted_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
