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
            $table->string('code')->unique();
            $table->string('english_name')->nullable();
            $table->text('description')->nullable();

            $table->index('name', 'idx_departments_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
