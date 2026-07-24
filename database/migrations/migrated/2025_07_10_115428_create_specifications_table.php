<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('specifications')) {
            Schema::create('specifications', function (Blueprint $table) {
                $table->id();
                $table->morphs('specifiable');

                $table->string('hs_code')->nullable();
                $table->string('import_duty')->nullable();
                $table->string('packing_type')->nullable();
                $table->boolean('vat_exempt')->default(false);
                $table->string('tax_id')->nullable();
                $table->string('manufacturer')->nullable();
                $table->json('import_licenses')->nullable();
                $table->json('extra')->nullable();

                $table->unsignedBigInteger('user_id')->nullable();
                $table->unsignedBigInteger('updated_by_id')->nullable();
                $table->softDeletes();
                $table->timestamps();

                $table->index(
                    ['specifiable_type', 'specifiable_id', 'deleted_at'],
                    'idx_specifiable_deleted'
                );
                $table->index('hs_code');
                $table->index('tax_id');
                $table->index('manufacturer');
                $table->index('user_id', 'idx_specifications_user_id');
                $table->index('updated_by_id', 'idx_specifications_updated_by_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('specifications');
    }
};
