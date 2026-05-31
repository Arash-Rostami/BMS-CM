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
        Schema::create('customs', function (Blueprint $table) {
            $table->id();
            $table->string('custom_no')->unique()->nullable();
            $table->foreignId('shipment_id')->constrained('shipments')->cascadeOnDelete();
            $table->foreignId('registered_order_id')->constrained('registered_orders')->cascadeOnDelete();

            $table->string('shipment_no')->nullable();
            $table->string('contract_no')->nullable();
            $table->string('declaration_no')->nullable();
            $table->string('clearance_type')->nullable();

            $table->decimal('commitment_balance', 15, 2)->nullable();

            $table->date('clearance_date')->nullable();
            $table->date('doc_submission_date')->nullable();
            $table->date('ten_percent_exit_date')->nullable();
            $table->date('payment_due_date')->nullable();
            $table->date('commitment_payment_date')->nullable();
            $table->date('rial_return_date')->nullable();

            // Statuses
            $table->foreignId('clearance_status_id')->nullable()->constrained('statuses')->nullOnDelete();
            $table->foreignId('bank_guarantee_status_id')->nullable()->constrained('statuses')->nullOnDelete();
            $table->foreignId('commitment_status_id')->nullable()->constrained('statuses')->nullOnDelete();

            $table->text('notes')->nullable();

            // User Stamps
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
        Schema::dropIfExists('customs');
    }
};
