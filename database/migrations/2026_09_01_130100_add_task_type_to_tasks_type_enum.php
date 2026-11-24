<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * إضافة النوع «task» إلى `tasks.type` لدعم المشاريع ذات الإدارة اليدوية
 * (بدون تسلسل مكوّن/ملحمة/قصة مستخدم — مهمة وأسفل منها مهمة فرعية فقط).
 *
 * SQLite (المستخدم في بيئة الاختبارات فقط عبر phpunit.xml) لا يدعم
 * ALTER TABLE ... MODIFY، ويترجم enum() إلى قيد CHECK لا يمكن توسيعه إلا
 * بإعادة بناء الجدول. قاعدة بيانات الاختبارات فارغة دوماً عند تنفيذ
 * الترحيلات (RefreshDatabase تُرحِّل قبل إدخال أي بيانات)، فإعادة البناء آمنة.
 */
return new class extends Migration
{
    private const OLD_TYPES = ['component', 'epic', 'user_story', 'bug', 'sub_task'];

    private const NEW_TYPES = ['component', 'epic', 'user_story', 'bug', 'sub_task', 'task'];

    public function up(): void
    {
        if (! Schema::hasTable('tasks')) {
            return;
        }

        if (DB::connection()->getDriverName() === 'sqlite') {
            $this->rebuildSqliteTasksTable(self::NEW_TYPES);

            return;
        }

        DB::statement(
            "ALTER TABLE `tasks` MODIFY `type`
             ENUM('component','epic','user_story','bug','sub_task','task') NOT NULL DEFAULT 'user_story'"
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('tasks')) {
            return;
        }

        DB::table('tasks')->where('type', 'task')->update(['type' => 'sub_task']);

        if (DB::connection()->getDriverName() === 'sqlite') {
            $this->rebuildSqliteTasksTable(self::OLD_TYPES);

            return;
        }

        DB::statement(
            "ALTER TABLE `tasks` MODIFY `type`
             ENUM('component','epic','user_story','bug','sub_task') NOT NULL DEFAULT 'user_story'"
        );
    }

    private function rebuildSqliteTasksTable(array $types): void
    {
        Schema::create('tasks_type_rebuild_tmp', function (Blueprint $table) use ($types) {
            $table->id();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('parent_task_id')->nullable();
            $table->unsignedBigInteger('sprint_id')->nullable();
            $table->string('title');
            $table->longText('description')->nullable();
            $table->enum('type', $types)->default('user_story');
            $table->enum('status', ['not_started', 'in_progress', 'completed', 'overdue'])->default('not_started');
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->date('start_date');
            $table->date('due_date');
            $table->decimal('budget', 10, 2)->nullable();
            $table->date('completed_date')->nullable();
            $table->decimal('estimated_hours', 5, 2)->nullable();
            $table->decimal('actual_hours', 5, 2)->nullable();
            $table->tinyInteger('completion_percentage')->unsigned()->default(0);
            $table->json('files')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'status']);
            $table->index(['priority', 'due_date']);
            $table->index('created_by');
            $table->index('parent_task_id');
            $table->foreign('sprint_id')->references('id')->on('sprints')->onDelete('set null');
        });

        DB::statement('INSERT INTO tasks_type_rebuild_tmp SELECT * FROM tasks');

        Schema::drop('tasks');
        Schema::rename('tasks_type_rebuild_tmp', 'tasks');
    }
};
