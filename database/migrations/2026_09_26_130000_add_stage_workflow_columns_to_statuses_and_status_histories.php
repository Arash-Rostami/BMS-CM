<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('statuses', function (Blueprint $table) {
            $table->unsignedSmallInteger('stage_order')->nullable()->after('english_name')->comment('Position within english_type; NULL = unordered/free status');
            $table->string('approval_permission', 255)->nullable()->after('stage_order')->comment('Spatie permission name gating who can set this status; NULL = ungated');
        });

        Schema::table('status_histories', function (Blueprint $table) {
            $table->text('reason')->nullable()->after('user_id')->comment('Populated only by Return-for-Revision/reject-style actions');
        });
    }

    public function down(): void
    {
        Schema::table('status_histories', function (Blueprint $table) {
            $table->dropColumn('reason');
        });

        Schema::table('statuses', function (Blueprint $table) {
            $table->dropColumn(['stage_order', 'approval_permission']);
        });
    }
};
