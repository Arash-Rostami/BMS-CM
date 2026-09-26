<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->index('user_id', 'idx_departments_user_id');
            $table->index('updated_by_id', 'idx_departments_updated_by_id');
            $table->index('is_active', 'idx_departments_is_active');
        });
    }

    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->dropIndex('idx_departments_user_id');
            $table->dropIndex('idx_departments_updated_by_id');
            $table->dropIndex('idx_departments_is_active');
        });
    }
};
