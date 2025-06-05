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
        Schema::create('banks', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('Name of the bank');
            $table->string('english_name')->comment('Name of the bank in English');
            $table->text('description')->nullable()->comment('Description of the bank');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('updated_by_id')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('banks');
    }
};
