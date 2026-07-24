<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_attributes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products');
            $table->string('key', 50);
            $table->string('value', 100);

            $table->index(['product_id', 'key']);
            $table->index(['key', 'value'], 'idx_product_attributes_key_value');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_attributes');
    }
};
