<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('name');
            $table->string('subject')->comment('Module FQCN the rule watches');
            $table->json('filters')->comment('RuleBuilder constraint tree');
            $table->json('extra_paths')->nullable()->comment('User-added deep relation paths');
            $table->string('date_path')->comment('Dotted path to the watched date column');
            $table->smallInteger('day_shift')->default(0);
            $table->json('lead_times');
            $table->boolean('on_day')->default(true);
            $table->string('notification_type', 20)->default('in_app')->comment('in_app, email or all');
            $table->string('type');
            $table->string('color', 20)->comment('CalendarColor palette key');
            $table->string('visibility');
            $table->json('shared_user_ids')->nullable();
            $table->json('shared_role_ids')->nullable();
            $table->json('notify_emails')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('related_models')->nullable()->comment('FQCNs of every relation hop the rule touches; derived on save');
            $table->json('watched_columns')->nullable()->comment('Routing map {FQCN: [column, ...]} that triggers recomputes; derived on save, replaces related_models for routing');
            $table->char('fingerprint', 40)->comment('sha1 of identity fields; duplicates blocked app-level');
            $table->char('conditions_hash', 40)->comment('sha1 of matching conditions only; near-duplicate detection');
            $table->unsignedBigInteger('updated_by_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('user_id', 'idx_calendar_rules_user_id');
            $table->index('updated_by_id', 'idx_calendar_rules_updated_by_id');
            $table->index(['subject', 'is_active']);
            $table->index('fingerprint');
            $table->index('conditions_hash');
            $table->index(['user_id', 'deleted_at']);
            $table->index(['deleted_at', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_rules');
    }
};
