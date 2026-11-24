<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * سجل واحد لكل سبرنت يجمع بين مراجعة السبرنت (Sprint Review) والاستعادة
 * (Retrospective) بدل جدولين منفصلين: أرقام المراجعة (النقاط الملتزم بها/
 * المكتملة/نسبة الإنجاز) تُحسب من بيانات حقيقية وقت الإنشاء وتُحفظ كلقطة
 * (snapshot) هنا، حتى تبقى القراءة التاريخية صحيحة حتى لو تغيّرت بيانات
 * المهام لاحقاً؛ الحقول السردية (ماذا سار جيداً... إلخ) هي الاستعادة الفعلية.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sprint_retrospectives')) {
            return;
        }

        Schema::create('sprint_retrospectives', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sprint_id')->unique();
            $table->unsignedInteger('committed_points')->nullable()->comment('لقطة وقت إنشاء الاستعادة');
            $table->unsignedInteger('completed_points')->nullable()->comment('لقطة وقت إنشاء الاستعادة');
            $table->unsignedTinyInteger('completion_percentage')->nullable()->comment('لقطة وقت إنشاء الاستعادة');
            $table->longText('went_well')->nullable();
            $table->longText('went_wrong')->nullable();
            $table->longText('improvements')->nullable();
            $table->json('action_items')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('sprint_id')->references('id')->on('sprints')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sprint_retrospectives');
    }
};
