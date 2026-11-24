@extends('manufacturing::master')

@section('title', 'لوحة التصنيع')

@section('content')
    @php
        /*
            لوحة وحدة التصنيع.

            الوحدة كانت قسماً داخل المخزون بلا مدخلٍ خاصّ بها: يُدخَل إليها
            من الشريط الجانبي إلى «أوامر التصنيع» مباشرةً، فلا صفحةَ تجمع
            حالها. وحين صارت وحدةً قائمةً لزمتها واجهةٌ أولى كسائر الوحدات.

            اللون هنا دلالةٌ لا زينة — كما في لوحتَي المخزون والحسابات.
        */
        $num = fn ($v) => number_format((float) $v, 0);
        $money = fn ($v) => number_format((float) $v, 2);

        /*
            الحالات بمفاتيحها النصّية كما تُخزَّن فعلاً.

            كانت المفاتيح أرقاماً (0..3) والعمود يخزّن سلاسل — فكل
            `$orders['by_status'][$code]` يعود فارغاً، وأشرطة التقدّم
            صفرٌ كلها مهما كان عدد الأوامر.
        */
        $orderStates = collect(\App\Enums\ManufacturOrderStatus::cases())
            ->mapWithKeys(fn ($c) => [$c->value => [$c->label(), $c->tone()]])
            ->all();

        $metrics = [
            [
                'label' => 'أوامر التصنيع',
                'value' => $num($orders['live']),
                'hint' => $orders['cancelled'] > 0
                    ? 'بلا ' . $orders['cancelled'] . ' ملغاة'
                    : $orders['in_progress'] . ' قيد التنفيذ',
                'icon' => 'clipboard',
                'tone' => 'info',
            ],
            [
                'label' => 'قيد التنفيذ',
                'value' => $num($orders['in_progress']),
                'hint' => 'أوامر مفتوحة الآن',
                'icon' => 'loader',
                'tone' => $orders['in_progress'] > 0 ? 'warning' : 'muted',
            ],
            [
                'label' => 'مكتملة',
                'value' => $num($orders['done']),
                // النسبةُ من الأوامر القائمة: الملغي ليس أمراً «لم يكتمل»
                'hint' => $orders['live'] > 0
                    ? round($orders['done'] / $orders['live'] * 100) . '% من الأوامر غير الملغاة'
                    : 'لا أوامر بعد',
                'icon' => 'check-circle',
                'tone' => $orders['done'] > 0 ? 'success' : 'muted',
            ],
            [
                'label' => 'قوائم المواد',
                'value' => $num($boms),
                'hint' => 'وصفات الإنتاج',
                'icon' => 'layers',
                'tone' => 'info',
            ],
            [
                'label' => 'محطّات العمل',
                'value' => $num($workstations['total']),
                /*
                    كان التلميح «بتكلفة X»: مجموعُ أسعار ساعات المحطّات —
                    ٢٥ للساعة + ٢٠ للساعة ليست «تكلفة ٤٥». حِملُها الفعلي
                    في تقرير محطّات العمل.
                */
                'hint' => 'خطوط ومراكز التشغيل',
                'icon' => 'cpu',
                'tone' => $workstations['total'] > 0 ? 'success' : 'muted',
            ],
            [
                'label' => 'تكاليف غير مباشرة',
                'value' => $money($indirectCosts['amount']),
                // العملةُ تُذكر؛ وإن اختلفت عملاتُ الصفوف فالمجموعُ بلا تحويل
                'hint' => $indirectCosts['count'] . ' توزيعاً — '
                    . (($mixedCurrency ?? false) ? 'عملات مختلفة بلا تحويل' : ($displayCurrency ?? '')),
                'icon' => 'percent',
                'tone' => 'primary',
            ],
        ];
    @endphp

    @include('stock::components.page-head', [
        'title' => 'لوحة التصنيع',
        'icon' => 'layers',
        'sub' => \Carbon\Carbon::now()->translatedFormat('l، d F Y')
            . ' — ' . $num($orders['live']) . ' أمر تصنيع على ' . $num($boms) . ' قائمة مواد',
        'crumbs' => [
            'الرئيسية' => url('/'),
            'التصنيع' => null,
        ],
    ])

    <div class="content-body">
        <section id="manufacturing-dashboard">

            {{-- ==================== ابدأ من هنا: دليل قسم التصنيع ==================== --}}
            @include('manufacturing::partials.start-guide')

            <div class="stk-metrics">
                @foreach ($metrics as $metric)
                    <div class="stk-metric">
                        <span class="stk-metric__icon text-{{ $metric['tone'] }}">
                            <i class="feather icon-{{ $metric['icon'] }}"></i>
                        </span>
                        <span class="stk-metric__value stk-num text-{{ $metric['tone'] }}">{{ $metric['value'] }}</span>
                        <span class="stk-metric__label">{{ $metric['label'] }}</span>
                        <span class="stk-metric__label">{{ $metric['hint'] }}</span>
                    </div>
                @endforeach
            </div>

            {{-- ══════════ مداخل الوحدة ══════════ --}}
            <h4 class="section-title mt-3">أقسام التصنيع</h4>
            <div class="stk-tiles">
                @php
                    $sections = [
                        ['أوامر التصنيع', 'أوامر الإنتاج ومتابعتها', 'clipboard', 'manufacturing.orders.index', 'stock.manufacturing_orders.view'],
                        ['قوائم المواد', 'وصفات الإنتاج ومكوّناتها', 'layers', 'BOM.index', 'stock.bom.view'],
                        ['محطّات العمل', 'المحطّات وتكاليفها', 'cpu', 'manufacturing.workstations.index', 'stock.workstations.view'],
                        ['التكاليف غير المباشرة', 'توزيعها على الأوامر', 'percent', 'manufacturing.indirectcosts.index', 'stock.indirect_costs.view'],
                        ['إعدادات التصنيع', 'الحالات ومسارات الإنتاج', 'settings', 'Manufacturing.settings.index', 'stock.settings.manufacturing'],
                        ['تقارير التصنيع', 'خمسة تقارير على الأوامر والتكاليف', 'bar-chart-2', 'manufacturing.reports.index', 'stock.reports.manufacturing'],
                    ];
                @endphp

                @foreach ($sections as [$title, $desc, $icon, $routeName, $permission])
                    @continue(! \Illuminate\Support\Facades\Route::has($routeName))
                    @continue($permission && ! auth()->user()->can($permission))

                    <a href="{{ route($routeName) }}" class="stk-tile">
                        <span class="stk-tile__icon"><i class="feather icon-{{ $icon }}"></i></span>
                        <span class="stk-tile__body">
                            <span class="stk-tile__title">{{ $title }}</span>
                            <span class="stk-tile__desc">{{ $desc }}</span>
                        </span>
                    </a>
                @endforeach
            </div>

            {{-- ══════════ حالة الأوامر ══════════ --}}
            <div class="row mt-2">
                <div class="col-lg-5 col-12">
                    <div class="card h-100">
                        <div class="card-header">
                            <h4 class="card-title"><i class="feather icon-pie-chart"></i> الأوامر حسب الحالة</h4>
                        </div>
                        <div class="card-body">
                            @php $scale = max($orders['total'], 1); @endphp

                            @foreach ($orderStates as $code => [$label, $tone])
                                @php $count = (int) ($orders['by_status'][$code] ?? 0); @endphp
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <span style="width:7rem">
                                        <span class="badge bg-{{ $tone }}">{{ $label }}</span>
                                    </span>
                                    <div class="progress flex-grow-1" style="height:8px;">
                                        <div class="progress-bar bg-{{ $tone }}" role="progressbar"
                                             style="width:{{ $count / $scale * 100 }}%"
                                             aria-valuenow="{{ $count }}"
                                             aria-valuemin="0" aria-valuemax="{{ $scale }}"></div>
                                    </div>
                                    <span class="stk-num" style="width:3rem;text-align:end">{{ $num($count) }}</span>
                                </div>
                            @endforeach

                            @if ($orders['total'] === 0)
                                <p class="text-muted mb-0">لا أوامر تصنيع بعد.</p>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="col-lg-7 col-12">
                    <div class="card stk-table-card h-100">
                        <div class="stk-table-head">
                            <h6 class="stk-table-head__title">
                                <i class="feather icon-clock"></i> أحدث الأوامر
                            </h6>
                            @if (\Illuminate\Support\Facades\Route::has('manufacturing.orders.index'))
                                <a href="{{ route('manufacturing.orders.index') }}" class="btn btn-sm btn-outline-secondary">الكل</a>
                            @endif
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th style="width:20%">الرقم</th>
                                        <th>الصنف</th>
                                        <th class="text-center" style="width:14%">الكمية</th>
                                        <th class="text-center" style="width:18%">الحالة</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($recentOrders as $order)
                                        @php
                                            /*
                                                كانت الخريطة رقمية (0..3) والعمود يخزّن سلاسل،
                                                فـ`(int) 'pending'` صفرٌ وكلُّ أمرٍ يُعرض «مسوّدة».
                                            */
                                            $st = \App\Enums\ManufacturOrderStatus::fromLegacy($order->status);
                                            [$label, $tone] = [$st?->label() ?? 'غير محدد', $st?->tone() ?? 'secondary'];
                                        @endphp
                                        <tr>
                                            <td class="stk-num">{{ $order->code ?: '#' . $order->id }}</td>
                                            <td>{{ optional($order->product)->name ?: ($order->name ?: '—') }}</td>
                                            <td class="text-center stk-num">{{ $num($order->quantity) }}</td>
                                            <td class="text-center">
                                                <span class="badge bg-{{ $tone }}">{{ $label }}</span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-4">
                                                لا أوامر تصنيع بعد — ابدأ بإنشاء قائمة موادّ ثم أمر تصنيع.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ══════════ أكثر الأصناف تصنيعاً ══════════ --}}
            @if ($topProducts->isNotEmpty())
                <div class="card stk-table-card mt-2">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title">
                            <i class="feather icon-bar-chart"></i> أكثر الأصناف تصنيعاً
                        </h6>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                {{--
                                    كان هنا شريطُ «الحجم النسبي» على الكمية — يقارن
                                    مليلتراتِ صنفٍ بزجاجاتِ آخر. الكميةُ تُعرض لكل
                                    صنفٍ بوحدته ولا تُقارَن؛ والشريطُ صار على عدد
                                    الأوامر، وهو ما يُرتَّب به الجدول. والملغي خارجه.
                                --}}
                                <tr>
                                    <th>الصنف</th>
                                    <th class="text-center" style="width:12%">عدد الأوامر</th>
                                    <th style="width:22%">نصيبه من الأوامر</th>
                                    <th class="text-center" style="width:12%">مكتملة</th>
                                    <th class="text-end" style="width:16%">الكمية المخطَّطة</th>
                                    <th class="text-end" style="width:16%">المنتَجة فعلاً</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $topScale = $topProducts->max('orders_count') ?: 1;
                                    $qty = fn ($v) => rtrim(rtrim(number_format((float) $v, 4), '0'), '.');
                                @endphp

                                @foreach ($topProducts as $row)
                                    <tr>
                                        <td>{{ $row->name }}</td>
                                        <td class="text-center stk-num">{{ $num($row->orders_count) }}</td>
                                        <td>
                                            <div class="progress" style="height:8px;">
                                                <div class="progress-bar bg-info"
                                                     style="width:{{ $row->orders_count / $topScale * 100 }}%"></div>
                                            </div>
                                        </td>
                                        <td class="text-center stk-num">{{ $num($row->done_count) }}</td>
                                        <td class="text-end stk-num">{{ $qty($row->qty) }}</td>
                                        <td class="text-end stk-num fw-bold">
                                            {{ $row->done_count > 0 ? $qty($row->produced) : '—' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </section>
    </div>
@endsection
