<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * مهام المتابعة المُضافة من مساحة عمل الزيارة («إضافة مهمة للمتابعة»).
 *
 * جدول مستقلّ لا يُعاد استعمال `appointments`: عمود `title` في موديل
 * `Appointment` لا وجود له في جدول `appointments` الفعلي (أعمدته
 * `time/duration/notes/action_type/...`) — عدم تطابقٍ قديم بين الموديل
 * والجدول، فبناء ميزةٍ جديدة عليه يورّث خطأه بدل إصلاحه.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('visit_follow_up_tasks')) {
            return;
        }

        Schema::create('visit_follow_up_tasks', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('visit_id')->nullable();
            $table->unsignedBigInteger('employee_id')->nullable();

            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->string('status', 20)->default('pending'); // pending|completed|cancelled

            $table->timestamps();

            $table->index(['client_id', 'status']);
            $table->foreign('visit_id')->references('id')->on('visits')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visit_follow_up_tasks');
    }
};
