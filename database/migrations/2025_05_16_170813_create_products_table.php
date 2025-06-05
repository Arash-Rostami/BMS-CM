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
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            $table->string('name')->nullable();
            $table->string('english_name')->nullable()->comment('Name of the product in English');
            $table->string('slug')->nullable();
            $table->json('attributes')->nullable();
            $table->text('description')->nullable()->comment('Description of the product');
            $table->string('code')->unique()->comment('Unique product code');

            $table->boolean('in_stock')->default(true);

            $table->foreignId('user_id')->nullable();
            $table->foreignId('updated_by_id')->nullable();
            $table->foreignId('category_id')->nullable();


            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
