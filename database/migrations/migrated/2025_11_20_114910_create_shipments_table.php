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
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();

            // Core Data
            $table->string('shipment_no')->unique();
            $table->string('part')->nullable();
            $table->string('contract_no')->nullable();

            $table->decimal('remittance_amount', 15, 2)->nullable();
            $table->decimal('customs_quantity', 15, 2)->nullable();
            $table->decimal('shipped_quantity', 15, 2)->nullable();

            // Logistics
            $table->string('bl_number')->nullable();
            $table->string('booking_no')->nullable();
            $table->string('container_no')->nullable();
            $table->string('container_type')->nullable();

            $table->json('docs')->nullable()->comment('Boolean flags: inspection, bank_commitment, etc');
            $table->text('notes')->nullable();

            // Dates
            $table->date('warehouse_date')->nullable();
            $table->date('exit_date')->nullable();
            $table->date('eta')->nullable();
            $table->date('etd')->nullable();


            // Relationships
            $table->foreignId('container_status_id')->nullable()->constrained('statuses');
            $table->foreignId('operation_status_id')->nullable()->constrained('statuses');
            $table->foreignId('shipment_status_id')->nullable()->constrained('statuses');
            $table->foreignId('doc_status_id')->nullable()->constrained('statuses');
            $table->foreignId('status_id')->constrained('statuses');

            $table->foreignId('registered_order_id')->constrained('registered_orders')->cascadeOnDelete();
            $table->foreignId('company_id')->comment('Carrier/Forwarder')->constrained('companies');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index('contract_no');
            $table->index('bl_number');
            $table->index('container_no');
            $table->index(['registered_order_id', 'deleted_at']);
            $table->index('company_id');
            $table->index('status_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
