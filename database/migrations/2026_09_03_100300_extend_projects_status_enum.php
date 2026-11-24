<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * يوسّع enum حالة المشروع من (new, in_progress, completed, on_hold) إلى
 * المجموعة الكاملة المطلوبة (planning, active, on_hold, completed,
 * cancelled, archived)، مع إعادة تسمية القيم الحالية للمطابقة الدلالية:
 * new -> planning, in_progress -> active. on_hold/completed تبقيان كما هما.
 *
 * القيم الجديدة (planning/active) غير مقبولة أصلاً في قيد enum/CHECK
 * الحالي، لذا لا يمكن إعادة التسمية عبر UPDATE قائم بذاته قبل توسيع القيد؛
 * التوسيع وإعادة التسمية يجب أن يحدثا معاً. في SQLite يتم ذلك ضمن نفس
 * خطوة إعادة بناء الجدول (CASE داخل INSERT...SELECT)، وفي MySQL بتوسيع
 * الـ enum أولاً ليشمل القيمتين معاً، ثم UPDATE، ثم تضييقه إلى المجموعة
 * النهائية. نفس نمط إعادة البناء الآمن المتّبع في
 * 2026_09_01_130100_add_task_type_to_tasks_type_enum.php لجدول tasks.
 */
return new class extends Migration
{
    private const OLD_STATUSES = ['new', 'in_progress', 'completed', 'on_hold'];

    private const NEW_STATUSES = ['planning', 'active', 'on_hold', 'completed', 'cancelled', 'archived'];

    private const UNION_STATUSES = ['new', 'in_progress', 'completed', 'on_hold', 'planning', 'active', 'cancelled', 'archived'];

    public function up(): void
    {
        if (! Schema::hasTable('projects')) {
            return;
        }

        if (DB::connection()->getDriverName() === 'sqlite') {
            $this->rebuildSqliteProjectsTable('projects_status_up_tmp', self::NEW_STATUSES, 'planning', [
                'new' => 'planning',
                'in_progress' => 'active',
            ]);

            return;
        }

        DB::statement('ALTER TABLE `projects` MODIFY `status` ENUM(\''.implode("','", self::UNION_STATUSES).'\') NOT NULL DEFAULT \'planning\'');
        DB::table('projects')->where('status', 'new')->update(['status' => 'planning']);
        DB::table('projects')->where('status', 'in_progress')->update(['status' => 'active']);
        DB::statement('ALTER TABLE `projects` MODIFY `status` ENUM(\''.implode("','", self::NEW_STATUSES).'\') NOT NULL DEFAULT \'planning\'');
    }

    public function down(): void
    {
        if (! Schema::hasTable('projects')) {
            return;
        }

        if (DB::connection()->getDriverName() === 'sqlite') {
            $this->rebuildSqliteProjectsTable('projects_status_down_tmp', self::OLD_STATUSES, 'new', [
                'planning' => 'new',
                'active' => 'in_progress',
                'cancelled' => 'on_hold',
                'archived' => 'on_hold',
            ]);

            return;
        }

        DB::statement('ALTER TABLE `projects` MODIFY `status` ENUM(\''.implode("','", self::UNION_STATUSES).'\') NOT NULL DEFAULT \'new\'');
        DB::table('projects')->where('status', 'planning')->update(['status' => 'new']);
        DB::table('projects')->where('status', 'active')->update(['status' => 'in_progress']);
        DB::table('projects')->whereIn('status', ['cancelled', 'archived'])->update(['status' => 'on_hold']);
        DB::statement('ALTER TABLE `projects` MODIFY `status` ENUM(\''.implode("','", self::OLD_STATUSES).'\') NOT NULL DEFAULT \'new\'');
    }

    /**
     * @param  array<string,string>  $remap  old value => new value, applied via SQL CASE during the copy
     *
     * Uses a distinct temp-table name per caller (up vs down): SQLite does not
     * rename a table's auto-generated index names when the table itself is
     * renamed, so reusing one fixed temp-table name across both directions
     * would collide with an index left behind by a previous run of the other
     * direction.
     */
    private function rebuildSqliteProjectsTable(string $tmpTable, array $statuses, string $default, array $remap): void
    {
        Schema::dropIfExists($tmpTable);

        Schema::create($tmpTable, function (Blueprint $table) use ($statuses, $default) {
            $table->id();
            $table->unsignedBigInteger('workspace_id')->nullable();
            $table->string('title');
            $table->longText('description')->nullable();
            $table->enum('status', $statuses)->default($default);
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->enum('management_type', ['manual', 'scrum'])->default('scrum');
            $table->decimal('budget', 10, 2)->nullable();
            $table->decimal('cost', 10, 2)->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->date('actual_end_date')->nullable();
            $table->tinyInteger('progress_percentage')->unsigned()->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'status']);
            $table->index(['priority', 'created_by']);
        });

        $statusCase = 'CASE status ';
        foreach ($remap as $from => $to) {
            $statusCase .= "WHEN '{$from}' THEN '{$to}' ";
        }
        $statusCase .= 'ELSE status END';

        DB::statement(
            "INSERT INTO {$tmpTable}
             (id, workspace_id, title, description, status, priority, management_type,
              budget, cost, start_date, end_date, actual_end_date, progress_percentage,
              created_by, created_at, updated_at)
             SELECT id, workspace_id, title, description, {$statusCase}, priority, management_type,
                    budget, cost, start_date, end_date, actual_end_date, progress_percentage,
                    created_by, created_at, updated_at
             FROM projects"
        );

        Schema::drop('projects');
        Schema::rename($tmpTable, 'projects');
    }
};
