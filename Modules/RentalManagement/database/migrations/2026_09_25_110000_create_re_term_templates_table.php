<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * قوالب شروط العقود — تُدار من الإعدادات وتُنسخ (لقطة) إلى re_contract_terms
 * عند إنشاء العقد، فلا يغيّر تعديلُ القالب لاحقاً عقداً قائماً.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('re_term_templates')) {
            return;
        }

        Schema::create('re_term_templates', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->string('contract_type', 30)->nullable(); // null = كل الأنواع
            $table->boolean('is_default')->default(false);   // يُضاف تلقائياً لكل عقدٍ جديد
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('re_term_templates');
    }
};
