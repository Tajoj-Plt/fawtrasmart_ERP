<?php

namespace App\Constants\planFeatures;

/**
 * خريطة شاشة «إدارة التطبيقات» على كتالوج باقة فوترة.
 *
 * ─────────────────────── مفتاحان لكل وحدة لا مفتاح ───────────────────────
 *
 * في النظام مِفتاحا تشغيلٍ منفصلان لكل وحدة، ولا يُغني أحدهما عن الآخر:
 *
 *   ١. `application_settings` — قرار المنشأة: «لا نستعمل هذه الوحدة».
 *      يملكه المسؤول ويقلبه متى شاء من شاشة التطبيقات.
 *   ٢. صلاحيات باقة فوترة — قرار البائع: «هذه الوحدة لم تُشترَ».
 *      لا يملكه المسؤول، ولا يُفتح إلا بترقية الباقة أو تجديدها.
 *
 * وكانت الشاشة تعرض الأول وحده: يقلب المسؤول المفتاح فيظنّ الوحدة فُتحت،
 * ثم يجد شاشاتها مقفلة من طبقةٍ لا تذكرها الشاشة أصلاً. فصارت الوحدة التي
 * لا تشملها الباقة تُعرض **مغطّاةً** برسالة «يجب تجديد أو ترقية الباقة»،
 * ومفتاحُها معطَّل — لا مخفيّة: إخفاؤها يُنتج السؤال «أين ذهبت الوحدة؟»
 * بلا جواب، وعرضُها مقفلةً هو نفسه جوابُه.
 *
 * ─────────────────────── من أين هذه المفاتيح؟ ───────────────────────
 *
 * من `plan.permissions` في لقطة الاشتراك حرفياً، لا من اجتهاد. ومفتاحٌ
 * لا يعرفه الكتالوج تعامله [\App\Services\Fawtra\SubscriptionGate::allows()]
 * **مسموحاً** — وهو الصواب لكتالوجٍ قد يسبق النظام أو يتأخّر عنه، لكنّ ثمنه
 * أن الخطأ الإملائي لا يُنتج عطلاً يُلاحَظ بل حارساً صامتاً لا يحرس شيئاً.
 * فلا يُكتب هنا مفتاحٌ لم يُقرأ من اللقطة نفسها.
 *
 * ─────────────────────── لماذا «تكفي واحدة»؟ ───────────────────────
 *
 * وحدة الشاشة أوسع من ميزة الباقة: «المبيعات» في هذه الشاشة تجمع الفواتير
 * وعروض الأسعار والمرتجعات والتقارير، ولكلٍّ مفتاحٌ مستقلّ عند فوترة. فباقةٌ
 * تشمل الفواتير دون المرتجعات وحدةٌ **مشمولة** لا ممنوعة — والمنع التفصيلي
 * موضعُه `plan:` على مسارات الشاشة الواحدة، لا هنا.
 *
 * ─────────────────────── وما لا مفتاح له ───────────────────────
 *
 * تسع وحدات لا يذكرها كتالوج فوترة بحرف: أوامر الشغل، الإيجارات، الحجوزات،
 * تتبّع الوقت، دورة العمل، العملاء ومتابعتهم وحضورهم، الرسائل النصّية،
 * المتجر الإلكتروني، إدارة المهام. تُترك بلا خريطة **عمداً** — مفتاحٌ
 * مخترَع لها يبدو حارساً وهو لا شيء، والصمت أصدق.
 */
class ApplicationPlanFeatures
{
    /**
     * مفتاح الوحدة في شاشة التطبيقات ← صلاحيات الباقة التي تفتحها.
     *
     * الوحدة مشمولة إن سمحت الباقة بواحدةٍ منها على الأقلّ. والوحدة
     * الغائبة عن هذه الخريطة مشمولة دائماً.
     *
     * @return array<string, array<int, string>>
     */
    public static function map(): array
    {
        return [
            /* ==================== المبيعات ==================== */

            'sales' => [
                'invoices', 'quotes', 'return_invoices', 'credit_notes',
                'periodic_invoices', 'payments', 'dashboard', 'reports',
            ],

            'pos' => [
                'pos_terminal', 'pos_sessions_mgmt', 'pos_settings', 'pos_reports',
            ],

            'installments_management' => ['installments'],

            'offers' => ['offers', 'gift_offers'],

            /* ==================== المخزون والمشتريات ==================== */

            'inventory_management' => [
                'stock_products', 'stock_categories', 'stock_storehouses',
                'stock_warehouse_permits', 'stock_inventory_adjustment', 'stock_reports',
            ],

            'manufacturing' => ['stock_manufacturing', 'stock_bom', 'stock_workstations'],

            'purchase_cycle' => [
                'purchases_invoices', 'purchases_orders', 'purchases_order_requests',
                'purchases_quotations', 'purchases_returns', 'purchases_payments',
                'purchases_suppliers', 'purchases_reports',
            ],

            /* ==================== الحسابات ==================== */

            'finance' => [
                'treasury', 'expenses', 'incomes', 'assets',
                'cost_centers', 'account_statement', 'account_reports',
            ],

            'general_accounts_journal_entries' => ['journal_entries', 'chart_of_accounts'],

            'cheque_cycle' => ['check_books', 'payable_cheques', 'received_cheques'],

            /* ==================== الموارد البشرية ==================== */

            'employees' => ['hr_employees', 'hr_employee_performance', 'hr_self_service'],

            'organizational_structure' => ['hr_departments', 'hr_job_titles', 'hr_functional_levels'],

            'employee_attendance' => [
                'hr_attendance', 'hr_attendance_devices', 'hr_shifts',
                'hr_attendance_rules', 'hr_barcode_attendance',
            ],

            'salaries' => [
                'hr_payroll', 'hr_payslips', 'hr_salary_items',
                'hr_contracts', 'hr_advances', 'hr_deductions',
            ],

            // «الطلبات» في قسم الموارد البشرية = طلبات الإجازة والمغادرة والسلف
            'orders' => [
                'hr_leaves', 'hr_leave_types', 'hr_leave_balances',
                'hr_holidays', 'hr_leave_permissions', 'hr_advances',
            ],

            /* ==================== العمليات ==================== */

            // مفاتيح لم تُعرَّف في فوترة بعد — مسموحةٌ إلى أن تُعرَّف، انظر
            // التنبيه في رأس `RealEstatePlanFeatures`
            'rental_management' => RealEstatePlanFeatures::all(),

            /* ==================== عامّة ==================== */

            'branches' => [
                'branches', 'branch_hierarchy', 'branch_switch',
                'branch_settings', 'branch_dashboard',
            ],
        ];
    }

    /**
     * صلاحيات الباقة التي تفتح وحدةً بعينها — أو قائمة فارغة لوحدةٍ لا
     * يذكرها الكتالوج.
     *
     * @return array<int, string>
     */
    public static function for(string $module): array
    {
        return static::map()[$module] ?? [];
    }
}
