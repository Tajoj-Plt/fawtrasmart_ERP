<?php

namespace App\Support\TaskManager;

/**
 * مفردات المهمّة الثابتة — ما تقبله أعمدة `tasks` فعلاً.
 *
 * `tasks.status` و`tasks.priority` و`tasks.type` أعمدة `enum`، والتقارير
 * ولوحة المؤشّرات ومنطق الإقفال كلّها تسأل عن `completed` و`overdue`
 * بالاسم. وكانت الإعدادات تعرض مفرداتٍ أخرى (`todo`, `in_review`, `done`,
 * `blocked`, `lowest`, `story`…) لا يقبلها العمود: فتظهر اللوحة بأعمدةٍ لا
 * تقع فيها مهمّةٌ واحدة، وتختفي المهامّ «لم تبدأ» و«مكتملة» منها.
 *
 * فالمعرّفات من هنا وحده، والإعدادات تغيّر **الاسم واللون والأيقونة**
 * لمعرّفٍ قائم ولا تضيف معرّفاً جديداً. إضافة حالةٍ فعلية تبدأ بهجرةٍ
 * على العمود ثم سطرٍ هنا.
 */
class TaskVocabulary
{
    public const STATUS_NOT_STARTED = 'not_started';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_OVERDUE = 'overdue';

    /** @return array<int, array<string, mixed>> */
    public static function statuses(): array
    {
        return [
            ['id' => 'not_started', 'name' => 'لم تبدأ', 'color' => '#9E9E9E', 'type' => 'todo'],
            ['id' => 'in_progress', 'name' => 'قيد التنفيذ', 'color' => '#FF9800', 'type' => 'in_progress'],
            ['id' => 'completed', 'name' => 'مكتملة', 'color' => '#4CAF50', 'type' => 'done'],
            ['id' => 'overdue', 'name' => 'متأخرة', 'color' => '#F44336', 'type' => 'blocked'],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public static function priorities(): array
    {
        return [
            ['id' => 'low', 'name' => 'منخفضة', 'color' => '#4CAF50', 'level' => 1],
            ['id' => 'medium', 'name' => 'متوسطة', 'color' => '#FF9800', 'level' => 2],
            ['id' => 'high', 'name' => 'عالية', 'color' => '#F44336', 'level' => 3],
            ['id' => 'urgent', 'name' => 'عاجلة', 'color' => '#D32F2F', 'level' => 4],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public static function types(): array
    {
        return [
            ['id' => 'component', 'name' => 'مكون', 'icon' => 'feather icon-box', 'color' => '#2196F3'],
            ['id' => 'epic', 'name' => 'إبيك', 'icon' => 'feather icon-bookmark', 'color' => '#9C27B0'],
            ['id' => 'user_story', 'name' => 'قصة مستخدم', 'icon' => 'feather icon-book', 'color' => '#4CAF50'],
            ['id' => 'task', 'name' => 'مهمة', 'icon' => 'feather icon-check-square', 'color' => '#3F51B5'],
            ['id' => 'sub_task', 'name' => 'مهمة فرعية', 'icon' => 'feather icon-list', 'color' => '#FF9800'],
            ['id' => 'bug', 'name' => 'خطأ برمجي', 'icon' => 'feather icon-alert-octagon', 'color' => '#F44336'],
        ];
    }

    /** @return array<int, string> */
    public static function statusIds(): array
    {
        return array_column(self::statuses(), 'id');
    }

    /** @return array<int, string> */
    public static function priorityIds(): array
    {
        return array_column(self::priorities(), 'id');
    }

    /** @return array<int, string> */
    public static function typeIds(): array
    {
        return array_column(self::types(), 'id');
    }

    /** @return array<int, array<string, mixed>> */
    public static function defaults(string $group): array
    {
        return match ($group) {
            'statuses' => self::statuses(),
            'priorities' => self::priorities(),
            'types', 'task_types' => self::types(),
            default => [],
        };
    }

    /**
     * المفردات الثابتة وعليها ما خصّصه المستخدم من اسمٍ ولونٍ وأيقونة.
     *
     * ما حُفظ بمعرّفٍ خارج المفردات يُهمل: هو من الشاشة القديمة التي كانت
     * تشتقّ المعرّف من الاسم، ولا يقبله العمود.
     *
     * @param  mixed  $saved  ما في `task_settings.setting_value`
     * @return array<int, array<string, mixed>>
     */
    public static function merge(string $group, mixed $saved): array
    {
        $overrides = [];

        foreach (is_array($saved) ? $saved : [] as $item) {
            if (is_array($item) && isset($item['id'])) {
                $overrides[(string) $item['id']] = $item;
            }
        }

        return array_map(function (array $default) use ($overrides) {
            $custom = $overrides[$default['id']] ?? [];

            foreach (['name', 'color', 'icon'] as $field) {
                if (! empty($custom[$field]) && array_key_exists($field, $default)) {
                    $default[$field] = $custom[$field];
                }
            }

            return $default;
        }, self::defaults($group));
    }
}
