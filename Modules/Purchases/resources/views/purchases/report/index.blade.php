{{--
    فهرس تقارير المشتريات — على قياس لوحة تقارير العملاء
    (`client::customers.index`، مسار `ClientReport.index`) واحداً لواحد:
    ورقة الأنماط نفسها (`sales::partials.settings-theme`)، ولوحة الإرشاد
    نفسها (`sales::components.guide`)، والترويسة نفسها
    (`sales::components.page-head`)، وبطاقات المجموعات نفسها (`card` برأسٍ
    فيه العنوان وشارة `sl-count`، وجسمٍ فيه شبكة `sl-tiles`).

    لماذا `sales::partials.settings-theme` لا نسخة الوحدة
    (`purchase_requests/partials/sales-theme`)؟ لأن النسخة المحليّة كانت
    مقصورةً على شاشات الفهرسة فلم تحمل قواعد البلاطات (`sl-tiles`،
    `sl-tile*`) ولا لوحة الإرشاد (`sl-guide*`) — فكانت بلاطات هذه اللوحة
    تُرسم بلا شبكةٍ ولا حدود. الاستدعاء العابر للوحدات على نفس ما تفعله
    لوحة العملاء نفسها، وما يفعله هذا الموديول أصلاً في ذيول الطباعة
    (`sales::sals_report.report_partials.print-footer`).

    البلاطات والصلاحيات والمسارات وروابط الفترة كما كانت: البطاقة لا تظهر
    إلّا لمن يملك صلاحيةَ تقريرها، والمجموعة الفارغة تُطوى كلُّها، وروابط
    اليوم/الأسبوع/الشهر/السنة تبقى صفّاً صغيراً أسفل البلاطة الدوريّة
    (`report_partials/report-card.blade.php`).
--}}
@extends('purchases::master')

@section('title')
    تقارير المشتريات
@stop

@section('content')

    @include('sales::partials.settings-theme')

    @include('sales::components.guide', [
        'key' => 'purchase-reports.hub',
        'title' => 'ما هذه الشاشة؟',
        'lead' => 'لوحة تجمع كل تقارير المشتريات في مكانٍ واحد — المشتريات بالفترة، دليل الموردين، أعمار ديونهم وأرصدتهم، والمدفوعات والاستلام.',
        'open' => false,
        'blocks' => [
            [
                'اضغط عنوان أي تقرير لفتحه مباشرة بخيارات الفلترة الخاصة به.',
                'روابط «اليوم/الأسبوع/الشهر/السنة» أسفل تقارير الفترة تفتح التقرير نفسه مضبوطاً على تلك الفترة.',
                'أرصدة الموردين وأعمار ديونهم بالمصطلح المحاسبي: دائن ما علينا للمورد، ومدين ما له عندنا من دفعاتٍ مقدَّمة أو مرتجعات.',
            ],
        ],
    ])

    @php
        $groups = [
            [
                'title' => 'المشتريات حسب الفترة الزمنية',
                'icon' => 'clock',
                'tiles' => [
                    ['route' => 'ReportsPurchases.purchaseByEmployee', 'permission' => 'purchases.reports.by_employee', 'icon' => 'user-tie', 'periods' => true,
                     'title' => 'المشتريات حسب الموظف', 'hint' => 'تحليل أداء الموظفين في المشتريات'],
                    ['route' => 'ReportsPurchases.bySupplier', 'permission' => 'purchases.reports.by_supplier', 'icon' => 'truck-loading', 'periods' => true,
                     'title' => 'المشتريات حسب المورد', 'hint' => 'تحليل تفصيلي لمشتريات كل مورد'],
                    ['route' => 'ReportsPurchases.byProduct', 'permission' => 'purchases.reports.by_product', 'icon' => 'cubes', 'periods' => true,
                     'title' => 'المشتريات حسب المنتج', 'hint' => 'تحليل شامل لمشتريات كل منتج'],
                ],
            ],
            [
                'title' => 'تقارير الموردين',
                'icon' => 'users',
                'tiles' => [
                    ['route' => 'ReportsPurchases.SuppliersDirectory', 'permission' => 'purchases.reports.suppliers_directory', 'icon' => 'address-book',
                     'title' => 'دليل الموردين', 'hint' => 'قائمة شاملة بجميع الموردين'],
                    ['route' => 'ReportsPurchases.supplierDebtAging', 'permission' => 'purchases.reports.debt_aging', 'icon' => 'clock',
                     'title' => 'أعمار ديون الموردين', 'hint' => 'إجمالي مديونية كلّ مورّد موزَّعاً على أعمار الدَّين'],
                    ['route' => 'ReportsPurchases.invoiceDebtAging', 'permission' => 'purchases.reports.debt_aging', 'icon' => 'file-invoice-dollar',
                     'title' => 'أعمار ديون فواتير الموردين', 'hint' => 'كلّ فاتورة شراء غير مسدَّدة بعمرها منذ استحقاقها'],
                    ['route' => 'ReportsPurchases.balanceSuppliers', 'permission' => 'purchases.reports.supplier_balances', 'icon' => 'balance-scale',
                     'title' => 'أرصدة الموردين', 'hint' => 'ملخص أرصدة المديونية للموردين'],
                    // المتحكّمُ يقبل الصلاحيتين — فالبطاقةُ كذلك
                    ['route' => 'ReportsPurchases.supplierWallets', 'permission' => ['purchases.reports.supplier_wallets', 'purchases.supplier_wallet.view'], 'icon' => 'wallet',
                     'title' => 'محافظ الموردين', 'hint' => 'أرصدة الدفعات المقدَّمة للموردين وسجل حركاتها: دفعات، استرداد، سداد فواتير'],
                    ['route' => 'ReportsPurchases.purchasePayments', 'permission' => 'purchases.reports.payments', 'icon' => 'calendar-check',
                     'title' => 'مدفوعات المشتريات', 'hint' => 'إيقاع الصرف للموردين يومياً وشهرياً'],
                    ['route' => 'ReportsPurchases.supplierPayments', 'permission' => 'purchases.reports.payments', 'icon' => 'money-bill-wave',
                     'title' => 'المدفوعات حسب المورد', 'hint' => 'إجمالي ما دُفع لكلّ مورّد ومصدر سداده'],
                    ['route' => 'ReportsPurchases.employeeSupplierPayments', 'permission' => 'purchases.reports.payments', 'icon' => 'user-check',
                     'title' => 'المدفوعات حسب الموظف', 'hint' => 'ما سدّده كلّ موظّف للموردين في الفترة'],
                    ['route' => 'ReportsPurchases.receiving', 'permission' => 'purchases.reports.receiving', 'icon' => 'dolly',
                     'title' => 'المشتريات حسب البند', 'hint' => 'المشترى مقابل المستلم وما لم يصل من المستودع'],
                ],
            ],
        ];
    @endphp

    <div class="content-body sl-scope">

        {{-- الأيقونة feather (لا Font Awesome) كما في ترويسة لوحة العملاء --}}
        @include('sales::components.page-head', [
            'title' => 'تقارير المشتريات',
            'icon' => 'bar-chart-2',
            'sub' => 'إحصاءات وتحليلات شاملة لجميع عمليات المشتريات والموردين والمدفوعات',
            'crumbs' => [
                'الرئيسية' => route('dashboard'),
                'التقارير' => null,
            ],
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        @foreach ($groups as $group)
            @php
                /* البطاقةُ لا تظهر إلّا لمن يملك صلاحيةَ تقريرها — المسارُ
                   موجودٌ دائماً فلا يكفي فحصُه وحده. والمجموعةُ الفارغة
                   تُطوى كلُّها بـ@continue أدناه فلا يبقى عنوانٌ بلا بطاقات. */
                $tiles = collect($group['tiles'])->filter(fn ($tile) => Route::has($tile['route'])
                    && (empty($tile['permission']) || hasPermission($tile['permission'])));
            @endphp

            @continue ($tiles->isEmpty())

            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">
                        <i class="fas fa-{{ $group['icon'] }}"></i> {{ $group['title'] }}
                    </h4>
                    <span class="sl-count">{{ $tiles->count() }}</span>
                </div>

                <div class="card-body">
                    <div class="sl-tiles">
                        @foreach ($tiles as $tile)
                            @include('purchases::purchases.report.report_partials.report-card', $tile)
                        @endforeach
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endsection
