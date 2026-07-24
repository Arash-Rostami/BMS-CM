<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            $table->text('name')->nullable();
            $table->text('english_name')->nullable()->comment('Name of the product in English');
            $table->string('slug')->nullable();
            $table->json('attributes')->nullable();
            $table->text('description')->nullable()->comment('Description of the product');
            $table->string('code')->unique()->comment('Unique product code');

            $table->boolean('in_stock')->default(true);
            $table->boolean('is_active')->default(true)->comment('true = active, false = inactive');

            $table->foreignId('user_id')->nullable();
            $table->foreignId('updated_by_id')->nullable();
            $table->foreignId('category_id')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['category_id', 'deleted_at']);
            $table->index(['is_active', 'deleted_at']);
            $table->index('user_id', 'idx_products_user_id');
            $table->index('updated_by_id', 'idx_products_updated_by_id');
            $table->index('slug', 'idx_products_slug');
            $table->index(['in_stock', 'is_active'], 'idx_products_stock_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
