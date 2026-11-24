<?php

namespace App\Constants\planFeatures;

/**
 * خريطة وحدة الموارد البشرية على كتالوج باقة فوترة — **المصدر الوحيد لأسمائها**.
 *
 * ─────────────────────── طبقتان لا واحدة ───────────────────────
 *
 * صلاحيات النظام ([\App\Constants\permissions\HrPermissions]، بنمط
 * `employees_add`) تقول **من** يفعل ماذا داخل المنشأة. وصلاحيات الباقة هنا
 * (بنمط `hr_employees`) تقول **ماذا اشترت المنشأة أصلاً**. لا يتداخلان:
 * مديرُ موارد بشرية يملك كل صلاحيات النظام لا يُضيف موظفاً سادساً في باقةٍ
 * سقفُها خمسة.
 *
 * ─────────────────────── مزلقٌ خاصٌّ بهذه الوحدة ───────────────────────
 *
 * أسماء صلاحيات النظام هنا **بنمط الشرطة السفلية** (`employees_add`) لا
 * النقطي الذي في المخزون والحسابات (`stock.products.view`). وأسماء صلاحيات
 * الباقة كذلك بالشرطة السفلية (`hr_employees`). فالنمطان متشابهان بصرياً
 * ويسهل تمرير أحدهما مكان الآخر — و`SubscriptionGate` تعامل المجهول
 * «مسموحاً» فلا يظهر خطأ.
 *
 * ولهذا: **كل ثابتٍ هنا يبدأ بـ`hr_`، وكل صلاحية نظامٍ لا تبدأ به.** وهي
 * علامةٌ تكفي لتمييزهما بالنظر.
 *
 * ─────────────────────── لماذا «الموظفون» هو الحدّ الحاكم؟ ───────────────
 *
 * لأن كل ميزةٍ أخرى في القسم تُضرب فيه: القسيمة تُولَّد لكل موظف، وسجلّ
 * الحضور يُنشأ لكل موظفٍ كل يوم، والعقد والسلفة مرتبطان بموظف. فسقفُ
 * الموظفين هو سقفُ الوحدة عملياً — وهو تسعير «لكل مقعد» المعروف في أنظمة
 * الموارد البشرية.
 */
class HrPlanFeatures
{
    /* ==================== صلاحيات الباقة ==================== */

    /* ── الموظفون ── */

    public const EMPLOYEES = 'hr_employees';

    public const PERFORMANCE = 'hr_employee_performance';

    public const SELF_SERVICE = 'hr_self_service';

    /* ── الهيكل التنظيمي ── */

    public const DEPARTMENTS = 'hr_departments';

    public const JOB_TITLES = 'hr_job_titles';

    public const FUNCTIONAL_LEVELS = 'hr_functional_levels';

    /* ── الحضور ── */

    public const ATTENDANCE = 'hr_attendance';

    public const ATTENDANCE_DEVICES = 'hr_attendance_devices';

    public const SHIFTS = 'hr_shifts';

    public const ATTENDANCE_RULES = 'hr_attendance_rules';

    public const BARCODE_ATTENDANCE = 'hr_barcode_attendance';

    /* ── الإجازات ── */

    public const LEAVES = 'hr_leaves';

    public const LEAVE_TYPES = 'hr_leave_types';

    public const LEAVE_BALANCES = 'hr_leave_balances';

    public const HOLIDAYS = 'hr_holidays';

    public const LEAVE_PERMISSIONS = 'hr_leave_permissions';

    /* ── الرواتب والعقود ── */

    public const PAYROLL = 'hr_payroll';

    public const PAYSLIPS = 'hr_payslips';

    public const SALARY_ITEMS = 'hr_salary_items';

    public const CONTRACTS = 'hr_contracts';

    public const ADVANCES = 'hr_advances';

    public const DEDUCTIONS = 'hr_deductions';

    /* ── التقارير ── */

    public const DASHBOARD = 'hr_dashboard';

    public const REPORTS = 'hr_reports';

    public const COMMISSIONS = 'hr_commissions';

    /**
     * كل صلاحيات الوحدة — تكفي واحدةٌ منها لفتح لوحتها وبند قائمتها الجامع.
     *
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [
            self::EMPLOYEES, self::PERFORMANCE, self::SELF_SERVICE,
            self::DEPARTMENTS, self::JOB_TITLES, self::FUNCTIONAL_LEVELS,
            self::ATTENDANCE, self::ATTENDANCE_DEVICES, self::SHIFTS,
            self::ATTENDANCE_RULES, self::BARCODE_ATTENDANCE,
            self::LEAVES, self::LEAVE_TYPES, self::LEAVE_BALANCES,
            self::HOLIDAYS, self::LEAVE_PERMISSIONS,
            self::PAYROLL, self::PAYSLIPS, self::SALARY_ITEMS,
            self::CONTRACTS, self::ADVANCES, self::DEDUCTIONS,
            self::DASHBOARD, self::REPORTS, self::COMMISSIONS,
        ];
    }

    /* ==================== مفاتيح حدود الاستخدام ==================== */

    /** سقفٌ متزامن: ملفات الموظفين — الحدّ الحاكم للوحدة كلّها. */
    public const LIMIT_EMPLOYEES = 'employees';

    /** سقفٌ متزامن: الأقسام في الهيكل التنظيمي. */
    public const LIMIT_DEPARTMENTS = 'departments';

    /** سقفٌ متزامن: أجهزة البصمة المربوطة. */
    public const LIMIT_DEVICES = 'attendance_devices';

    /** سقفٌ متزامن: الورديات المعرَّفة. */
    public const LIMIT_SHIFTS = 'shifts';

    /** سقفٌ متزامن: العقود السارية. */
    public const LIMIT_CONTRACTS = 'contracts';

    /** عدّادٌ شهري: قسائم الرواتب المولَّدة. */
    public const LIMIT_PAYSLIPS = 'payslips';

    /** عدّادٌ شهري: طلبات الإجازات. */
    public const LIMIT_LEAVE_REQUESTS = 'leave_requests';

    /** عدّادٌ شهري: طلبات السلف. */
    public const LIMIT_ADVANCE_REQUESTS = 'advance_requests';

    /*
        لا حدَّ لسجلّات الحضور — أُحيل إلى `LIMIT_EMPLOYEES`.

        كان `LIMIT_ATTENDANCE_RECORDS = 'attendance_records'` عدّاداً شهرياً
        يقيس طبقةَ «الجلسات» التي لم تعد تُكتب، فتجمّد عند رقمه. وقياسُه
        على سجلّ الحضور الحيّ يُنتج نحو ٢٢ سجلاً لكل موظفٍ شهرياً — ×٢٢
        أقربَ مفاتيح الوحدة إليه (`payslips`) — ويُخترق به أيُّ حدٍّ مُعايَرٍ
        على القديم.

        والكلفةُ تتبع عددَ الموظفين لا عددَ أيامهم، وأيامُ الحضور مشتقّةٌ
        منه حسابياً. فحدُّ الموظفين وحدَه يحكم، وهو مُلزَمٌ فعلاً.

        ولا يُوضَع مكانَه `attendance_days` في حارس الحدّ: `creating()` ترمي،
        وصفوفُه تُنشَأ على باب الفرع وفي كرون الليل — فنفادُ الحصّة يمنع
        الموظفَ من تسجيل حضوره. انظر [\App\Observers\PlanLimitObserver].
    */
}
