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
        Schema::create('specifications', function (Blueprint $table) {
            $table->id();
            $table->morphs('specifiable');

            $table->string('hs_code')->nullable();
            $table->string('import_duty')->nullable();
            $table->string('packing_type')->nullable();
            $table->boolean('vat_exempt')->default(false);
            $table->string('tax_id')->nullable();
            $table->string('manufacturer')->nullable();
            // ["ministry_quota", "food_drug_license" , ...]
            $table->json('import_licenses')->nullable();
            // Extensible JSON store
            $table->json('extra')->nullable();

            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('updated_by_id')->nullable();
            $table->index(['specifiable_type', 'specifiable_id'], 'specifiable_index');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('specifications');
    }
};
