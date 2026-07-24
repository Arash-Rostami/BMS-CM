<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('correspondences', function (Blueprint $table) {
            $table->id();
            $table->morphs('correspondable');
            $table->foreignId('parent_id')->nullable()->constrained('correspondences')->nullOnDelete();
            $table->string('subject')->comment('Brief title or subject of the correspondence');
            $table->text('body')->comment('Main content/message body');
            $table->string('type')->default('note')->comment('Enum: Report, Inquiry, Warning, Note');
            $table->string('priority')->default('normal')->comment('Enum: Low, Normal, High, Urgent');
            $table->boolean('is_internal')->default(false)->comment('Visible only to internal staff (general access)');
            $table->boolean('is_private')->default(false)->comment('Strict privacy: Visible only to creator and explicit recipients');

            $table->foreignId('status_id')->nullable()->constrained('statuses')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index(
                ['correspondable_type', 'correspondable_id', 'deleted_at'],
                'idx_correspondable_deleted'
            );
            $table->index(['parent_id', 'deleted_at'], 'idx_parent_deleted');
        });

        Schema::create('correspondence_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('correspondence_id')->constrained('correspondences')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('type')->default('to')->comment('to, cc, mention');

            $table->timestamp('read_at')->nullable();

            $table->timestamps();

            $table->unique(['correspondence_id', 'user_id']);
            $table->index(['user_id', 'read_at'], 'idx_user_read');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('correspondence_recipients');
        Schema::dropIfExists('correspondences');
    }
};
