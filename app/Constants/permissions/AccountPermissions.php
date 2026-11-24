<?php

namespace App\Constants\permissions;

/**
 * كتالوج صلاحيات وحدة الحسابات والمالية — المصدر الوحيد لأسمائها.
 *
 * **لماذا كتالوج مستقل؟**
 *
 * حماية الوحدة اليوم متناثرة وناقصة: أربعة متحكّمات فقط من عشرين تحمل
 * `$this->middleware('permission:…')` في بانيها، بأسماء من النظام القديم
 * (`finance_view_all_receipts`, `g_a_d_r_add_new_assets`) لا نظام لها —
 * بعضها بصيغة `all|own` مدمجة في الاسم، وبعضها بلا صيغة. وستّة عشر متحكّماً
 * بلا حماية إطلاقاً: شجرة الحسابات، والقيود، والخزائن، والشيكات، ومراكز
 * التكلفة، والتقارير المالية كلّها — يفتحها كل من دخل النظام.
 *
 * النمط هنا نقطي `account.journal.create` كما في [SalesPermissions]، ولا
 * يتصادم مع الأسماء القديمة (نقطة مقابل شرطة سفلية) فيتعايش النظامان أثناء
 * الانتقال.
 *
 * **محورا الصلاحية منفصلان** كما في المبيعات: العملية تحدّد *ماذا يفعل*،
 * وصلاحيات النطاق في [DataScopePermissions] تحدّد *على أي بيانات* —
 * فلا تُضرَب كل عملية في كل نطاق كما في `finance_view_all_receipts` مقابل
 * `finance_view_own_receipts`.
 *
 * **التغطية:** كل فعلٍ عامّ في متحكّمات الوحدة له صلاحية. وما ليس فعلاً
 * مستقلاً — مساعدات البحث والقوائم المنسدلة (`searchAccounts`,
 * `getParents`, `getNextCode`) — يندرج تحت صلاحية عرض شاشته، فلا معنى
 * لحجب قائمةٍ منسدلة عمّن يُسمح له بالشاشة نفسها.
 *
 * **العمليات الخطرة معزولة**: تصفير الحسابات، وحذف القيود بالجملة، وإعادة
 * ترقيم أكواد العملاء — لكلٍّ منها صلاحيتها هي، فلا يرثها من مُنح «تعديل
 * شجرة الحسابات».
 */
class AccountPermissions extends BasePermissionCatalog
{
    /** بادئة أسماء صلاحيات هذه الوحدة — يُبنى عليها الفحص والعرض. */
    public const PREFIX = 'account.';

    public static function module(): array
    {
        return [
            'key' => 'account',
            'label' => 'الحسابات والمالية',
            'description' => 'شجرة الحسابات والقيود والخزائن والشيكات والأصول ومراكز التكلفة والتقارير المالية.',
            'icon' => 'book',
            'order' => 50,
        ];
    }

    /**
     * صلاحيات العمليات مجموعةً بالشاشة.
     *
     * المفتاح اسم الصلاحية، والقيمة وصفها العربي المعروض في شاشة الأدوار.
     *
     * @return array<string, array{label: string, controller: string, permissions: array<string, string>}>
     */
    public static function groups(): array
    {
        return [
            // ═══════════════ شجرة الحسابات ═══════════════
            'chart' => [
                'label' => 'شجرة الحسابات',
                'description' => 'شجرة الحسابات وبنيتها ومستوياتها.',
                'icon' => 'git-merge',
                'controller' => 'AccountsChartController + AccountController',
                'permissions' => [
                    'account.chart.view' => 'عرض شجرة الحسابات',
                    'account.chart.show' => 'عرض تفاصيل حساب',
                    'account.chart.create' => 'إنشاء حساب',
                    'account.chart.edit' => 'تعديل حساب',
                    'account.chart.delete' => 'حذف حساب',
                    'account.chart.entries' => 'عرض قيود الحساب',
                    'account.chart.general_ledger' => 'عرض دفتر الأستاذ العام',
                    'account.chart.recalculate' => 'إعادة احتساب أرصدة الحسابات',
                ],
            ],

            // العمليات التي تمسّ أرصدةً وقيوداً محفوظة — لا تُمنح مع التعديل
            'chart_maintenance' => [
                'label' => 'صيانة شجرة الحسابات (عمليات خطرة)',
                'description' => 'عملياتٌ تمسّ بنية الشجرة كلها — دمجٌ وإعادة ترقيمٍ وحذف.',
                'icon' => 'alert-triangle',
                'controller' => 'AccountsChartController',
                'permissions' => [
                    'account.chart.migrate_opening_balances' => 'ترحيل الأرصدة الافتتاحية للعملاء',
                    'account.chart.create_opening_entries' => 'توليد قيود افتتاحية من الأرصدة',
                    'account.chart.reorder_client_codes' => 'إعادة ترقيم أكواد العملاء',
                    'account.chart.reset_revenue_expense' => 'تصفير حسابات الإيرادات والمصروفات',
                    'account.chart.reset_treasuries' => 'تصفير حسابات الخزائن',
                    'account.chart.delete_entries_bulk' => 'حذف القيود بالجملة',
                    'account.chart.sync_invoice_entries' => 'مزامنة قيود الفواتير الناقصة',
                ],
            ],

            // ═══════════════ القيود ═══════════════
            'journal' => [
                'label' => 'قيود اليومية',
                'description' => 'قيود اليومية: تحريرها واعتمادها وعكسها.',
                'icon' => 'book-open',
                'controller' => 'JournalEntryController',
                'permissions' => [
                    'account.journal.view' => 'عرض القيود',
                    'account.journal.show' => 'عرض تفاصيل قيد',
                    'account.journal.create' => 'إنشاء قيد يدوي',
                    'account.journal.edit' => 'تعديل قيد',
                    'account.journal.delete' => 'حذف قيد',
                    'account.journal.modifications' => 'عرض سجلّ تعديلات القيود',
                    'account.journal.print' => 'طباعة وتصدير القيد',
                ],
            ],

            // ═══════════════ كشف الحساب ═══════════════
            'statement' => [
                'label' => 'كشف الحساب',
                'description' => 'كشف حساب أي حسابٍ في الشجرة.',
                'icon' => 'file-text',
                'controller' => 'AccountStatementController',
                'permissions' => [
                    'account.statement.view' => 'عرض كشف الحساب',
                    'account.statement.export' => 'تصدير كشف الحساب PDF',
                ],
            ],

            // ═══════════════ سندات الصرف ═══════════════
            'expenses' => [
                'label' => 'سندات الصرف',
                'description' => 'سندات الصرف وقيودها المحاسبية.',
                'icon' => 'file-minus',
                'controller' => 'Finance/ExpensesController',
                'permissions' => [
                    'account.expenses.view' => 'عرض سندات الصرف',
                    'account.expenses.show' => 'عرض تفاصيل سند صرف',
                    'account.expenses.create' => 'إنشاء سند صرف',
                    'account.expenses.edit' => 'تعديل سند صرف',
                    'account.expenses.delete' => 'حذف سند صرف',
                    'account.expenses.cancel' => 'إلغاء سند صرف',
                    'account.expenses.print' => 'طباعة سند الصرف',
                    // بدونها: «لعميل» = عملاء خطّ السير، و«عام» = حسابات التصنيف المختار وحدها
                    'account.expenses.all_accounts' => 'إظهار كل الحسابات في سند الصرف (بلا تقييد بخط السير وحسابات التصنيف)',
                ],
            ],

            // ═══════════════ سندات القبض ═══════════════
            'incomes' => [
                'label' => 'سندات القبض',
                'description' => 'سندات القبض وقيودها المحاسبية.',
                'icon' => 'file-plus',
                'controller' => 'Finance/IncomesController',
                'permissions' => [
                    'account.incomes.view' => 'عرض سندات القبض',
                    'account.incomes.view_assigned_clients' => 'عرض سندات قبض العملاء المسندين إليه',
                    'account.incomes.show' => 'عرض تفاصيل سند قبض',
                    'account.incomes.create' => 'إنشاء سند قبض',
                    'account.incomes.edit' => 'تعديل سند قبض',
                    'account.incomes.delete' => 'حذف سند قبض',
                    'account.incomes.cancel' => 'إلغاء سند قبض',
                    'account.incomes.print' => 'طباعة سند القبض',
                ],
            ],

            // ═══════════════ الخزائن والبنوك ═══════════════
            'treasury' => [
                'label' => 'الخزائن والحسابات البنكية',
                'description' => 'الخزائن والحسابات البنكية وأرصدتها.',
                'icon' => 'briefcase',
                'controller' => 'Finance/TreasuryController',
                'permissions' => [
                    'account.treasury.view' => 'عرض الخزائن',
                    'account.treasury.show' => 'عرض حركة الخزينة',
                    'account.treasury.create' => 'إنشاء خزينة',
                    'account.treasury.edit' => 'تعديل خزينة',
                    'account.treasury.delete' => 'حذف خزينة',
                    'account.treasury.toggle_status' => 'تفعيل الخزينة وتعطيلها',
                    'account.treasury.bank_create' => 'إنشاء حساب بنكي',
                    'account.treasury.bank_edit' => 'تعديل حساب بنكي',
                ],
            ],

            'treasury_transfers' => [
                'label' => 'التحويل بين الخزائن',
                'description' => 'التحويل بين الخزائن والحسابات البنكية.',
                'icon' => 'shuffle',
                'controller' => 'Finance/TreasuryController',
                'permissions' => [
                    'account.treasury_transfers.view' => 'عرض التحويلات',
                    'account.treasury_transfers.create' => 'تنفيذ تحويل بين خزينتين',
                    'account.treasury_transfers.edit' => 'تعديل تحويل',
                ],
            ],

            'treasury_employees' => [
                'label' => 'خزائن الموظفين',
                'description' => 'عهد الموظفين النقدية.',
                'icon' => 'user',
                'controller' => 'Finance/TreasuryEmployeeController',
                'permissions' => [
                    'account.treasury_employees.view' => 'عرض خزائن الموظفين',
                    'account.treasury_employees.create' => 'تعيين خزينة لموظف',
                    'account.treasury_employees.edit' => 'تعديل خزينة موظف',
                    'account.treasury_employees.delete' => 'حذف تعيين خزينة موظف',
                ],
            ],

            // ═══════════════ الشيكات ═══════════════
            'cheque_books' => [
                'label' => 'دفاتر الشيكات',
                'description' => 'دفاتر الشيكات وأرقامها المتسلسلة.',
                'icon' => 'book',
                'controller' => 'Cheques/CheckBooksController',
                'permissions' => [
                    'account.cheque_books.view' => 'عرض دفاتر الشيكات',
                    'account.cheque_books.show' => 'عرض تفاصيل دفتر',
                    'account.cheque_books.create' => 'إنشاء دفتر شيكات',
                    'account.cheque_books.edit' => 'تعديل دفتر شيكات',
                    'account.cheque_books.delete' => 'حذف دفتر شيكات',
                ],
            ],

            'received_cheques' => [
                'label' => 'الشيكات المستلمة (أوراق القبض)',
                'description' => 'أوراق القبض: إيداعها وتحصيلها وارتدادها.',
                'icon' => 'download',
                'controller' => 'Cheques/ReceivedChequesController',
                'permissions' => [
                    'account.received_cheques.view' => 'عرض الشيكات المستلمة',
                    'account.received_cheques.show' => 'عرض تفاصيل شيك مستلم',
                    'account.received_cheques.create' => 'تسجيل استلام شيك',
                    'account.received_cheques.edit' => 'تعديل شيك مستلم',
                    'account.received_cheques.delete' => 'حذف شيك مستلم',
                    'account.received_cheques.deposit' => 'إيداع الشيك برسم التحصيل',
                    'account.received_cheques.collect' => 'تحصيل الشيك',
                    'account.received_cheques.reject' => 'إثبات ارتداد الشيك',
                    'account.received_cheques.endorse' => 'تظهير الشيك لطرف آخر',
                ],
            ],

            'payable_cheques' => [
                'label' => 'الشيكات الصادرة (أوراق الدفع)',
                'description' => 'أوراق الدفع: صرفها وإلغاؤها.',
                'icon' => 'upload',
                'controller' => 'Cheques/PayableChequesController',
                'permissions' => [
                    'account.payable_cheques.view' => 'عرض الشيكات الصادرة',
                    'account.payable_cheques.show' => 'عرض تفاصيل شيك صادر',
                    'account.payable_cheques.create' => 'إصدار شيك',
                    'account.payable_cheques.edit' => 'تعديل شيك صادر',
                    'account.payable_cheques.delete' => 'حذف شيك صادر',
                    'account.payable_cheques.cash' => 'صرف الشيك',
                    'account.payable_cheques.reject' => 'إثبات رفض الشيك',
                    'account.payable_cheques.cancel' => 'إلغاء الشيك',
                ],
            ],

            // ═══════════════ الأصول الثابتة ═══════════════
            'assets' => [
                'label' => 'الأصول الثابتة',
                'description' => 'الأصول الثابتة وإهلاكها وبيعها.',
                'icon' => 'home',
                'controller' => 'AssetsController',
                'permissions' => [
                    'account.assets.view' => 'عرض الأصول',
                    'account.assets.show' => 'عرض تفاصيل أصل',
                    'account.assets.create' => 'إضافة أصل',
                    'account.assets.edit' => 'تعديل أصل',
                    'account.assets.delete' => 'حذف أصل',
                    'account.assets.sell' => 'بيع أصل',
                    'account.assets.transactions' => 'عرض حركات الأصل',
                    'account.assets.print' => 'طباعة بيانات الأصل',
                ],
            ],

            // ═══════════════ مراكز التكلفة ═══════════════
            'cost_centers' => [
                'label' => 'مراكز التكلفة',
                'description' => 'مراكز التكلفة وتوزيع المصروفات عليها.',
                'icon' => 'crosshair',
                'controller' => 'CostCentersController',
                'permissions' => [
                    'account.cost_centers.view' => 'عرض مراكز التكلفة',
                    'account.cost_centers.create' => 'إنشاء مركز تكلفة',
                    'account.cost_centers.edit' => 'تعديل مركز تكلفة',
                    'account.cost_centers.delete' => 'حذف مركز تكلفة',
                ],
            ],

            // ═══════════════ التحصيلات ═══════════════
            'collections' => [
                'label' => 'تفاصيل التحصيلات',
                'description' => 'تفاصيل التحصيلات اليومية.',
                'icon' => 'dollar-sign',
                'controller' => 'CollectionsDetailController',
                'permissions' => [
                    'account.collections.view' => 'عرض تقرير التحصيلات التفصيلي',
                    'account.collections.export' => 'تصدير تقرير التحصيلات',
                ],
            ],

            // ═══════════════ الإعدادات ═══════════════
            'settings' => [
                'label' => 'إعدادات الحسابات',
                'description' => 'توجيه الحسابات والضوابط المحاسبية العامة.',
                'icon' => 'settings',
                'controller' => 'AccountsSettingsController + Finance/FinanceSettingsController',
                'permissions' => [
                    'account.settings.view' => 'عرض إعدادات الحسابات',
                    'account.settings.general' => 'إدارة الإعدادات المحاسبية العامة',
                    'account.settings.routing' => 'إدارة توجيه الحسابات',
                    'account.settings.fiscal_years' => 'إدارة السنوات المالية',
                    'account.settings.expense_categories' => 'إدارة تصنيفات المصروفات',
                    'account.settings.receipt_categories' => 'إدارة تصنيفات المقبوضات',
                ],
            ],

            // ═══════════════ التقارير ═══════════════
            'reports' => [
                'label' => 'التقارير المالية',
                'description' => 'الميزانية وقائمة الدخل والتدفقات وموازين المراجعة.',
                'icon' => 'bar-chart-2',
                'controller' => 'GeneralAccountsController + ChecksController',
                'permissions' => [
                    'account.reports.view' => 'الدخول إلى التقارير المالية',
                    'account.reports.trial_balance' => 'ميزان المراجعة',
                    'account.reports.trial_balance_totals' => 'ميزان المراجعة الإجمالي',
                    'account.reports.account_review' => 'مراجعة الحساب',
                    'account.reports.journal' => 'تقرير دفتر اليومية',
                    'account.reports.chart_of_accounts' => 'تقرير شجرة الحسابات',
                    'account.reports.income_statement' => 'قائمة الدخل',
                    'account.reports.balance_sheet' => 'الميزانية العمومية',
                    'account.reports.profit_loss' => 'الأرباح والخسائر',
                    'account.reports.cash_flow' => 'قائمة التدفّقات النقدية',
                    'account.reports.cash_flow_detailed' => 'التدفّقات النقدية التفصيلية',
                    'account.reports.financial_transactions' => 'الحركات المالية',
                    'account.reports.assets' => 'تقرير الأصول',
                    'account.reports.inventory_trial_balance' => 'ميزان مراجعة المخزون (محاسبي)',
                    'account.reports.cost_centers' => 'تقرير مراكز التكلفة',
                    'account.reports.tax' => 'التقرير الضريبي',
                    'account.reports.tax_declaration' => 'الإقرار الضريبي',
                    'account.reports.expenses' => 'تقارير المصروفات',
                    'account.reports.receipts' => 'تقارير المقبوضات',
                    'account.reports.cheques' => 'تقارير الشيكات',
                    'account.reports.by_employee' => 'قيود الموظف',
                    'account.reports.year_closing' => 'تقرير الإقفال السنوي',
                    'account.reports.export' => 'تصدير التقارير المالية',
                ],
            ],

            /*
             * الفترات والإعدادات المحاسبية — أسماؤها تبدأ بـ`settings.`
             * لأن شاشاتها تسكن وحدة الإعدادات، لكنّ ما تحكمه محاسبةٌ خالصة:
             * إقفالُ فترةٍ يمنع القيد فيها، و«التجاوز» يخترق ذلك المنع.
             */
            'accounting_periods' => [
                'label' => 'الفترات المحاسبية',
                'description' => 'إقفال الفترات وإعادة فتحها — و«التجاوز» يسمح بالقيد في فترةٍ مقفلة، فتُمنح للمراجع وحده.',
                'icon' => 'lock',
                'controller' => 'Account/ClosedPeriodsController',
                'permissions' => [
                    'settings.accounting_periods.view' => 'عرض الفترات المحاسبية',
                    'settings.accounting_periods.manage' => 'إدارة الفترات المحاسبية',
                    'settings.accounting_periods.close' => 'إقفال فترة محاسبية',
                    'settings.accounting_periods.reopen' => 'إعادة فتح فترة محاسبية',
                    'settings.accounting_periods.override' => 'تجاوز قفل الفترة المحاسبية',
                ],
            ],

            'accounting_settings' => [
                'label' => 'الإعدادات المحاسبية',
                'description' => 'الضوابط المحاسبية العامة التي تقرؤها كل شاشة تُنشئ قيداً.',
                'icon' => 'settings',
                'controller' => 'Account/AccountingSettingsController',
                'permissions' => [
                    'settings.accounting_settings.view' => 'عرض الإعدادات المحاسبية',
                    'settings.accounting_settings.edit' => 'تعديل الإعدادات المحاسبية',
                ],
            ],

            /*
             * ═══════════ النظام القديم ═══════════
             *
             * `finance_` للخزائن والسندات، و`g_a_d_r_` للقيود ومراكز
             * التكلفة والأصول (اختصارُ General Accounting Daily Restrictions
             * — اسمٌ لا يدلّ على شيء). حُذفت مجموعاتها الثمان (سندات الصرف
             * والقبض، الخزائن، القيود، مراكز التكلفة، الفترات المقفلة،
             * الأصول، الشيكات) 2026-08-24 بعد أن حُوِّلت كل شاشات الحسابات
             * والمالية وكل صفحاتها إلى كتالوج account.* الجديد أعلاه،
             * وتدقيقٍ شامل للمستودع كلّه أثبت خلوّه من أي فحصٍ حيّ بهذه
             * الأسماء. بقيت `legacy_debts` وحدها — لم تُدقَّق بعد.
             */
            'legacy_debts' => [
                'label' => 'الديون المشكوك في تحصيلها',
                'description' => 'الذمم المتعثّرة التي قد تُخصَّص لها مخصّصات.',
                'icon' => 'alert-triangle',
                'controller' => 'Account/DoubtfulDebtsController',
                'permissions' => [
                    'doubtful_debts_view' => 'عرض الديون المشكوك في تحصيلها',
                ],
            ],

        ];
    }
}
