<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reconstructed to match production exactly (name + timestamp taken from production's own
     * migrations ledger). No model or app code references this table — it predates every other
     * migration in this repo and looks like a leftover from an earlier, removed language-preference
     * mechanism. Kept only so a fresh install's schema matches production; not wired to anything.
     */
    public function up(): void
    {
        Schema::create('user_languages', function (Blueprint $table) {
            $table->id();
            $table->string('model_id');
            $table->string('model_type');
            $table->string('lang')->nullable()->default('en');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_languages');
    }
};
