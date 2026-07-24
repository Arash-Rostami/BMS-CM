<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable()->unique();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('company')->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->string('position')->nullable();
            $table->string('role')->nullable();
            $table->string('image')->nullable();
            $table->string('status')->nullable();
            $table->string('ip')->nullable();
            $table->timestamp('last_log_in')->nullable();
            $table->timestamp('last_log_out')->nullable();
            $table->json('settings')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->softDeletes();
            $table->timestamps();

            $table->index('phone', 'idx_users_phone');
            $table->index(['role', 'deleted_at'], 'idx_users_role_deleted');
            $table->index(['status', 'deleted_at'], 'idx_users_status_deleted');
            $table->index(['department_id', 'deleted_at'], 'idx_users_department_deleted');
            $table->index(['company', 'deleted_at'], 'idx_users_company_deleted');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();

            $table->index(['user_id', 'last_activity'], 'idx_sessions_user_activity');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
