<?php

namespace App\Constants\permissions;

/**
 * الأساس المشترك لكتالوجات الصلاحيات.
 *
 * كل كتالوج يصف **قسماً** واحداً من النظام (`module()`) ويقسّمه إلى
 * **مجموعات** (`groups()`)، ولكلٍّ من الثلاثة — القسم والمجموعة والصلاحية —
 * تسميةٌ عربية وسطرٌ توضيحي. هذا هو المصدر الوحيد الذي تُملأ منه أعمدة
 * التصنيف في جدول `permissions` (انظر [App\Console\Commands\SyncPermissionCatalog])،
 * ومنه تبني شاشةُ الأدوار شجرتها.
 *
 * **شكل `groups()`** — المفتاح مُعرّف المجموعة داخل القسم، والقيمة:
 *
 *   'label'       اسم المجموعة العربي            (إلزامي)
 *   'description' سطرٌ يشرح ما تحكمه المجموعة    (إلزامي)
 *   'icon'        أيقونة feather بلا البادئة     (اختياري)
 *   'controller'  المتحكّم/المتحكّمات التي تحرسها (اختياري — توثيقٌ فقط)
 *   'permissions' [اسم الصلاحية => تسميتها العربية]
 *
 * `all()` و`describe()` و`locate()` مشتقّةٌ من `groups()` — لا تُكتب في
 * الأبناء. الابن يكتب `module()` و`groups()` فقط.
 */
abstract class BasePermissionCatalog
{
    public const GUARD = 'web';

    /**
     * تعريف القسم.
     *
     * key         مُعرّفه في عمود `module`
     * label       اسمه العربي
     * description سطرٌ يشرح نطاقه
     * icon        أيقونة feather
     * order       ترتيب ظهوره بين الأقسام
     *
     * @return array{key:string, label:string, description:string, icon:string, order:int}
     */
    abstract public static function module(): array;

    /**
     * مجموعات القسم.
     *
     * @return array<string, array{label:string, description:string, icon?:string, controller?:string, permissions:array<string,string>}>
     */
    abstract public static function groups(): array;

    /**
     * كل أسماء صلاحيات القسم مسطّحةً.
     *
     * @return array<int, string>
     */
    public static function all(): array
    {
        $names = [];

        foreach (static::groups() as $group) {
            foreach (array_keys($group['permissions']) as $name) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /** الوصف العربي لصلاحية — أو null إن لم تكن من هذا القسم. */
    public static function describe(string $permission): ?string
    {
        foreach (static::groups() as $group) {
            if (isset($group['permissions'][$permission])) {
                return $group['permissions'][$permission];
            }
        }

        return null;
    }

    /**
     * موضع الصلاحية كاملاً: قسمها ومجموعتها وتسميتها وترتيبها.
     *
     * هذا ما يكتبه أمر المزامنة في أعمدة `permissions`، فتصير الشاشات
     * قادرةً على التقسيم باستعلامٍ واحد بلا تحميل الكتالوجات.
     *
     * @return array{module:string, module_label:string, group_key:string, group_label:string, label:string, sort_order:int}|null
     */
    public static function locate(string $permission): ?array
    {
        $module = static::module();
        $sort = 0;

        foreach (static::groups() as $groupKey => $group) {
            foreach ($group['permissions'] as $name => $label) {
                $sort++;

                if ($name !== $permission) {
                    continue;
                }

                return [
                    'module' => $module['key'],
                    'module_label' => $module['label'],
                    'group_key' => $groupKey,
                    'group_label' => $group['label'],
                    'label' => $label,
                    'sort_order' => $module['order'] * 10000 + $sort,
                ];
            }
        }

        return null;
    }
}
