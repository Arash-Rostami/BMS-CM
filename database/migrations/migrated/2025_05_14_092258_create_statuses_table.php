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
        Schema::create('statuses', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('english_type')->nullable();
            $table->string('name');
            $table->string('english_name')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('updated_by_id')->nullable();
            $table->unique(['type','name']);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['english_type', 'deleted_at'], 'idx_statuses_eng_type_deleted');
            $table->index(['type', 'deleted_at'], 'idx_statuses_type_deleted');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('statuses');
    }
};
