<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_hits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calendar_rule_id')->constrained('calendar_rules')->cascadeOnDelete();
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->string('label')->comment('Denormalized item label so the grid never morph-loads');
            $table->date('event_date');
            $table->json('alerts_sent')->comment('Map of lead (or overdue) to date fired; engine always writes it');
            $table->unsignedTinyInteger('overdue_count')->default(0);
            $table->timestamp('synced_at')->nullable()->comment('Stamp of the sync run that produced this row');
            $table->timestamps();

            $table->unique(['calendar_rule_id', 'subject_type', 'subject_id'], 'calendar_hits_rule_subject_unique');
            $table->index('event_date');
            $table->index(['calendar_rule_id', 'event_date'], 'idx_calendar_hits_rule_date');
            $table->index(['event_date', 'subject_type'], 'idx_calendar_hits_date_subject');
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_hits');
    }
};
