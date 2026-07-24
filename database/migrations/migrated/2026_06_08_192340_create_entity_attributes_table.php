<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entity_attributes', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id');
            $table->string('key');
            $table->json('value');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('updated_by_id')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['entity_type', 'entity_id', 'key']);
            $table->index(['entity_type', 'key']);
            $table->index('user_id', 'idx_entity_attributes_user_id');
            $table->index('updated_by_id', 'idx_entity_attributes_updated_by_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entity_attributes');
    }
};
