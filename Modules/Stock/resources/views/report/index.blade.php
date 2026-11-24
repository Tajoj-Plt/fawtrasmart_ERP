@extends('stock::master')

@section('title', 'تقارير المخزون')

@section('content')
    @php
        /*
            كانت البلاطات مكتوبة يدوياً بلا فحصٍ لوجود الطرق، وثلاثة تقارير
            موجودة فعلاً لا رابط لها هنا (المخزون التالف، ميزان المراجعة،
            الكشف المخزني، الحركة التفصيلية البسيطة). صارت بياناتٍ واحدة
            تتخطّى تلقائياً أي طريقٍ غير مسجَّل.
        */
        $groups = [
            [
                'title' => 'التقارير الأساسية',
                'icon' => 'package',
                'tiles' => [
                    ['route' => 'StorHouseReport.inventorySheet', 'icon' => 'clipboard',
                     'title' => 'ورقة الجرد', 'hint' => 'كل المنتجات وكمياتها للجرد الميداني'],
                    ['route' => 'StorHouseReport.summaryInventory', 'icon' => 'book-open',
                     'title' => 'ملخّص عمليات المخزون', 'hint' => 'الوارد والصادر مجمَّعاً لكل منتج'],
                    ['route' => 'StorHouseReport.valueInventory', 'icon' => 'dollar-sign',
                     'title' => 'قيمة المخزون', 'hint' => 'القيمة المالية للأرصدة الحالية'],
                    ['route' => 'storehouse.statement', 'icon' => 'file-text',
                     'title' => 'كشف حساب المستودع', 'hint' => 'رصيد افتتاحي وحركات ورصيد ختامي'],
                    ['route' => 'storehouse.employee_statement', 'icon' => 'user-check',
                     'title' => 'الكشف المخزني', 'hint' => 'كشف المستودع مع تفصيل الجهات والتحويلات'],
                ],
            ],
            [
                'title' => 'الحركة التفصيلية',
                'icon' => 'activity',
                'tiles' => [
                    ['route' => 'StorHouseReport.detailedMovementInventory', 'icon' => 'search',
                     'title' => 'الحركة التفصيلية', 'hint' => 'بحث وتصفية شاملة لكل الحركات'],
                    ['route' => 'StockReport.simpleDetailedMovement', 'icon' => 'list',
                     'title' => 'الحركة التفصيلية المبسّطة', 'hint' => 'عرض مختصر قابل للتصدير'],
                    ['route' => 'StorHouseReport.detailedMovementInventory', 'params' => ['period' => 1],
                     'icon' => 'sun', 'title' => 'حركة اليوم', 'hint' => 'حركات اليوم الجاري'],
                    ['route' => 'StorHouseReport.detailedMovementInventory', 'params' => ['period' => 7],
                     'icon' => 'calendar', 'title' => 'حركة الأسبوع', 'hint' => 'آخر سبعة أيام'],
                    ['route' => 'StorHouseReport.detailedMovementInventory', 'params' => ['period' => 30],
                     'icon' => 'calendar', 'title' => 'حركة الشهر', 'hint' => 'آخر ثلاثين يوماً'],
                    ['route' => 'StorHouseReport.detailedMovementInventory', 'params' => ['period' => 365],
                     'icon' => 'trending-up', 'title' => 'حركة السنة', 'hint' => 'آخر اثني عشر شهراً'],
                ],
            ],
            [
                'title' => 'تحليل المنتجات',
                'icon' => 'box',
                'tiles' => [
                    ['route' => 'StorHouseReport.Inventory_mov_det_product', 'icon' => 'box',
                     'title' => 'حركة كل منتج', 'hint' => 'كشف حركة منفصل لكل منتج'],
                    ['route' => 'storehouse.damaged_stock', 'icon' => 'alert-triangle',
                     'title' => 'المخزون التالف والسليم', 'hint' => 'تفصيل الكميات التالفة مقابل السليمة'],
                ],
            ],
            [
                'title' => 'الأرصدة والمراجعة',
                'icon' => 'bar-chart-2',
                'tiles' => [
                    ['route' => 'StorHouseReport.inventoryBlance', 'icon' => 'layers',
                     'title' => 'ملخّص رصيد المخازن', 'hint' => 'الأرصدة الحالية لكل مخزن'],
                    ['route' => 'StorHouseReport.warehouseBalance', 'icon' => 'home',
                     'title' => 'رصيد المستودع', 'hint' => 'تفصيل الرصيد مع القيود المحاسبية'],
                    ['route' => 'StorHouseReport.trialBalance', 'icon' => 'check-square',
                     'title' => 'ميزان المراجعة المخزني', 'hint' => 'مطابقة الكميات والقيم'],
                ],
            ],
        ];
    @endphp

    <div class="content-body">

        @include('stock::components.page-head', [
            'title' => 'تقارير المخزون',
            'icon' => 'bar-chart-2',
            'sub' => 'الجرد والحركة والأرصدة والمراجعة',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'التقارير' => null,
            ],
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        @foreach ($groups as $group)
            @php
                $tiles = collect($group['tiles'])->filter(fn ($tile) => Route::has($tile['route']));
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
                            <a href="{{ route($tile['route'], $tile['params'] ?? []) }}" class="stk-tile">
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
