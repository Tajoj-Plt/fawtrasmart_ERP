@extends('stock::master')

@section('title')
    لوحة تحكم المخزون الشاملة
@stop

@section('content')
    @include('stock::components.page-head', [
        'title' => 'أهلاً '.auth()->user()->name,
        'icon' => 'bar-chart-2',
        'sub' => \Carbon\Carbon::now()->translatedFormat('l، d F Y').' — نظرة عامّة على المخزون',
        'crumbs' => [
            'الرئيسية' => url('/'),
            'لوحة المخزون' => null,
        ],
    ])

    <div class="content-body">
        <section id="inventory-dashboard">
            {{--
                بطاقات الرأس.

                كانت «إجمالي الكمية» رقماً بلا مقياس (14,681 قطعة من ماذا؟)
                ولا قيمةَ للمخزون في اللوحة إطلاقاً — وهي أوّل ما يُسأل عنه.
                وكانت البطاقات الثماني كلّها بلونٍ واحد تقريباً بلا دلالة،
                فلا يميّز الناظر التحذير من الخبر.

                اللون هنا دلالةٌ لا زينة: الأزرق خبرٌ محايد، الأخضر سليم،
                الأصفر يحتاج انتباهاً، الأحمر يحتاج تصرّفاً.
            --}}
            @php
                $cards = [
                    [
                        'label' => 'الأصناف',
                        'value' => number_format($totalProducts),
                        'note' => $activeProducts.' نشط من '.$totalProducts,
                        'icon' => 'box',
                        'tone' => 'info',
                    ],
                    [
                        'label' => 'إجمالي القطع في المستودعات',
                        'value' => number_format($totalQuantity),
                        'note' => $totalStorehouses.' مستودع',
                        'icon' => 'layers',
                        'tone' => 'info',
                    ],
                    [
                        'label' => 'قيمة المخزون بالتكلفة',
                        'value' => number_format($inventoryCostValue, 2),
                        'note' => 'بسعر الشراء',
                        'icon' => 'dollar-sign',
                        'tone' => 'success',
                    ],
                    [
                        'label' => 'الربح المتوقَّع لو بيع كلّه',
                        'value' => number_format($expectedProfit, 2),
                        'note' => 'قيمة بيعية '.number_format($inventorySaleValue, 2),
                        'icon' => 'trending-up',
                        'tone' => $expectedProfit >= 0 ? 'success' : 'danger',
                    ],
                    [
                        'label' => 'بلغ حدّ التنبيه',
                        'value' => number_format($lowStockProducts),
                        'note' => 'أصناف تحتاج إعادة تخزين',
                        'icon' => 'alert-circle',
                        'tone' => $lowStockProducts > 0 ? 'warning' : 'success',
                    ],
                    [
                        'label' => 'نفذ من المخزون',
                        'value' => number_format($outOfStockProducts),
                        'note' => 'أصناف بلا رصيد',
                        'icon' => 'x-circle',
                        'tone' => $outOfStockProducts > 0 ? 'danger' : 'success',
                    ],
                    [
                        'label' => 'قريب من الانتهاء',
                        'value' => number_format($expiringSoonProducts),
                        'note' => 'خلال 30 يوماً',
                        'icon' => 'clock',
                        'tone' => $expiringSoonProducts > 0 ? 'warning' : 'success',
                    ],
                    [
                        'label' => 'الفئات',
                        'value' => number_format($totalCategories),
                        'note' => $categoryDistribution->count().' فئة بها أصناف',
                        'icon' => 'grid',
                        'tone' => 'info',
                    ],
                ];
            @endphp

            <div class="row">
                @foreach ($cards as $card)
                    <div class="col-lg-3 col-md-6 col-12">
                        <div class="card stat-card inventory-card h-100">
                            <div class="card-header d-flex flex-column align-items-start pb-0">
                                <div class="avatar bg-rgba-{{ $card['tone'] }} p-50 m-0">
                                    <div class="avatar-content">
                                        <i class="feather icon-{{ $card['icon'] }} text-{{ $card['tone'] }} font-medium-5"></i>
                                    </div>
                                </div>
                                <h2 class="text-bold-700 mt-1 mb-0">{{ $card['value'] }}</h2>
                                <p class="mb-0">{{ $card['label'] }}</p>
                                <small class="text-muted">{{ $card['note'] }}</small>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Charts Section -->
            <div class="row mt-2">
                <div class="col-lg-8 col-12">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title">اتجاه المخزون (آخر 12 شهر)</h4>
                        </div>
                        <div class="card-body">
                            <div id="inventory-trend-chart" class="chart-container"></div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4 col-12">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title">توزيع الفئات والمبيعات</h4>
                        </div>
                        <div class="card-body">
                            <div id="categories-chart" class="chart-container"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Products Section -->
            <h4 class="section-title mt-3">المنتجات</h4>
            <div class="row">
                <div class="col-lg-4 col-md-6 col-12">
                    <div class="card stat-card">
                        <div class="card-header">
                            <h4 class="card-title">ملخص المنتجات</h4>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between mb-2">
                                <span>إجمالي المنتجات</span>
                                <span class="font-weight-bold">{{ $totalProducts }}</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>المنتجات النشطة</span>
                                <span class="font-weight-bold">{{ $activeProducts }}</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>المنتجات غير النشطة</span>
                                <span class="font-weight-bold">{{ $totalProducts - $activeProducts }}</span>
                            </div>

                            <div class="progress progress-bar-primary mb-2 mt-1">
                                <div class="progress-bar" role="progressbar" style="width:{{ ($activeProducts / max($totalProducts, 1)) * 100 }}%"
                                     aria-valuenow="{{ ($activeProducts / max($totalProducts, 1)) * 100 }}" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-8 col-12">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title">أفضل المنتجات مبيعاً</h4>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover table-striped mb-0">
                                    <thead>
                                        <tr>
                                            <th>اسم المنتج</th>
                                            <th>الكمية المباعة</th>
                                            <th>المخزون المتبقي</th>
                                            <th>الفئة</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {{--
                                            كان الجدول يخرج فارغاً بلا كلمة — ولا فواتير
                                            بيعٍ في النظام أصلاً. جدولٌ فارغ بلا تفسير
                                            يُقرأ عطلاً، فصار يقول سببه.
                                        --}}
                                        @forelse ($topSellingProducts as $product)
                                            <tr>
                                                <td>{{ $product->name }}</td>
                                                <td><span class="badge bg-success">{{ number_format($product->sold_quantity, 0) }}</span></td>
                                                <td>{{ number_format($product->stock_qty, 0) }}</td>
                                                <td>{{ $product->category_name }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="4" class="text-center text-muted py-4">
                                                    لا مبيعات مسجَّلة بعد — يظهر هنا الأكثر مبيعاً فور صدور أوّل فاتورة.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Low Stock Products Section -->
            <h4 class="section-title mt-3">المنتجات ذات الأسهم المنخفضة</h4>
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title">قائمة المنتجات التي تحتاج إلى إعادة تخزين</h4>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover table-striped mb-0">
                                    {{--
                                        كان الجدول يسرد **أسطر `product_details`** لا أصنافاً،
                                        فيظهر سطرٌ بلا مستودع ولا سعر: «N/A — N/A — 0.00 ريال».
                                        وكان يقيس كل سطر مستودعٍ على حدة بينما تقيس البطاقة
                                        فوقه مجموع الصنف — فقرأ المستخدم «0 منخفض» فوق جدولٍ
                                        فيه صنف. صار الاثنان على تعريفٍ واحد.
                                    --}}
                                    <thead>
                                        <tr>
                                            <th>الصنف</th>
                                            <th>الفئة</th>
                                            <th class="text-center">الرصيد</th>
                                            <th class="text-center">حدّ التنبيه</th>
                                            <th class="text-end">قيمته بالتكلفة</th>
                                            <th class="text-center">الحالة</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($lowStockItems as $item)
                                            <tr>
                                                <td>{{ $item->name }}</td>
                                                <td>{{ $item->category_name }}</td>
                                                <td class="text-center fw-bold">{{ number_format($item->stock_qty, 0) }}</td>
                                                <td class="text-center">
                                                    {{ $item->threshold > 0 ? number_format($item->threshold, 0) : '—' }}
                                                </td>
                                                <td class="text-end">{{ number_format($item->cost_value, 2) }}</td>
                                                <td class="text-center">
                                                    @if ($item->is_out)
                                                        <span class="badge bg-danger">نفذ</span>
                                                    @else
                                                        <span class="badge bg-warning">بلغ الحدّ</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6" class="text-center text-muted py-4">
                                                    لا صنف بلغ حدّ التنبيه — المخزون بخير.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Storehouses Section -->
            <h4 class="section-title mt-3">المستودعات</h4>
            <div class="row">
                <div class="col-lg-4 col-md-6 col-12">
                    <div class="card stat-card">
                        <div class="card-header">
                            <h4 class="card-title">ملخص المستودعات</h4>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between mb-2">
                                <span>إجمالي المستودعات</span>
                                <span class="font-weight-bold">{{ $totalStorehouses }}</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>المستودعات النشطة</span>
                                <span class="font-weight-bold">{{ $activeStorehouses }}</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>المستودعات الرئيسية</span>
                                <span class="font-weight-bold">{{ $mainStorehouses ?? 0 }}</span>
                            </div>

                            <div class="progress progress-bar-success mb-2 mt-1">
                                <div class="progress-bar" role="progressbar" style="width:{{ ($activeStorehouses / max($totalStorehouses, 1)) * 100 }}%"
                                     aria-valuenow="{{ ($activeStorehouses / max($totalStorehouses, 1)) * 100 }}" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-8 col-12">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title">الرصيد حسب المستودع</h4>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover table-striped mb-0">
                                    {{--
                                        «إجمالي العناصر» كان `productDetails()->count()` —
                                        **عدد أسطر** الجدول لا عدد القطع. مستودعٌ فيه 8,281
                                        قطعة من صنفٍ واحد كان يُقرأ «1». وكان استعلاماً
                                        داخل الحلقة (N+1).
                                    --}}
                                    <thead>
                                        <tr>
                                            <th>المستودع</th>
                                            <th>العنوان</th>
                                            <th class="text-center">الأصناف</th>
                                            <th class="text-center">القطع</th>
                                            <th class="text-end">القيمة بالتكلفة</th>
                                            <th class="text-center">الحالة</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($storehouses as $storehouse)
                                            <tr>
                                                <td>
                                                    {{ $storehouse->name }}
                                                    @if ($storehouse->is_major)
                                                        <span class="badge bg-primary">رئيسي</span>
                                                    @endif
                                                </td>
                                                <td>{{ $storehouse->shipping_address ?: '—' }}</td>
                                                <td class="text-center">{{ number_format($storehouse->product_count) }}</td>
                                                <td class="text-center fw-bold">{{ number_format($storehouse->stock_qty, 0) }}</td>
                                                <td class="text-end">{{ number_format($storehouse->cost_value, 2) }}</td>
                                                <td class="text-center">
                                                    <span class="badge bg-{{ $storehouse->is_active ? 'success' : 'secondary' }}">
                                                        {{ $storehouse->is_active ? 'نشط' : 'غير نشط' }}
                                                    </span>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6" class="text-center text-muted py-4">لا مستودعات معرَّفة.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Categories Section -->
            {{--
                الفئات.

                كان هنا **جدولان** لنفس الفئات: «أكثر الفئات منتجات»
                و«قيمة المخزون حسب الفئة» — والقيمتان تُحسبان بأساسين
                مختلفين (سعر البيع في أحدهما، وسعر الوحدة المسجَّل في سطر
                المستودع في الآخر). ولمّا كان ذلك السعر فارغاً في أكثر
                الأسطر، أعطى الجدولان 220,215 و32,000 لنفس المخزون. صارا
                جدولاً واحداً على أساسٍ واحد.

                و«النسبة المئوية» كانت شريطاً نسبته إلى أكبر صفٍّ في الجدول
                لا إلى الإجمالي — فأكبر فئة تملأ الشريط دائماً مهما صغرت
                حصّتها. صارت حصّةً حقيقية من قيمة المخزون، مكتوبةً رقماً
                إلى جانب الشريط.
            --}}
            <h4 class="section-title mt-3">الفئات</h4>
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title">المخزون حسب الفئة</h4>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>الفئة</th>
                                            <th class="text-center">الأصناف</th>
                                            <th class="text-center">القطع</th>
                                            <th class="text-end">القيمة بالتكلفة</th>
                                            <th class="text-center">تحتاج انتباهاً</th>
                                            <th style="width:22%">حصّتها من قيمة المخزون</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($categoryDistribution as $category)
                                            <tr>
                                                <td>{{ $category->name }}</td>
                                                <td class="text-center">{{ number_format($category->product_count) }}</td>
                                                <td class="text-center">{{ number_format($category->current_stock, 0) }}</td>
                                                <td class="text-end fw-bold">{{ number_format($category->stock_value, 2) }}</td>
                                                <td class="text-center">
                                                    @if ($category->out_count)
                                                        <span class="badge bg-danger">{{ $category->out_count }} نفذ</span>
                                                    @endif
                                                    @if ($category->low_count)
                                                        <span class="badge bg-warning">{{ $category->low_count }} منخفض</span>
                                                    @endif
                                                    @if (! $category->out_count && ! $category->low_count)
                                                        <span class="text-muted">—</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <div class="progress flex-grow-1" style="height:8px;">
                                                            <div class="progress-bar bg-info" role="progressbar"
                                                                 style="width:{{ $category->value_share }}%"
                                                                 aria-valuenow="{{ $category->value_share }}"
                                                                 aria-valuemin="0" aria-valuemax="100"></div>
                                                        </div>
                                                        <small class="text-muted">{{ $category->value_share }}%</small>
                                                    </div>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6" class="text-center text-muted py-4">لا فئة بها أصناف بعد.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{--
                حركات المخزون الأخيرة.

                **ما كان — خمسة أعطال في جدولٍ واحد**

                1. عمود «المنتج» يقرأ `$movement->product->name` — و`WarehousePermits`
                   ترويسةُ إذنٍ لا صنفاً واحداً؛ أصنافه في بنوده. فالعلاقة `product`
                   لا وجود لها، و`?? 'N/A'` يبتلع الخطأ: العمود كان `N/A` في كل سطر.

                2. عمود «الكمية» يطبع `grand_total` — وهو **الإجمالي المالي**. فيُقرأ
                   «39150.00» على أنه عدد حبّات.

                3. نوع العملية يُقارَن بنصوص `'incoming'/'outgoing'/'transfer'`
                   والقاعدة تخزّنه رقماً (1..4) — فكل سطر يسقط إلى else ويطبع
                   الرقم عارياً.

                4. الحالة تُقارَن بـ`'approved'` وحدها، والقاعدة فيها **صيغتان**
                   لكل معنى (`approved` و`apporod` — خطأ إملائي قديم في البيانات:
                   18 سطراً من 21). فكانت أذونٌ **موافَقٌ عليها** تُعرَض «مرفوض»،
                   وهو ما رآه المستخدم في كل سطر.

                5. الشارات بأصناف بوتستراب 4 (`badge-success`) والصفحة تحمّل
                   بوتستراب 5 الذي يريد `bg-*` — فخرجت الشارات بلا لون.

                **ما صار**

                البنود تُحمَّل مع الإذن، فالاسم اسمُ صنفٍ حقيقي والكمّية مجموع
                كمّيات البنود. والنوع والحالة يُقرآن بخريطتَي القيم نفسِهما
                المستعملتَين في شاشة الأذون — بما فيها الصيغة الخاطئة.
            --}}
            <h4 class="section-title mt-3">حركات المخزون الأخيرة</h4>
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title">آخر تحديثات المخزون</h4>
                        </div>
                        <div class="card-body p-0">
                            @php
                                // نوع الإذن كما تخزّنه القاعدة: [الاسم، اللون، الرمز]
                                $permitKinds = [
                                    1 => ['إضافة', 'success', 'log-in'],
                                    2 => ['صرف', 'danger', 'log-out'],
                                    3 => ['تحويل', 'info', 'repeat'],
                                    4 => ['رصيد افتتاحي', 'primary', 'flag'],
                                ];

                                $permitStates = [
                                    'pending' => ['قيد الانتظار', 'warning'],
                                    'approved' => ['موافق عليه', 'success'],
                                    'apporod' => ['موافق عليه', 'success'],
                                    'rejected' => ['مرفوض', 'danger'],
                                    'rejec' => ['مرفوض', 'danger'],
                                ];
                            @endphp

                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th style="width:26%">الصنف</th>
                                            <th style="width:18%">نوع العملية</th>
                                            <th class="text-center" style="width:12%">الكمية</th>
                                            <th style="width:20%">المستودع</th>
                                            <th style="width:12%">التاريخ</th>
                                            <th class="text-center" style="width:12%">الحالة</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($recentMovements as $movement)
                                            @php
                                                $items = $movement->items;
                                                $firstItem = $items->first();
                                                $firstName = $firstItem && $firstItem->product ? $firstItem->product->name : null;
                                                $extra = max($items->count() - 1, 0);
                                                $qty = (float) $items->sum('quantity');

                                                [$kindLabel, $kindTone, $kindIcon] =
                                                    $permitKinds[(int) $movement->permission_type]
                                                    ?? [optional($movement->permissionSource)->name ?: 'حركة', 'secondary', 'repeat'];

                                                [$stateLabel, $stateTone] =
                                                    $permitStates[$movement->status] ?? [$movement->status, 'secondary'];

                                                $warehouse = match (true) {
                                                    (bool) ($movement->fromStoreHouse && $movement->toStoreHouse)
                                                        => $movement->fromStoreHouse->name.' ← '.$movement->toStoreHouse->name,
                                                    (bool) $movement->fromStoreHouse => $movement->fromStoreHouse->name,
                                                    (bool) $movement->toStoreHouse => $movement->toStoreHouse->name,
                                                    (bool) $movement->storeHouse => $movement->storeHouse->name,
                                                    default => '—',
                                                };
                                            @endphp

                                            <tr>
                                                <td>
                                                    @if ($firstName)
                                                        <span class="fw-medium">{{ $firstName }}</span>
                                                        @if ($extra)
                                                            <small class="text-muted">و{{ $extra }} صنفاً آخر</small>
                                                        @endif
                                                    @else
                                                        <span class="text-muted">إذن بلا بنود</span>
                                                    @endif
                                                    <div><small class="text-muted">إذن رقم {{ $movement->number }}</small></div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-{{ $kindTone }}">
                                                        <i class="feather icon-{{ $kindIcon }}"></i> {{ $kindLabel }}
                                                    </span>
                                                </td>
                                                <td class="text-center">
                                                    @if ($items->count())
                                                        <span class="fw-bold">{{ rtrim(rtrim(number_format($qty, 2), '0'), '.') }}</span>
                                                    @else
                                                        <span class="text-muted">—</span>
                                                    @endif
                                                </td>
                                                <td>{{ $warehouse }}</td>
                                                <td>
                                                    {{ $movement->permission_date
                                                        ? \Carbon\Carbon::parse($movement->permission_date)->format('Y-m-d')
                                                        : '—' }}
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge bg-{{ $stateTone }}">{{ $stateLabel }}</span>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6" class="text-center text-muted py-4">
                                                    لا حركات مخزنية بعد.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Expiring Products Section -->
            <h4 class="section-title mt-3">المنتجات القريبة من الانتهاء</h4>
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title">المنتجات التي تنتهي صلاحيتها قريباً</h4>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover table-striped mb-0">
                                    <thead>
                                        <tr>
                                            <th>اسم المنتج</th>
                                            <th>تاريخ الانتهاء</th>
                                            <th class="text-center">الأيام المتبقية</th>
                                            <th class="text-center">الكمية</th>
                                            <th class="text-center">الحالة</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($expiringSoonProductsList as $product)
                                            @php
                                                $expiry = $product->expiry_date ? \Carbon\Carbon::parse($product->expiry_date) : null;
                                                // `diffInDays` بلا وسيط يعيد فرقاً موجباً دائماً،
                                                // فكان المنتهي يُقرأ كأنّ أمامه أياماً.
                                                $daysToExpiry = $expiry ? (int) \Carbon\Carbon::now()->startOfDay()->diffInDays($expiry->startOfDay(), false) : null;

                                                [$expiryLabel, $expiryTone] = match (true) {
                                                    $daysToExpiry === null => ['بلا تاريخ', 'secondary'],
                                                    $daysToExpiry < 0 => ['منتهي', 'danger'],
                                                    $daysToExpiry <= 7 => ['عاجل', 'danger'],
                                                    $daysToExpiry <= 30 => ['قريب', 'warning'],
                                                    default => ['ضمن المهلة', 'info'],
                                                };
                                            @endphp
                                            <tr>
                                                <td>{{ $product->name }}</td>
                                                <td>{{ $expiry ? $expiry->format('Y-m-d') : '—' }}</td>
                                                <td class="text-center">
                                                    @if ($daysToExpiry === null)
                                                        <span class="text-muted">—</span>
                                                    @elseif ($daysToExpiry < 0)
                                                        <span class="badge bg-danger">مضى {{ abs($daysToExpiry) }} يوماً</span>
                                                    @else
                                                        <span class="badge bg-{{ $expiryTone }}">{{ $daysToExpiry }} يوماً</span>
                                                    @endif
                                                </td>
                                                <td class="text-center">{{ number_format($product->productDetails->sum('quantity'), 0) }}</td>
                                                <td class="text-center">
                                                    <span class="badge bg-{{ $expiryTone }}">{{ $expiryLabel }}</span>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="text-center text-muted py-4">
                                                    لا صنف يقترب من الانتهاء خلال 30 يوماً.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection

@section('scripts')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/apexcharts/3.45.0/apexcharts.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Categories Distribution Chart with Sales
            try {
                var categoriesChartElement = document.querySelector("#categories-chart");
                if (categoriesChartElement) {
                    var categoriesOptions = {
                        chart: {
                            type: 'donut',
                            height: 300
                        },
                        // كانت السلسلة `sold_quantity` — ولا فواتير بيعٍ في النظام،
                        // فكان الرسم أصفاراً كلّه: دائرةٌ فارغة بلا ألوان.
                        series: [@foreach($categoryDistribution as $cat){{ round($cat->stock_value, 2) }},@endforeach],
                        labels: [@foreach($categoryDistribution as $cat)@json($cat->name),@endforeach],
                        colors: ['#007bff', '#28a745', '#ffc107', '#dc3545', '#6f42c1', '#20c997', '#e83e8c', '#fd7e14'],
                        legend: {
                            position: 'bottom'
                        },
                        dataLabels: {
                            enabled: true,
                            formatter: function(val) {
                                return Math.round(val) + '%';
                            }
                        },
                        tooltip: {
                            y: {
                                formatter: function(val) {
                                    return val + ' وحدة مباعة';
                                }
                            }
                        },
                        responsive: [{
                            breakpoint: 480,
                            options: {
                                chart: {
                                    width: 200
                                },
                                legend: {
                                    position: 'bottom'
                                }
                            }
                        }]
                    };

                    if (categoriesOptions.series.length > 0 && categoriesOptions.labels.length > 0) {
                        var categoriesChart = new ApexCharts(categoriesChartElement, categoriesOptions);
                        categoriesChart.render();
                    }
                }
            } catch (e) {
                console.error('Error rendering categories chart:', e);
            }

            // Inventory Trend Chart
            try {
                var inventoryTrendChartElement = document.querySelector("#inventory-trend-chart");
                if (inventoryTrendChartElement) {
                    var inventoryTrendOptions = {
                        chart: {
                            type: 'line',
                            height: 300
                        },
                        series: [{
                            name: 'إجمالي المخزون',
                            data: [@foreach($monthlyInventory as $inv) {{ $inv }}, @endforeach]
                        }, {
                            name: 'المنتجات المباعة',
                            data: [@foreach($monthlySold as $sold) {{ $sold }}, @endforeach]
                        }],
                        xaxis: {
                            categories: [@foreach($months as $month) '{!! $month !!}', @endforeach]
                        },
                        colors: ['#28a745', '#dc3545'],
                        dataLabels: {
                            enabled: false
                        },
                        stroke: {
                            curve: 'smooth'
                        },
                        fill: {
                            type: 'gradient',
                            gradient: {
                                shadeIntensity: 1,
                                opacityFrom: 0.7,
                                opacityTo: 0.9,
                                stops: [0, 90, 100]
                            }
                        }
                    };

                    if (inventoryTrendOptions.series[0].data.length > 0) {
                        var inventoryTrendChart = new ApexCharts(inventoryTrendChartElement, inventoryTrendOptions);
                        inventoryTrendChart.render();
                    }
                }
            } catch (e) {
                console.error('Error rendering inventory trend chart:', e);
            }
        });
    </script>
@endsection