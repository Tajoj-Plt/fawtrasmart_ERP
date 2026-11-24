<?php

namespace Modules\TaskManager\Services;

use App\Models\Task;
use App\Support\OperationLog;

/**
 * تغيير حالة المهمّة — نقطةٌ واحدة لِما كان مكتوباً مرّتين.
 *
 * `TaskController::updateStatus()` و`SprintController::updateTaskStatus()`
 * حملا نسخةً من المنطق نفسه كلٌّ على حدة: الأولى تسجّل التغيير، والثانية
 * لا تسجّله؛ وكلتاهما ترفع الحالة إلى الأب مستوىً واحداً ثم تقف، فتكتمل
 * القصّة بمهامّها ولا يكتمل الإبيك بقصصه.
 */
class TaskStatusService
{
    private const LOG_MODULE = 'TaskManager.Task';

    /** أقصى عمق للصعود — الهرم أربعة مستويات، والحدّ يقطع حلقةً في بياناتٍ معطوبة. */
    private const MAX_DEPTH = 6;

    /** @return bool هل تغيّرت الحالة فعلاً؟ */
    public function change(Task $task, string $status): bool
    {
        if ($task->status === $status) {
            return false;
        }

        $this->apply($task, $status, 'تغيّرت حالة المهمّة **%s** من **%s** إلى **%s**');
        $this->propagate($task, $status);

        return true;
    }

    /**
     * الأب يتبع أبناءه حين يُجمعون على حالةٍ واحدة — صعوداً حتى يختلفوا.
     */
    private function propagate(Task $task, string $status): void
    {
        $node = $task;

        for ($depth = 0; $depth < self::MAX_DEPTH && $node->parent_task_id; $depth++) {
            $parent = Task::find($node->parent_task_id);

            if (! $parent || $parent->status === $status) {
                return;
            }

            $unanimous = ! Task::where('parent_task_id', $parent->id)->where('status', '!=', $status)->exists();

            if (! $unanimous) {
                return;
            }

            $this->apply($parent, $status, 'تبعت **%s** أبناءها فتغيّرت حالتها من **%s** إلى **%s**');
            $node = $parent;
        }
    }

    private function apply(Task $task, string $status, string $template): void
    {
        $previous = $task->status;
        $task->update(['status' => $status]);

        OperationLog::record(
            'status_changed',
            self::LOG_MODULE,
            $task,
            sprintf($template, $task->title, $previous, $status),
            ['old' => ['status' => $previous], 'new' => ['status' => $status]],
        );
    }
}
