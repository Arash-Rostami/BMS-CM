<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('shipments')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->comment('Null when an old invoice line could not be matched to a product')->constrained('products')->nullOnDelete();
            $table->string('name')->nullable();
            $table->string('english_name')->nullable();
            $table->string('hs_code')->nullable();
            $table->string('origin')->nullable();
            $table->string('unit')->nullable();
            $table->decimal('quantity', 65, 5)->nullable();
            $table->decimal('unit_price', 65, 5)->nullable();
            $table->decimal('total_amount', 65, 5)->nullable();
            $table->decimal('net_weight', 65, 5)->nullable();
            $table->decimal('gross_weight', 65, 5)->nullable();
            $table->timestamps();
        });

        Schema::table('shipments', function (Blueprint $table) {
            $table->decimal('total_net_weight', 65, 5)->nullable()->after('shipped_quantity')->comment('Sum of commercial invoice item net weights');
            $table->decimal('total_gross_weight', 65, 5)->nullable()->after('total_net_weight')->comment('Sum of commercial invoice item gross weights');
            $table->decimal('grand_total', 65, 5)->nullable()->after('total_gross_weight')->comment('Commercial invoice grand total in the invoice currency');
            $table->index(['shipment_status_id', 'deleted_at'], 'idx_shipments_tracking_deleted');
        });

        Schema::table('bank_profiles', function (Blueprint $table) {
            $table->index(['status_id', 'deleted_at'], 'idx_bank_profiles_status_deleted');
            $table->index('commitment_payment_date', 'idx_bank_profiles_commitment_date');
        });

        Schema::table('customs', function (Blueprint $table) {
            $table->index(['clearance_status_id', 'deleted_at'], 'idx_customs_clearance_deleted');
        });
    }

    public function down(): void
    {
        Schema::table('customs', function (Blueprint $table) {
            $table->dropIndex('idx_customs_clearance_deleted');
        });

        Schema::table('bank_profiles', function (Blueprint $table) {
            $table->dropIndex('idx_bank_profiles_commitment_date');
            $table->dropIndex('idx_bank_profiles_status_deleted');
        });

        Schema::table('shipments', function (Blueprint $table) {
            $table->dropIndex('idx_shipments_tracking_deleted');
            $table->dropColumn(['total_net_weight', 'total_gross_weight', 'grand_total']);
        });

        Schema::dropIfExists('shipment_items');
    }
};
