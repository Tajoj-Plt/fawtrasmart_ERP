@extends('stock::master')

@section('title')
    إعدادات المخزون
@stop

@section('content')
    <div class="content-body">

        @include('stock::components.page-head', [
            'title' => 'إعدادات المخزون',
            'icon' => 'settings',
            'sub' => 'الأصناف والوحدات والبراندات والمجموعات وإعدادات الباركود',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'إعدادات المخزون' => null,
            ],
        ])

        {{--
            كانت البلاطات صوراً بلا شرح، ولون تمريرها مكتوباً في الصفحة
            (`#cdd2d8`) فلا يتبع الثيم. صارت أيقونة وعنواناً وسطراً يشرح
            وجهة كل بلاطة قبل الضغط عليها، وألوانها كلها من متغيّرات الثيم.
        --}}
        <div class="stk-tiles">

            @php
                use App\Constants\planFeatures\StockPlanFeatures;
                use App\Support\PlanLock;

                /*
                    `plan` هو مفتاح ميزة الباقة التي تفتح هذه البلاطة — نفس ما
                    يحرس مسارها في Modules/Stock/routes/web.php. فبلاطةٌ خارج
                    الباقة **تُقفَل ولا تُخفى**: إخفاؤها يجعل المنشأة لا تعرف
                    أنها موجودة فلا تسأل عنها ولا تشتريها.
                */
                $tiles = [
                    [
                        'route' => 'category.index',
                        'icon' => 'tag',
                        'title' => 'الأصناف',
                        'hint' => 'تصنيف المنتجات وربط كل صنف بوحداته',
                        'plan' => StockPlanFeatures::CATEGORIES,
                    ],
                    [
                        'route' => 'units.index',
                        'icon' => 'sliders',
                        'title' => 'الوحدات',
                        'hint' => 'وحدات القياس ومعاملات التحويل بينها',
                        'plan' => StockPlanFeatures::PRODUCTS,
                    ],
                    [
                        'route' => 'stock.brands.index',
                        'icon' => 'award',
                        'title' => 'البراندات',
                        'hint' => 'العلامات التجارية المرتبطة بالمنتجات',
                        'plan' => StockPlanFeatures::BRANDS,
                    ],
                    [
                        'route' => 'product_groups.index',
                        'icon' => 'grid',
                        'title' => 'مجموعات المنتجات',
                        'hint' => 'تجميع المنتجات لتسهيل الفرز والتقارير',
                        'plan' => StockPlanFeatures::CATEGORIES,
                    ],
                    [
                        'route' => 'product_settings.barcode_settings',
                        'icon' => 'maximize',
                        'title' => 'إعدادات الباركود',
                        'hint' => 'شكل ملصق الباركود وبياناته عند الطباعة',
                        'plan' => StockPlanFeatures::PRODUCTS,
                    ],
                ];
            @endphp

            @foreach ($tiles as $tile)
                @if (Route::has($tile['route']))
                    @php $locked = PlanLock::locked($tile['plan']); @endphp

                    <a href="{{ $locked ? PlanLock::url() : route($tile['route']) }}"
                       class="stk-tile {{ $locked ? 'stk-tile--locked' : '' }}"
                       @if ($locked) title="{{ PlanLock::reason($tile['plan']) }}" @endif>
                        <span class="stk-tile__icon">
                            <i class="feather icon-{{ $locked ? 'lock' : $tile['icon'] }}"></i>
                        </span>
                        <span class="stk-tile__body">
                            <span class="stk-tile__title d-block">
                                {{ $tile['title'] }}
                                @if ($locked)
                                    <span class="stk-plan-badge">
                                        <i class="feather icon-lock"></i> خارج الباقة
                                    </span>
                                @endif
                            </span>
                            <span class="stk-tile__hint d-block">
                                {{ $locked ? 'غير مشمولة بباقتك الحالية — اضغط لعرض الاشتراك والترقية.' : $tile['hint'] }}
                            </span>
                        </span>
                    </a>
                @endif
            @endforeach

        </div>
    </div>
@endsection
