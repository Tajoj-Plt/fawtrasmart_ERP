@extends('stock::master')

@section('title', 'تقارير التصنيع')

@section('content')
    @php
        /*
            كانت البلاطة الوحيدة هنا رابطاً بلا وجهة (`href=""`) — الضغط عليه
            يُعيد تحميل الصفحة نفسها. البلاطات الآن بياناتٌ تتخطّى تلقائياً
            أي طريقٍ غير مسجَّل، وما لا وجهة له يظهر «قريباً» غير قابلٍ للنقر.
        */
        $groups = [
            [
                'title' => 'أوامر التصنيع',
                'icon' => 'tool',
                'tiles' => [
                    [
                        'route' => 'manufacturing.orders.index',
                        'icon' => 'list',
                        'title' => 'أوامر التصنيع',
                        'hint' => 'كل الأوامر بحالاتها وتكاليفها الإجمالية',
                    ],
                    [
                        'icon' => 'dollar-sign',
                        'title' => 'تكاليف أوامر التصنيع',
                        'hint' => 'تحليل تفصيلي لتكلفة كل أمر: مواد ومصروفات وعمليات وهوالك',
                        'soon' => true,
                    ],
                ],
            ],
            [
                'title' => 'المكوّنات والتكاليف',
                'icon' => 'layers',
                'tiles' => [
                    [
                        'route' => 'BOM.index',
                        'icon' => 'clipboard',
                        'title' => 'قوائم مواد الإنتاج',
                        'hint' => 'مكوّنات كل منتج مصنَّع وتكلفتها المعياريّة',
                    ],
                    [
                        'route' => 'manufacturing.indirectcosts.index',
                        'icon' => 'pie-chart',
                        'title' => 'التكاليف غير المباشرة',
                        'hint' => 'المصروفات المحمَّلة على الإنتاج وأساس توزيعها',
                    ],
                    [
                        'route' => 'manufacturing.workstations.index',
                        'icon' => 'cpu',
                        'title' => 'محطات العمل',
                        'hint' => 'تكلفة تشغيل كل محطة وساعات عملها',
                    ],
                ],
            ],
            [
                'title' => 'أثر التصنيع على المخزون',
                'icon' => 'package',
                'tiles' => [
                    [
                        'route' => 'StorHouseReport.detailedMovementInventory',
                        'params' => ['type' => 'manufacturing'],
                        'icon' => 'activity',
                        'title' => 'حركات التصنيع المخزنية',
                        'hint' => 'الوارد والمنصرف الناتج عن أوامر التصنيع',
                    ],
                    [
                        'route' => 'StorHouseReport.valueInventory',
                        'icon' => 'trending-up',
                        'title' => 'تقييم المخزون',
                        'hint' => 'قيمة المواد الخام والبضاعة الجاهزة',
                    ],
                ],
            ],
        ];
    @endphp

    <div class="content-body">

        @include('stock::components.page-head', [
            'title' => 'تقارير التصنيع',
            'icon' => 'bar-chart-2',
            'sub' => 'الأوامر والتكاليف والمكوّنات وأثرها على المخزون',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'التقارير' => null,
                'التصنيع' => null,
            ],
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        @foreach ($groups as $group)
            @php
                $tiles = collect($group['tiles'])
                    ->filter(fn ($tile) => ($tile['soon'] ?? false) || Route::has($tile['route'] ?? ''));
            @endphp

            @continue ($tiles->isEmpty())

            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">
                        <i class="feather icon-{{ $group['icon'] }}"></i> {{ $group['title'] }}
                    </h4>
                    <span class="stk-count">{{ $tiles->count() }}</span>
                </div>

                <div class="card-body">
                    <div class="stk-tiles">
                        @foreach ($tiles as $tile)
                            @if ($tile['soon'] ?? false)
                                <span class="stk-tile stk-tile--soon">
                                    <span class="stk-tile__icon">
                                        <i class="feather icon-{{ $tile['icon'] }}"></i>
                                    </span>
                                    <span class="stk-tile__body">
                                        <span class="stk-tile__title d-block">{{ $tile['title'] }}</span>
                                        <span class="stk-tile__hint d-block">{{ $tile['hint'] }}</span>
                                    </span>
                                    <span class="stk-state stk-state--neutral">قريباً</span>
                                </span>
                            @else
                                <a href="{{ route($tile['route'], $tile['params'] ?? []) }}" class="stk-tile">
                                    <span class="stk-tile__icon">
                                        <i class="feather icon-{{ $tile['icon'] }}"></i>
                                    </span>
                                    <span class="stk-tile__body">
                                        <span class="stk-tile__title d-block">{{ $tile['title'] }}</span>
                                        <span class="stk-tile__hint d-block">{{ $tile['hint'] }}</span>
                                    </span>
                                </a>
                            @endif
                        @endforeach
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endsection
