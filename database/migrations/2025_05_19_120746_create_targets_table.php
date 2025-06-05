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
        Schema::create('targets', function (Blueprint $table) {
            $table->id();

            $table->morphs('targetable');
            $table->integer('year')->index();
            $table->date('start_from');
            $table->date('end_in');
            $table->decimal('quantity', 10, 2)->nullable();
            $table->decimal('amount', 10, 2)->nullable();
            $table->decimal('achieved_quantity', 10, 2)->nullable();
            $table->decimal('achieved_amount', 10, 2)->nullable();
            $table->string('metrics')->nullable();
            $table->text('description')->nullable();
            $table->string('status')->default('draft');

            $table->foreignId('user_id')->nullable();
            $table->foreignId('updated_by_id')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('targets');
    }
};
