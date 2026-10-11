<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calendar_rules', function (Blueprint $table) {
            $table->string('notification_type', 20)->default('in_app')->after('on_day')->comment('in_app, email or all');
        });
    }

    public function down(): void
    {
        Schema::table('calendar_rules', function (Blueprint $table) {
            $table->dropColumn('notification_type');
        });
    }
};
