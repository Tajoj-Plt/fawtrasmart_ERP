<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * جرد أصول العميل أثناء زيارة واحدة: حالة كل أصل (سليم/تالف/ناقص) +
 * صورة إثبات. صفٌّ واحد لكل أصلٍ مفحوص في كل زيارة.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('visit_asset_inspections')) {
            return;
        }

        Schema::create('visit_asset_inspections', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('visit_id');
            $table->unsignedBigInteger('client_company_asset_id');

            $table->string('condition', 20)->default('ok'); // ok|damaged|missing
            $table->unsignedInteger('found_quantity')->default(1);
            $table->string('photo_path')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('inspected_by')->nullable();

            $table->timestamps();

            $table->unique(['visit_id', 'client_company_asset_id']);
            $table->foreign('visit_id')->references('id')->on('visits')->cascadeOnDelete();
            $table->foreign('client_company_asset_id')->references('id')->on('client_company_assets')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visit_asset_inspections');
    }
};
