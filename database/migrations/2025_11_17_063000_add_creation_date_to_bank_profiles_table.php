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
            $table->date('creation_date')->nullable()->after('swift');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bank_profiles', function (Blueprint $table) {
            $table->dropColumn('creation_date');
        });
    }
};
