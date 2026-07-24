<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banks', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('Name of the bank');
            $table->string('english_name')->comment('Name of the bank in English');
            $table->text('description')->nullable()->comment('Description of the bank');
            $table->boolean('is_active')->default(true)->comment('true = active, false = inactive');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('updated_by_id')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index('user_id', 'idx_banks_user_id');
            $table->index('updated_by_id', 'idx_banks_updated_by_id');
            $table->index('is_active', 'idx_banks_is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banks');
    }
};
