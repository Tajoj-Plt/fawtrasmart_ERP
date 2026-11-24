<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * project_id قابل للـ null عمداً ليسمح لاحقاً بتصنيفات عامة مشتركة بين
 * المشاريع إذا لزم الأمر؛ حالياً كل الاستخدام المخطط له مرتبط بمشروع واحد.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('labels')) {
            return;
        }

        Schema::create('labels', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->string('name');
            $table->string('color', 7)->default('#7367f0')->comment('hex color');
            $table->timestamps();

            $table->index('project_id');
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('labels');
    }
};
