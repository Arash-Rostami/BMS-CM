<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code');
            $table->string('english_name')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('updated_by_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('name', 'idx_departments_name');
            $table->index('code', 'idx_departments_code');
            $table->index('user_id', 'idx_departments_user_id');
            $table->index('updated_by_id', 'idx_departments_updated_by_id');
            $table->index('is_active', 'idx_departments_is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
