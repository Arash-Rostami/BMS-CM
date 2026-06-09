<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('bank_profiles', function (Blueprint $table) {
            $table->date('payment_due_date')->nullable()->after('delivery_date');
            $table->date('commitment_payment_date')->nullable()->after('payment_due_date');
        });

        Schema::table('customs', function (Blueprint $table) {
            $table->dropColumn(['payment_due_date', 'commitment_payment_date']);
        });
    }

    public function down(): void
    {
        Schema::table('customs', function (Blueprint $table) {
            $table->date('payment_due_date')->nullable();
            $table->date('commitment_payment_date')->nullable();
        });

        Schema::table('bank_profiles', function (Blueprint $table) {
            $table->dropColumn(['payment_due_date', 'commitment_payment_date']);
        });
    }
};
