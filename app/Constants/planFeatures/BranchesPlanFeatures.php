<?php

namespace App\Constants\planFeatures;

/**
 * خريطة وحدة الفروع على كتالوج باقة فوترة — **المصدر الوحيد لهذه الأسماء**.
 *
 * ─────────────────────── طبقتان لا واحدة ───────────────────────
 *
 * صلاحيات النظام ([\App\Constants\permissions\BranchesPermissions]، بنمط
 * `branches.create`) تقول **من** يفعل ماذا داخل المنشأة. وصلاحيات الباقة هنا
 * (بنمط `branches`) تقول **ماذا اشترت المنشأة أصلاً**. لا يتداخلان: مديرٌ
 * يملك كل صلاحيات النظام لا يُنشئ فرعاً حادي عشر في باقةٍ سقفُها عشرة.
 *
 * ─────────────────────── مزلق الاسم المشترك ───────────────────────
 *
 * `branches` اسمٌ **لثلاثة أشياء** في هذا النظام، ولا يجوز الخلط بينها:
 *
 *   • `branches`        ← صلاحية باقة  (تُسأل بـ`allows()`)
 *   • `branches`        ← حدّ استخدام  (يُسأل بـ`liveLimit()`)
 *   • `branches.create` ← صلاحية نظام  (يُسأل بـ`hasPermission()`)
 *
 * والأولان متطابقا الاسم هنا — وهو ما يجعل تمرير أحدهما مكان الآخر لا
 * يُنتج عطلاً يُلاحَظ، لأن كلتا الخريطتين تعرفه. لكنّ `territory_groups`
 * تكشف الفخّ: هي صلاحيةٌ **وحدٌّ** باسمٍ واحد كذلك، بينما `branch_transfers`
 * حدٌّ بلا صلاحيةٍ تقابله، و`branch_switch` صلاحيةٌ بلا حدّ. فالثوابت
 * مفصولةٌ هنا كما في بقيّة الوحدات، ولا يُعاد استعمال أحدها مكان الآخر.
 *
 * ─────────────────────── الفرع والمجموعة صفّان في جدولٍ واحد ───────────────
 *
 * كلاهما في `branches` ويفرّقهما العمود `is_territory`: الفرع منفذُ عملٍ له
 * مخزون وخزينة وموظفون، والمجموعة عقدةٌ تنظيمية تجمع فروعاً. وحدّاهما
 * منفصلان لأن ثمنهما مختلف — فالعدّ يجب أن يُرشِّح بالعمود، وإلا عدّ
 * المجموعاتِ فروعاً فاستنفد سقفاً لم يُمسّ.
 */
class BranchesPlanFeatures
{
    /* ==================== صلاحيات الباقة ==================== */

    /** إدارة الفروع: القائمة والإنشاء والتحرير. */
    public const BRANCHES = 'branches';

    /** الشجرة الهرمية وإسناد الآباء بالجملة. */
    public const HIERARCHY = 'branch_hierarchy';

    /** مجموعات المناطق. */
    public const TERRITORY_GROUPS = 'territory_groups';

    /** التنقّل بين الفروع — صلاحيةٌ بلا حدّ يقابلها. */
    public const SWITCH = 'branch_switch';

    /** إعدادات الفرع ونسخها. */
    public const SETTINGS = 'branch_settings';

    /** دمج المجموعات ونقل العملاء. */
    public const MERGE = 'territory_merge';

    /** لوحة مؤشرات الفروع. */
    public const DASHBOARD = 'branch_dashboard';

    /** تصدير بيانات الفروع. */
    public const EXPORT = 'branch_export';

    /**
     * كل صلاحيات الوحدة — تكفي واحدةٌ منها لفتح لوحتها وبند قائمتها الجامع.
     *
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [
            self::BRANCHES,
            self::HIERARCHY,
            self::TERRITORY_GROUPS,
            self::SWITCH,
            self::SETTINGS,
            self::MERGE,
            self::DASHBOARD,
            self::EXPORT,
        ];
    }

    /* ==================== مفاتيح حدود الاستخدام ==================== */

    /** سقفٌ متزامن: الفروع (`is_territory = 0`). */
    public const LIMIT_BRANCHES = 'branches';

    /** سقفٌ متزامن: مجموعات المناطق (`is_territory = 1`). */
    public const LIMIT_TERRITORY_GROUPS = 'territory_groups';

    /** عدّادٌ شهري: عمليات الدمج والنقل وإسناد الآباء بالجملة. */
    public const LIMIT_TRANSFERS = 'branch_transfers';
}
