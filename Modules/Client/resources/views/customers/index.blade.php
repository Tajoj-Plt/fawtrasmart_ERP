{{--
    لوحة تقارير العملاء — أُعيدت بنظام لوحة تقارير المبيعات (`sl-tiles` +
    `report-card`) بدل تصميم `checks-*` المخترَع الذي كانت تحمله وحدها.
    كل تقارير العملاء الثمانية الحيّة (`rpt-*`) تُبنى أصلاً بنظام تقارير
    المبيعات نفسه عبر include عابرٍ للموديول — فهذه اللوحة تتبع نفس القاعدة
    بدل أن تبقى الوحيدة المختلفة.

    «تقرير مدفوعات العملاء» (`ClientReport.customerPayments`) لم يكن مربوطاً
    من هذه اللوحة إطلاقاً رغم أنّ مساره ومتحكّمه يعملان — أُضيف هنا.

    «تقرير أهداف العملاء» (`ClientReport.customerTargets`) أيضاً لم يكن
    مربوطاً من هذه اللوحة — الطريق الوحيد إليه كان زرّاً داخل شاشة إعدادات
    الأهداف نفسها. صار الوصول إليه من هنا أيضاً، كبقيّة التقارير. مسارُه
    محروسٌ بنفس صلاحية هذه اللوحة فلا حاجة لفحص صلاحيةٍ إضافية.
--}}
@extends('client::master')

@section('title')
    تقرير العملاء
@stop

@section('content')

    @include('sales::partials.settings-theme')

    @include('sales::components.guide', [
        'key' => 'client-reports.hub',
        'title' => 'ما هذه الشاشة؟',
        'lead' => 'لوحة تجمع كل تقارير العملاء في مكانٍ واحد — أعمار الديون، الأرصدة، كشوف الحساب، المواعيد، الأقساط والمدفوعات.',
        'open' => false,
        'blocks' => [
            [
                'اضغط عنوان أي تقرير لفتحه مباشرة بخيارات الفلترة الخاصة به.',
                'رصيد العميل في تقريري «أرصدة العملاء» و«أعمار الديون» يستثني رصيد محفظته — أموال العميل نفسه لدينا تُعرض بجانبه كمعلومةٍ منفصلة، لا كدَين.',
            ],
        ],
    ])

    @php
        $groups = [
            [
                'title' => 'تقارير أعمار الديون',
                'icon' => 'clock',
                'tiles' => [
                    ['route' => 'ClientReport.debtReconstructionInv', 'permission' => 'clients.reports.debts', 'icon' => 'file-invoice-dollar',
                     'title' => 'أعمار الديون (الفواتير)', 'hint' => 'تحليل أعمار الديون حسب الفواتير المستحقة', 'periods' => false],
                    ['route' => 'ClientReport.debtAgingGeneralLedger', 'permission' => 'clients.reports.debt_aging', 'icon' => 'calculator',
                     'title' => 'أعمار الديون (حساب الأستاذ)', 'hint' => 'تحليل أعمار الديون حسب حساب الأستاذ — مع نوع الحساب مدين/دائن', 'periods' => false],
                ],
            ],
            [
                'title' => 'تقارير بيانات العملاء',
                'icon' => 'address-book',
                'tiles' => [
                    ['route' => 'ClientReport.customerGuide', 'permission' => 'clients.reports.guide', 'icon' => 'address-book',
                     'title' => 'دليل العملاء', 'hint' => 'دليل شامل لجميع العملاء في النظام', 'periods' => false],
                    ['route' => 'ClientReport.customerBalance', 'permission' => 'clients.reports.balances', 'icon' => 'wallet',
                     'title' => 'أرصدة العملاء', 'hint' => 'أرصدة العملاء الحالية — مع نوع الحساب مدين/دائن ورصيد المحفظة منفصلاً', 'periods' => false],
                    // المتحكّمُ يقبل الصلاحيتين — فالبطاقةُ كذلك
                    ['route' => 'ClientReport.customerWallets', 'permission' => ['clients.reports.wallets', 'clients.wallet.view'], 'icon' => 'wallet',
                     'title' => 'محافظ العملاء', 'hint' => 'أرصدة محافظ العملاء وسجل حركاتها: إيداع، سحب، سداد فواتير', 'periods' => false],
                    ['route' => 'ClientReport.assignments', 'permission' => ['clients.reports.assignments', 'clients.reports.view'], 'icon' => 'user-friends',
                     'title' => 'العملاء المسندون للموظفين', 'hint' => 'اختر موظفاً فتظهر عملاؤه ولأيّ جهةٍ يتبعون في شجرة الفروع', 'periods' => false],
                    ['route' => 'ClientReport.competitors', 'permission' => ['clients.reports.competitors', 'clients_competitors_view'], 'icon' => 'binoculars',
                     'title' => 'تقرير المنافسين', 'hint' => 'المنافسون المرصودون عند العملاء: كم مرّة، وعند من، وبكم أصل، وفي أيّ جهة', 'periods' => false],
                    ['route' => 'ClientReport.customerAccountStatement', 'permission' => 'clients.reports.statement', 'icon' => 'search',
                     'title' => 'كشف حساب العملاء', 'hint' => 'كشف حساب مفصل لجميع العمليات مع العملاء', 'periods' => false],
                    ['route' => 'ClientReport.customerTargets', 'permission' => 'clients.reports.targets', 'icon' => 'bullseye',
                     'title' => 'تقرير أهداف العملاء', 'hint' => 'توزيع العملاء وتصنيفهم على أهداف التقييم', 'periods' => false],
                ],
            ],
            [
                'title' => 'تقارير المدفوعات والمواعيد',
                'icon' => 'credit-card',
                'tiles' => [
                    ['route' => 'ClientReport.customerAppointments', 'permission' => 'clients.reports.appointments', 'icon' => 'calendar-alt',
                     'title' => 'مواعيد العملاء', 'hint' => 'المواعيد حسب الحالة واليوم والموظف والعميل', 'periods' => false],
                    // تقرير الحجوزات شقيق تقرير المواعيد — كلاهما يُفتح من هنا (قرار 2026-09-20)
                    ['route' => 'Reservations.reports.index', 'permission' => 'reservation_reports.view', 'icon' => 'calendar-check',
                     'title' => 'تقرير الحجوزات', 'hint' => 'الحجوزات بالحالات والخدمات والموظفين والفوترة', 'periods' => false],
                    ['route' => 'ClientReport.customerInstallments', 'permission' => 'clients.reports.installments', 'icon' => 'calendar-check',
                     'title' => 'أقساط العملاء', 'hint' => 'تقارير عن أقساط العملاء وسدادها', 'periods' => false],
                    ['route' => 'ClientReport.customerPayments', 'permission' => 'clients.reports.view', 'icon' => 'money-bill-wave',
                     'title' => 'مدفوعات العملاء', 'hint' => 'تقارير عن دفعات العملاء وطرق السداد', 'periods' => false],
                ],
            ],
        ];
    @endphp

    <div class="content-body sl-scope">

        @include('sales::components.page-head', [
            'title' => 'تقرير العملاء',
            'icon' => 'chart-bar',
            'sub' => 'تقارير شاملة ومفصلة لجميع عمليات العملاء والمبيعات في نظامك',
            'crumbs' => [
                'الرئيسية' => route('dashboard'),
                'التقارير' => null,
            ],
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        @foreach ($groups as $group)
            @php
                $tiles = collect($group['tiles'])->filter(fn ($tile) => Route::has($tile['route'])
                    && (empty($tile['permission']) || hasPermission($tile['permission']))
                    && (empty($tile['feature']) || (bool) (\App\Models\ClientModuleSetting::current()->{$tile['feature'].'_enabled'} ?? true)));
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
                            @include('sales::sals_report.report_partials.report-card', $tile)
                        @endforeach
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endsection
