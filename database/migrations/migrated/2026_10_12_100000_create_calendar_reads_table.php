<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('calendar_hit_id')->constrained('calendar_hits')->cascadeOnDelete();
            $table->date('event_date')->comment('Hit date when hidden; a moved date reopens the item');
            $table->timestamp('read_at')->comment('When the user hid the item');

            $table->unique(['user_id', 'calendar_hit_id'], 'calendar_reads_user_hit_unique');
            $table->index('calendar_hit_id', 'idx_calendar_reads_hit');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_reads');
    }
};
