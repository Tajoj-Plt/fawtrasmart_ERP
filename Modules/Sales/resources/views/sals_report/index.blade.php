@extends('sales::master')

@section('title')
    {{ __('sales_reports.index.title') }}
@stop

@section('content')

    {{-- من أجل `.sl-*` وحدها — نفس نظام تصميم لوحة إعدادات المبيعات ولوحة
         تقارير المخزون (`stock::report.index`)، بادئةٌ مختلفة لتصميمٍ واحد. --}}
    @include('sales::partials.settings-theme')
    @include('components.hint-tips')

    {{-- ══════════ إرشادُ الشاشة ══════════ --}}
    @include('sales::components.guide', [
        'key' => 'sales-reports.hub',
        'title' => __('sales_reports.guide.hub.title'),
        'lead' => __('sales_reports.guide.hub.lead'),
        'open' => false,
        'blocks' => [
            [
                __('sales_reports.guide.hub.a'),
                __('sales_reports.guide.hub.b'),
                __('sales_reports.guide.hub.c'),
                __('sales_reports.guide.hub.d'),
            ],
        ],
    ])

    @php
        /*
            خمسة أقسامٍ كانت تُعرض تبويباتٍ (`rpt-tabs`) تُخفي أربعةً منها عن
            المستخدم حتى يضغط. لوحة تقارير المخزون لا تُبوِّب: تسرد أقسامها
            بطاقاتٍ متتالية، كلُّ بطاقةٍ عنوانها وعدّاد بلاطاتها. هنا نفس
            الشيء — والفترة التي كانت صفَّ أزرارٍ داخل كل بطاقة تقرير بقيت
            كما هي، منقولةً إلى `report-card.blade.php`.
        */
        $groups = [
            [
                'title' => __('sales_reports.index.section_sales'),
                'icon' => 'chart-line',
                'tiles' => [
                    ['route' => 'salesReports.byEmployee', 'permission' => 'sales.reports.by_employee', 'icon' => 'user-tie',
                     'title' => __('sales_reports.index.cards.by_employee'), 'hint' => __('sales_reports.index.cards.by_employee_desc')],
                    ['route' => 'salesReports.byCustomer', 'permission' => 'sales.reports.by_customer', 'icon' => 'users',
                     'title' => __('sales_reports.index.cards.by_customer'), 'hint' => __('sales_reports.index.cards.by_customer_desc')],
                    ['route' => 'salesReports.byProductNew', 'permission' => 'sales.reports.by_product', 'icon' => 'box',
                     'title' => __('sales_reports.index.cards.by_product'), 'hint' => __('sales_reports.index.cards.by_product_desc')],
                    ['route' => 'salesReports.byItemNew', 'permission' => 'sales.reports.by_item', 'icon' => 'tags',
                     'title' => __('sales_reports.index.cards.by_item'), 'hint' => __('sales_reports.index.cards.by_item_desc')],
                    /* «تقرير المبيعات الموحد حسب العميل» (`salesReports.unified`) رُفع من
                       اللوحة بطلب صاحب النظام. المسار والمتحكّم ونصوصه باقية كما هي —
                       رفعُ بلاطةٍ من لوحةٍ ليس حذفَ تقرير، ومن حفظ الرابط يصل إليه. */
                ],
            ],
            /* قسم «المجموعات والتصنيفات والوحدات» رُفع: صار «التجميع» حسب التصنيف
               أو مجموعة المنتج أو الوحدة داخل «مبيعات الأصناف»، ومسارُه القديم
               (`salesReports.byProductGroup`) يُحوِّل إليه. */
            [
                'title' => __('sales_reports.index.section_profits'),
                'icon' => 'coins',
                'tiles' => [
                    ['route' => 'salesReports.profits', 'permission' => 'sales.reports.profits', 'icon' => 'coins',
                     'title' => __('sales_reports.index.cards.profits'), 'hint' => __('sales_reports.index.cards.profits_desc')],
                ],
            ],
            /* قسم «تقارير المدفوعات» بتقاريره الستّة رُفع من اللوحة بطلب
               صاحب النظام. المسارات والمتحكّمات ونصوصها باقية كما هي —
               ومنها «تقرير التحصيل اليومي» الذي يبقى في الشريط الجانبي. */
            [
                'title' => __('sales_reports.index.section_collections'),
                'icon' => 'hand-holding-dollar',
                'tiles' => [
                    ['route' => 'salesReports.collections', 'permission' => 'sales.reports.collections', 'icon' => 'receipt',
                     'title' => __('sales_reports.index.cards.collections'), 'hint' => __('sales_reports.index.cards.collections_desc')],
                ],
            ],
            [
                'title' => __('sales_reports.index.section_exchanges_commissions'),
                'icon' => 'exchange-alt',
                'tiles' => [
                    ['route' => 'salesReports.exchanges', 'permission' => 'sales.reports.exchanges', 'icon' => 'exchange-alt',
                     'plan' => \App\Constants\planFeatures\SalesPlanFeatures::PRODUCT_EXCHANGES,
                     'title' => __('sales_reports.index.cards.exchanges'), 'hint' => __('sales_reports.index.cards.exchanges_desc')],
                    ['route' => 'salesReports.commissions', 'permission' => 'sales.reports.commissions', 'icon' => 'percent',
                     'title' => __('sales_reports.index.cards.commissions'), 'hint' => __('sales_reports.index.cards.commissions_desc')],
                ],
            ],
        ];
    @endphp

    <div class="content-body sl-scope">

        @include('sales::components.page-head', [
            'title' => __('sales_reports.index.title'),
            'icon' => 'chart-bar',
            'sub' => __('sales_reports.index.subtitle'),
            'crumbs' => [
                __('sales_reports.common.home') => route('dashboard'),
                __('sales_reports.common.reports') => null,
            ],
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        @foreach ($groups as $group)
            @php
                /* البطاقةُ لا تظهر إلّا لمن يملك صلاحيةَ تقريرها.

                   كان الفلترُ على وجود المسار وحدَه، والمسارُ موجودٌ دائماً —
                   فتُعرض البلاطاتُ كلُّها لكلّ من يفتح اللوحة، ثمّ يصطدم
                   المستخدم بـ403 عند الضغط. والصلاحياتُ كانت معرَّفةً في
                   `SalesPermissions` ومحروسةً في المتحكّمات، واللوحةُ وحدَها
                   لا تقرؤها. */
                $tiles = collect($group['tiles'])->filter(fn ($tile) => Route::has($tile['route'])
                    && (empty($tile['permission']) || hasPermission($tile['permission']))
                    // تقريرٌ لميزةٍ خارج الباقة لا يُعرض — مسارُه يردّ بحارس الباقة
                    && (empty($tile['plan']) || \App\Services\Fawtra\SubscriptionGate::permits($tile['plan'])));
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
