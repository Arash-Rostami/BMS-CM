<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('targets', function (Blueprint $table) {
            $table->id();

            $table->morphs('targetable');
            $table->integer('year');
            $table->date('start_from');
            $table->date('end_in');
            $table->decimal('quantity', 15, 5)->nullable();
            $table->decimal('amount', 15, 5)->nullable();
            $table->decimal('achieved_quantity', 15, 5)->nullable();
            $table->decimal('achieved_amount', 15, 5)->nullable();
            $table->string('metrics')->nullable();
            $table->text('description')->nullable();
            $table->json('tags')->nullable();
            $table->string('status')->default('draft');

            $table->foreignId('user_id')->nullable();
            $table->foreignId('updated_by_id')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['targetable_type', 'targetable_id', 'deleted_at'], 'idx_targets_targetable_deleted');
            $table->index(['year', 'deleted_at'], 'idx_targets_year_deleted');
            $table->index(['status', 'deleted_at'], 'idx_targets_status_deleted');
            $table->index('user_id', 'idx_targets_user_id');
            $table->index('updated_by_id', 'idx_targets_updated_by_id');
            $table->index(['start_from', 'end_in'], 'idx_targets_date_range');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('targets');
    }
};
