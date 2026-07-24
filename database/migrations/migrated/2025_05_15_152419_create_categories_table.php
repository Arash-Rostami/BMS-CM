<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();

            $table->text('name');
            $table->string('slug')->unique();
            $table->text('english_name')->comment('Name of the category in English');
            $table->text('description')->nullable()->comment('Description of the category');

            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('categories')
                ->cascadeOnDelete();
            $table->tinyInteger('level')->default(0)->comment('Level in the category hierarchy');
            $table->boolean('active')->default(true);

            $table->foreignId('user_id')->nullable();
            $table->foreignId('updated_by_id')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index('user_id', 'idx_categories_user_id');
            $table->index('updated_by_id', 'idx_categories_updated_by_id');
            $table->index(['active', 'deleted_at'], 'idx_categories_active_deleted');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
