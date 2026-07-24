<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('desk_references', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('group_key');
            $table->unsignedInteger('version');
            $table->timestamp('acknowledged_at');
            $table->timestamps();

            $table->unique(['user_id', 'group_key']);
            $table->index('group_key', 'idx_desk_references_group_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('desk_references');
    }
};
