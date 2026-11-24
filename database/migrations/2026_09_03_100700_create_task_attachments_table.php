<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * يستبدل عمود tasks.files (json) غير المستخدم فعلياً — Task::addFile() كانت
 * دالة فارغة بلا تنفيذ. العمود القديم يبقى دون مساس به؛ هذا الجدول هو
 * المسار الحقيقي الجديد للمرفقات.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('task_attachments')) {
            return;
        }

        Schema::create('task_attachments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('task_id');
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->unsignedBigInteger('size')->comment('bytes');
            $table->string('mime_type')->nullable();
            $table->timestamps();

            $table->index('task_id');
            $table->foreign('task_id')->references('id')->on('tasks')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_attachments');
    }
};
