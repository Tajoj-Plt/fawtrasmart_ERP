@extends('account::master')

@section('title', 'التقارير المالية')

@section('content')
    @php
        /*
            كانت الشاشة اثنتين وثلاثين بطاقة بترميز `checks-*` — نظام تصميمٍ
            ثالثٍ خاصّ بها وحدها — وترويسةٍ ضخمة بعنوانٍ من حجم `h1` وتدرّجٍ
            بنفسجي `#667eea` مكتوبٍ في السمة، وعناوينِ أقسامٍ بأنماطٍ داخلية
            (`style="font-size:1.5rem; border-bottom:3px solid #667eea"`).
            لا شيء من ذلك يتبع «مظهر النظام».

            وستّ عشرة بطاقةً منها — المصروفات والمقبوضات «حسب البائع» و«حسب
            الموظف» و«اليومية» و«الأسبوعية»… — تشير إلى مسارات لا وجود لها،
            فتُسقط الصفحة كلّها بـ«Route not defined» قبل أن يُطبع منها شيء.

            البلاطات الآن بياناتٌ تتخطّى تلقائياً أيّ مسارٍ غير مسجَّل، فما لا
            وجهة له لا يظهر أصلاً.
        */
        /*
            كل بلاطةٍ الآن بمفتاح `perm` يطابق ما يفرضه المسار نفسه فعلياً
            (`Modules/Account/routes/web.php`، مجموعة GeneralAccountReports) —
            لا مجرّد Route::has() كما كانت: رابطٌ مسارُه مسجَّلٌ لكن بلا صلاحية
            كان يظهر لأي مستخدم وصل هذه الصفحة بصرف النظر عمّا يملك.
        */
        $groups = [
            [
                'title' => 'القوائم المالية',
                'icon' => 'file-text',
                'tiles' => [
                    ['route' => 'GeneralAccountReports.incomeStatement', 'perm' => 'account.reports.income_statement', 'icon' => 'trending-up',
                     'title' => 'قائمة الدخل', 'hint' => 'الإيرادات والمصروفات وصافي الربح خلال الفترة'],
                    ['route' => 'GeneralAccountReports.balanceSheet', 'perm' => 'account.reports.balance_sheet', 'icon' => 'layers',
                     'title' => 'الميزانية العمومية', 'hint' => 'الأصول والخصوم وحقوق الملكية في تاريخٍ بعينه'],
                    ['route' => 'GeneralAccountReports.profitAndLoss', 'perm' => 'account.reports.profit_loss', 'icon' => 'bar-chart-2',
                     'title' => 'الأرباح والخسائر', 'hint' => 'نتيجة النشاط موزَّعةً على بنودها'],
                    ['route' => 'GeneralAccountReports.cashFlowStatement', 'perm' => 'account.reports.cash_flow', 'icon' => 'repeat',
                     'title' => 'التدفّقات النقدية', 'hint' => 'حركة النقد الداخل والخارج إجمالاً'],
                    ['route' => 'GeneralAccountReports.detailedCashFlow', 'perm' => 'account.reports.cash_flow_detailed', 'icon' => 'git-commit',
                     'title' => 'التدفّقات التفصيلية', 'hint' => 'كل حركةٍ نقدية بحسابها المقابل'],
                    ['route' => 'GeneralAccountReports.yearClosing', 'perm' => 'account.reports.year_closing', 'icon' => 'lock',
                     'title' => 'الإقفال السنوي', 'hint' => 'قيود إقفال السنة وحساباتها وترحيل صافيها ومطابقتها وسجلّ إعادة الفتح'],
                ],
            ],

            [
                'title' => 'موازين المراجعة والأستاذ',
                'icon' => 'check-square',
                'tiles' => [
                    ['route' => 'GeneralAccountReports.trialBalance', 'perm' => 'account.reports.trial_balance', 'icon' => 'sliders',
                     'title' => 'ميزان المراجعة', 'hint' => 'أرصدة الحسابات مدينةً ودائنة'],
                    ['route' => 'GeneralAccountReports.trialBalanceTotals', 'perm' => 'account.reports.trial_balance_totals', 'icon' => 'align-justify',
                     'title' => 'ميزان المجاميع والأرصدة', 'hint' => 'الحركة والرصيد معاً لكل حساب'],
                    ['route' => 'GeneralAccountReports.accountReview', 'perm' => 'account.reports.account_review', 'icon' => 'search',
                     'title' => 'مراجعة الحساب', 'hint' => 'حركة حسابٍ واحد وأرصدته قبل الفترة وبعدها'],
                    ['route' => 'journal.generalLedger', 'perm' => 'account.chart.general_ledger', 'icon' => 'book-open',
                     'title' => 'دفتر الأستاذ العام', 'hint' => 'حركة كل حساب مفصَّلةً بقيودها'],
                    ['route' => 'GeneralAccountReports.ReportJournal', 'perm' => 'account.reports.journal', 'icon' => 'edit-3',
                     'title' => 'دفتر اليومية', 'hint' => 'القيود مرتَّبةً بتاريخها'],
                    ['route' => 'GeneralAccountReports.ChartOfAccounts', 'perm' => 'account.reports.chart_of_accounts', 'icon' => 'git-branch',
                     'title' => 'دليل الحسابات', 'hint' => 'شجرة الحسابات بأكوادها وأرصدتها'],
                ],
            ],

            [
                'title' => 'الضرائب',
                'icon' => 'percent',
                'tiles' => [
                    ['route' => 'GeneralAccountReports.taxReport', 'perm' => 'account.reports.tax', 'icon' => 'file-text',
                     'title' => 'التقرير الضريبي', 'hint' => 'الضريبة المحصَّلة والمدفوعة وصافي المستحقّ'],
                    ['route' => 'GeneralAccountReports.taxDeclaration', 'perm' => 'account.reports.tax_declaration', 'icon' => 'clipboard',
                     'title' => 'الإقرار الضريبي', 'hint' => 'الإقرار جاهزاً بصيغته المعتمدة'],
                ],
            ],

            [
                'title' => 'الأصول والمخزون',
                'icon' => 'box',
                'tiles' => [
                    ['route' => 'GeneralAccountReports.assetsReport', 'perm' => 'account.reports.assets', 'icon' => 'box',
                     'title' => 'تقرير الأصول', 'hint' => 'الأصول الثابتة وقيمها الدفترية ومجمّع إهلاكها'],
                    ['route' => 'GeneralAccountReports.inventoryTrialBalance', 'perm' => 'account.reports.inventory_trial_balance', 'icon' => 'package',
                     'title' => 'ميزان مراجعة المخزون', 'hint' => 'أرصدة حسابات المخزون وحركتها'],
                ],
            ],

            [
                'title' => 'المصروفات والمقبوضات',
                'icon' => 'dollar-sign',
                'tiles' => [
                    ['route' => 'GeneralAccountReports.expensesByCategory', 'perm' => 'account.reports.expenses', 'icon' => 'file-minus',
                     'title' => 'المصروفات حسب التصنيف', 'hint' => 'توزيع المصروف على تصنيفاته'],
                    ['route' => 'GeneralAccountReports.expensesByPeriod', 'perm' => 'account.reports.expenses', 'icon' => 'calendar',
                     'title' => 'المصروفات حسب الفترة', 'hint' => 'المصروف موزَّعاً على المدى الزمني'],
                    ['route' => 'GeneralAccountReports.receiptsByCategory', 'perm' => 'account.reports.receipts', 'icon' => 'file-plus',
                     'title' => 'المقبوضات حسب التصنيف', 'hint' => 'توزيع المقبوض على تصنيفاته'],
                    ['route' => 'GeneralAccountReports.receiptsByPeriod', 'perm' => 'account.reports.receipts', 'icon' => 'calendar',
                     'title' => 'المقبوضات حسب الفترة', 'hint' => 'المقبوض موزَّعاً على المدى الزمني'],
                ],
            ],

            [
                'title' => 'مراكز التكلفة والحركات',
                'icon' => 'pie-chart',
                'tiles' => [
                    ['route' => 'GeneralAccountReports.CostCentersReport', 'perm' => 'account.reports.cost_centers', 'icon' => 'pie-chart',
                     'title' => 'تقرير مراكز التكلفة', 'hint' => 'ما حُمِّل على كل مركز تكلفة'],
                    ['route' => 'GeneralAccountReports.financialTransactions', 'perm' => 'account.reports.financial_transactions', 'icon' => 'activity',
                     'title' => 'الحركات المالية', 'hint' => 'كل حركةٍ مالية بمرجعها وطرفيها'],
                ],
            ],

            [
                'title' => 'الشيكات',
                'icon' => 'credit-card',
                'tiles' => [
                    ['route' => 'checksReports.index', 'perm' => 'account.reports.cheques', 'icon' => 'credit-card',
                     'title' => 'تقارير الشيكات', 'hint' => 'الشيكات المستلمة والصادرة وحالاتها'],
                ],
            ],
        ];
    @endphp

    <div class="content-body">

        @include('account::components.page-head', [
            'title' => 'التقارير المالية',
            'icon' => 'bar-chart-2',
            'sub' => 'القوائم المالية وموازين المراجعة والضرائب وتقارير التكلفة',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'الحسابات' => null,
                'التقارير' => null,
            ],
        ])

        @foreach ($groups as $group)
            @php
                $tiles = collect($group['tiles'])->filter(
                    fn ($tile) => Route::has($tile['route']) && hasPermission($tile['perm'])
                );
            @endphp

            @continue ($tiles->isEmpty())

            <div class="card">
                <div class="stk-table-head">
                    <h6 class="stk-table-head__title">
                        <i class="feather icon-{{ $group['icon'] }}"></i> {{ $group['title'] }}
                    </h6>
                    <span class="stk-count">{{ $tiles->count() }}</span>
                </div>

                <div class="card-body">
                    <div class="stk-tiles">
                        @foreach ($tiles as $tile)
                            <a href="{{ route($tile['route']) }}" class="stk-tile">
                                <span class="stk-tile__icon">
                                    <i class="feather icon-{{ $tile['icon'] }}"></i>
                                </span>
                                <span class="stk-tile__body">
                                    <span class="stk-tile__title d-block">{{ $tile['title'] }}</span>
                                    <span class="stk-tile__hint d-block">{{ $tile['hint'] }}</span>
                                </span>
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endsection
