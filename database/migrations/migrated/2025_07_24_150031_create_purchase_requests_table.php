<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_requests', function (Blueprint $table) {
            $table->id();
            $table->string('pr_number')->unique();
            $table->foreignId('requester_id')->constrained('users');
            $table->foreignId('department_id')->constrained('departments');
            $table->unsignedBigInteger('cost_center_id')->nullable();
            $table->date('required_by_date')->nullable()->comment('Date by which items are needed');
            $table->decimal('total_estimated_cost', 15, 5)->default(0);
            $table->string('urgency_level')->default('low')->comment('Urgency: low, medium, high');
            $table->foreignId('status_id')->nullable()->constrained('statuses');
            $table->foreignId('approver_id')->nullable()->constrained('users');
            $table->timestamp('approval_date')->nullable()->comment('Timestamp of approval');
            $table->text('rejection_reason')->nullable();
            $table->text('notes')->nullable()->comment('Additional notes');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('updated_by_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['department_id', 'deleted_at']);
            $table->index(['status_id', 'deleted_at']);
            $table->index(['requester_id', 'deleted_at']);
            $table->index('cost_center_id', 'idx_pr_cost_center_id');
            $table->index('user_id', 'idx_pr_user_id');
            $table->index('updated_by_id', 'idx_pr_updated_by_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_requests');
    }
};
