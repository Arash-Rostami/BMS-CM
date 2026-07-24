<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_closure', function (Blueprint $table) {
            $table->unsignedBigInteger('ancestor_id');
            $table->unsignedBigInteger('descendant_id');
            $table->integer('depth')->unsigned();
            $table->primary(['ancestor_id', 'descendant_id']);
            $table->foreign('ancestor_id')
                ->references('id')->on('categories')
                ->cascadeOnDelete();
            $table->foreign('descendant_id')
                ->references('id')->on('categories')
                ->cascadeOnDelete();
            $table->softDeletes();

            $table->index('descendant_id', 'idx_category_closure_descendant');
            $table->index('depth', 'idx_category_closure_depth');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_closure');
    }
};
