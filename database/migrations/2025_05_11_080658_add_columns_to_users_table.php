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
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('last_log_in')->nullable()->after('remember_token');
            $table->timestamp('last_log_out')->nullable()->after('last_log_in');
            $table->unsignedBigInteger('department_id')->nullable()->after('last_log_out');
            $table->string('position')->nullable()->after('department_id');
            $table->string('role')->nullable()->after('position');
            $table->string('image')->nullable()->after('role');
            $table->string('status')->nullable()->after('image');
            $table->string('ip')->nullable()->after('status');
            $table->string('company')->nullable()->after('ip');
            $table->json('settings')->nullable()->after('company');
            $table->softDeletes()->after('settings');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'phone',
                'department_id',
                'position',
                'role',
                'image',
                'status',
                'company',
                'settings',
                'ip',
                'last_log_in',
                'last_log_out',
                'deleted_at',
            ]);
        });
    }
};
