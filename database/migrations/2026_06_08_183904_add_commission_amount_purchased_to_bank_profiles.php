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
        Schema::table('bank_profiles', function (Blueprint $table) {
            $table->decimal('commission_amount_purchased', 15, 2)->nullable()->after('commission_rate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bank_profiles', function (Blueprint $table) {
            $table->dropColumn('commission_amount_purchased');
        });
    }
};
