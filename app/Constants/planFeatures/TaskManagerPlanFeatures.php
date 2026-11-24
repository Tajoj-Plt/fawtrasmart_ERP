<?php

namespace App\Constants\planFeatures;

/**
 * خريطة وحدة إدارة المهام على كتالوج باقة فوترة — **المصدر الوحيد لهذه الأسماء**.
 *
 * ─────────────────────── اقرأ هذا قبل أن تعتمد عليه ───────────────────────
 *
 * **هذه المفاتيح ليست في كتالوج فوترة بعد.**
 *
 * [ApplicationPlanFeatures] يقول صراحةً إن «إدارة المهام» من الوحدات التسع
 * التي لا يذكرها الكتالوج بحرف، وإنها تُركت بلا خريطة عمداً. وذلك القرار
 * قائمٌ ولم يُنقض هنا: الوحدة ما زالت خارج `ApplicationPlanFeatures::map()`
 * فلا تُغطّى شاشتُها في «إدارة التطبيقات» برسالة ترقيةٍ كاذبة.
 *
 * وما يفعله هذا الملف شيءٌ آخر: يجمع الأسماء في موضعٍ واحد ويضعها على
 * المسارات، كي تصير الحراسة **مسألة سطرٍ في الكتالوج عند فوترة** لا مسألة
 * مراجعة ثلاث مئة مسار. وحتى يُضاف ذلك السطر:
 *
 *   [\App\Services\Fawtra\SubscriptionGate::allows()] تُعامل المفتاح المجهول
 *   **مسموحاً**. فكل `plan:` هنا يمرّ اليوم، ولا يمنع شيئاً، ولا يُنتج عطلاً
 *   يُلاحَظ. هو تمديدُ أنابيب لا حارس.
 *
 * فلا يُقال «الوحدة محروسة بالباقة» استناداً إلى هذا الملف. الحارس العامل
 * فعلاً على هذه الوحدة اليوم اثنان لا ثالث لهما:
 *
 *   ١. `module:tasks` — مفتاح المنشأة في `application_settings`.
 *   ٢. `permission.role:tasks.*` — صلاحيات النظام من [\App\Constants\permissions\TaskManagerPermissions].
 *
 * ─────────────────────── وحين تصل المفاتيح من فوترة ───────────────────────
 *
 * تُصحَّح الأسماء أدناه لتطابق `plan.permissions` في لقطة الاشتراك **حرفياً**،
 * وتُضاف `'tasks' => [...]` إلى `ApplicationPlanFeatures::map()`، وتُضاف
 * أقسام الاستهلاك إلى [\App\Services\Fawtra\UsageReporter]. لا يُخمَّن حرف:
 * اسمٌ مختلفٌ بحرفٍ يبقى «مجهولاً» فيبقى مسموحاً، والخطأ صامت.
 */
class TaskManagerPlanFeatures
{
    /* ==================== صلاحيات الباقة ==================== */

    /** مساحات العمل والمشاريع — نواة الوحدة. */
    public const PROJECTS = 'tasks_projects';

    /** لوحة كانبان وقائمة الانتظار ولوحة المؤشّرات. */
    public const BOARD = 'tasks_board';

    /** السبرنتات — منهجية سكرم. المشاريع اليدوية لا تحتاجها. */
    public const SPRINTS = 'tasks_sprints';

    /** تقارير المهام والمشاريع وأداء الفرق. */
    public const REPORTS = 'tasks_reports';

    /**
     * كل صلاحيات باقة إدارة المهام — يكفي واحدةٌ منها لفتح الوحدة.
     *
     * الشرط «واحدةٌ على الأقل» لا «كلّها»، كما في [StockPlanFeatures]:
     * باقةٌ تشمل المشاريع دون السبرنتات وحدةٌ مشمولة، والمنع التفصيلي
     * موضعُه `plan:` على مسار الشاشة الواحدة.
     *
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [
            self::PROJECTS,
            self::BOARD,
            self::SPRINTS,
            self::REPORTS,
        ];
    }

    /* ==================== مفاتيح حدود الاستخدام ==================== */

    /*
     * هذه أيضاً غير مُتابَعة عند فوترة بعد — لا قسم `tasks` في
     * [\App\Services\Fawtra\UsageReporter]. فلا تُوضع `plan.limit:` على
     * مسارٍ استناداً إليها: [\App\Http\Middleware\EnforcePlanLimit] لا
     * تعرف كيف تعدّ ما لا قسم له، وحدٌّ لا يُعدّ لا يُلزِم.
     *
     * تُركت معرَّفةً لأن أسماءها هي ما سيُطلب من فوترة إضافته، ومكانُ
     * الطلب موضعٌ واحد لا ذاكرةُ من كتب الشيفرة.
     */

    /** سقفٌ متزامن: عدد مساحات العمل. */
    public const LIMIT_WORKSPACES = 'workspaces';

    /** سقفٌ متزامن: عدد المشاريع. */
    public const LIMIT_PROJECTS = 'projects';

    /** عدّادٌ شهري: المهام المُنشأة. */
    public const LIMIT_TASKS = 'tasks';

    /** عدّادٌ شهري: السبرنتات المُنشأة. */
    public const LIMIT_SPRINTS = 'sprints';

    /**
     * الحدّ المرتبط بكل صلاحية باقة — لعرض الشارة في شاشة الميزة نفسها.
     */
    public static function limitOf(string $permission): ?string
    {
        return [
            self::PROJECTS => self::LIMIT_PROJECTS,
            self::BOARD => self::LIMIT_TASKS,
            self::SPRINTS => self::LIMIT_SPRINTS,
        ][$permission] ?? null;
    }
}
