<?php

namespace App\Support;

use App\Constants\permissions\PermissionCatalog;

/**
 * تسميات الصلاحيات وتصنيفها للعرض — واجهةٌ رقيقة فوق
 * [App\Constants\permissions\PermissionCatalog].
 *
 * كانت هنا خرائطُ بادئاتٍ مكتوبة بيدها (`'inv' => 'المخزون والمشتريات'`)
 * تخمّن القسم من أول مقطعٍ في الاسم. والتخمين يخطئ حيث تكذب البادئة:
 * `inv_manage_suppliers_add` مشترياتٌ لا مخزون، و`Delete_Client` عملاءُ لا
 * «Delete»، و`employee_orders_management` طلباتٌ لا موارد بشرية. صار القسم
 * معلومةً مصرَّحاً بها في الكتالوج، والخرائط حُذفت.
 *
 * وما لا يعرفه الكتالوج — صلاحيةٌ أُنشئت يدوياً من الشاشة ولم تُضف إلى
 * كتالوج قسمها — يُعرَض باسمه محسَّناً للقراءة تحت قسم «غير مصنَّفة»؛
 * علامةٌ ظاهرة على أن سطراً ينقص، لا خطأٌ صامت.
 */
class PermissionLabels
{
    /** مفتاح القسم الذي تسقط فيه الصلاحيات التي لا كتالوج لها. */
    public const UNCLASSIFIED = 'unclassified';

    /** التسمية العربية لصلاحية — أو اسمها محسَّناً للقراءة إن لم تُصنَّف. */
    public static function describe(string $permission): string
    {
        return PermissionCatalog::describe($permission)
            ?? trim(str_replace(['_', '.'], ' ', $permission));
    }

    /** مفتاح قسم الصلاحية. */
    public static function group(string $permission): string
    {
        return PermissionCatalog::locate($permission)['module'] ?? self::UNCLASSIFIED;
    }

    /** التسمية العربية لقسمٍ بمفتاحه. */
    public static function groupLabel(string $moduleKey): string
    {
        return PermissionCatalog::module($moduleKey)['label'] ?? 'صلاحيات غير مصنَّفة';
    }

    /** السطر التوضيحي لقسمٍ بمفتاحه. */
    public static function groupDescription(string $moduleKey): string
    {
        return PermissionCatalog::module($moduleKey)['description']
            ?? 'صلاحيات موجودة في الجدول ولم تُضف بعد إلى كتالوج قسمها.';
    }

    /** أيقونة feather (بلا البادئة) لقسمٍ بمفتاحه. */
    public static function groupIcon(string $moduleKey): string
    {
        return PermissionCatalog::module($moduleKey)['icon'] ?? 'help-circle';
    }

    /** التسمية العربية لقسم صلاحيةٍ بعينها. */
    public static function moduleLabelOf(string $permission): string
    {
        return PermissionCatalog::locate($permission)['module_label'] ?? 'صلاحيات غير مصنَّفة';
    }
}
