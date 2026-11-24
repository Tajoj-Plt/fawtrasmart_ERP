<?php

namespace Modules\TaskManager\Services;

use App\Helpers\TaskSettingsHelper;
use App\Models\notifications;
use App\Models\Task;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * تنبيهات المهامّ داخل النظام.
 *
 * شاشة «التنبيهات الافتراضية» في إعدادات الوحدة كانت تحفظ ستّة مفاتيح لا
 * يقرؤها أحد: لا سطرَ في الوحدة يرسل تنبيهاً. هنا يُقرأ منها ما له حدثٌ
 * فعلي — الإسناد والتعليق وتغيير الحالة.
 *
 * التنبيه لا يُسقط العملية: فشلُه يُكتب في السجلّ ويمضي الحفظ.
 */
class TaskNotifier
{
    /** @param  array<int, int|string>  $userIds  من أُسندت إليهم المهمّة الآن (لا من كانوا عليها) */
    public function assigned(Task $task, array $userIds): void
    {
        $this->send('task_assigned', $userIds, 'أُسندت إليك مهمّة', $task->title);
    }

    public function commented(Task $task, string $excerpt): void
    {
        $this->send('comment_added', $this->audience($task), 'تعليق جديد على: '.$task->title, Str::limit($excerpt, 150));
    }

    public function statusChanged(Task $task): void
    {
        $this->send('status_changed', $this->audience($task), 'تغيّرت حالة: '.$task->title, 'الحالة الآن: '.$task->status_label);
    }

    /** المكلَّفون بالمهمّة ومنشئُها. */
    private function audience(Task $task): array
    {
        return $task->assignedUsers()->pluck('users.id')->push($task->created_by)->filter()->all();
    }

    /** @param  array<int, int|string>  $userIds */
    private function send(string $event, array $userIds, string $title, string $description): void
    {
        try {
            $enabled = TaskSettingsHelper::getSetting(null, 'notifications', []);

            if (! data_get($enabled, $event, true)) {
                return;
            }

            $actorId = Auth::id();
            $receivers = array_unique(array_filter(array_map('intval', $userIds), fn ($id) => $id && $id !== (int) $actorId));

            foreach ($receivers as $receiverId) {
                notifications::create([
                    'user_id' => $actorId,
                    'receiver_id' => $receiverId,
                    'type' => 'task',
                    'title' => $title,
                    'description' => $description,
                ]);
            }
        } catch (Throwable $e) {
            Log::warning('تعذّر إرسال تنبيه مهمّة: '.$e->getMessage(), ['event' => $event]);
        }
    }
}
