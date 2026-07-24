<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('Name of the company');
            $table->string('english_name')->comment('Name of the company in English');
            $table->text('description')->nullable()->comment('Description of the company');
            $table->json('types')->nullable()->comment('For better recalling');
            $table->boolean('is_active')->default(true)->comment('true = active, false = inactive');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('updated_by_id')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index('user_id', 'idx_companies_user_id');
            $table->index('updated_by_id', 'idx_companies_updated_by_id');
            $table->index(['is_active', 'deleted_at'], 'idx_companies_active_deleted');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
