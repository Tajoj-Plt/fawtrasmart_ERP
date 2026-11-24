@extends('stock::master')

@section('title', $product->name)

@section('content')
    @php
        $isStocked = in_array($product->type, ['products', 'compiled'], true);
        $isCompiled = $product->type === 'compiled';
        $isStaff = ! auth()->user()->employee_id;   // الموظّف المرتبط بمخزن لا يعدّل ولا يحذف

        $units = $productUnits ?? collect();
        $variants = $productVariants ?? collect();
        $quantities = $storeQuantities ?? collect();

        $materialTypes = [
            'raw' => ['خام (مادة أولية)', 'warning'],
            'semi_finished' => ['نصف مصنَّع', 'info'],
            'finished' => ['منتج نهائي', 'success'],
        ];
        [$materialLabel, $materialTone] = $materialTypes[$product->material_type] ?? ['غير محدد', 'secondary'];

        $trackingLabels = [
            0 => 'الرقم التسلسلي',
            1 => 'رقم الشحنة',
            2 => 'تاريخ الانتهاء',
            3 => 'رقم الشحنة وتاريخ الانتهاء',
            4 => 'الكمية فقط',
        ];

        $inventoryTypes = [
            'stored' => 'مخزَّن',
            'consumable' => 'مستهلك',
            'service' => 'خدمة',
            'raw' => 'مادة خام',
        ];

        // هامش الربح يُشتقّ من السعرين، فلا يعتمد على حقلٍ قد لا يُملأ.
        $computedMargin = ((float) $product->purchase_price) > 0
            ? ((((float) $product->sale_price) - ((float) $product->purchase_price)) / ((float) $product->purchase_price)) * 100
            : null;

        // الوسوم تُحفظ نصّاً مفصولاً بفواصل أو JSON حسب الشاشة التي حفظتها.
        $tagList = collect(
            is_array($product->tags)
                ? $product->tags
                : (json_decode((string) $product->tags, true) ?: explode(',', (string) $product->tags))
        )->map(fn ($t) => trim((string) $t))->filter()->values();

        $dimensions = is_array($product->dimensions)
            ? $product->dimensions
            : (array) json_decode((string) $product->dimensions, true);
        $dimensionLine = collect(['length', 'width', 'height'])
            ->map(fn ($k) => $dimensions[$k] ?? null)
            ->filter()
            ->implode(' × ');
    @endphp

    <meta name="csrf-token" content="{{ csrf_token() }}">

    <div class="content-body">

        @section('page-actions')
            @if ($isStaff)
                <a href="{{ route('products.edit', $product->id) }}" class="btn btn-primary">
                    <i class="feather icon-edit-2"></i> تعديل
                </a>
            @endif

            @if ($isStocked)
                <a href="{{ route('products.manual_stock_adjust', $product->id) }}" class="btn btn-outline-primary">
                    <i class="feather icon-plus-circle"></i> حركة يدوية
                </a>
            @endif

            <div class="btn-group">
                <button type="button" class="btn btn-outline-secondary dropdown-toggle"
                        data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="feather icon-more-horizontal"></i> المزيد
                </button>
                <div class="dropdown-menu dropdown-menu-end">
                    <a class="dropdown-item" href="{{ route('products.print_barcode', $product->id) }}">
                        <i class="feather icon-hash"></i> صفحة الباركود
                    </a>
                    <a class="dropdown-item" href="{{ route('products.download_barcode', ['id' => $product->id]) }}">
                        <i class="feather icon-download"></i> تحميل الباركود
                    </a>
                    <a class="dropdown-item" href="{{ route('products.print_barcode_pdf', ['id' => $product->id]) }}" target="_blank">
                        <i class="feather icon-printer"></i> باركود PDF
                    </a>

                    @if ($isStaff && $isStocked)
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" href="{{ route('store_permits_management.manual_conversion') }}">
                            <i class="feather icon-repeat"></i> نقل بين المستودعات
                        </a>
                    @endif

                    @if ($isStaff)
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item text-danger" href="#" data-toggle="modal" data-bs-toggle="modal"
                           data-target="#modal_DELETE" data-bs-target="#modal_DELETE">
                            <i class="feather icon-trash-2"></i> حذف المنتج
                        </a>
                    @endif
                </div>
            </div>
        @endsection

        @include('stock::components.page-head', [
            'title' => $product->name,
            'icon' => $isCompiled ? 'layers' : ($isStocked ? 'package' : 'briefcase'),
            'sub' => '#' . $product->serial_number . ($product->category ? ' — ' . $product->category->name : ''),
            'crumbs' => [
                'الرئيسية' => url('/'),
                'المنتجات' => route('products.index'),
                $product->name => null,
            ],
        ])

        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        {{-- ══════════ مؤشّرات سريعة ══════════ --}}
        <div class="stk-metrics">
            @if ($isStocked && ($showWarehouseQuantity ?? true))
                <div class="stk-metric">
                    <span class="stk-metric__icon"><i class="feather icon-package"></i></span>
                    <span class="stk-metric__value stk-num">{{ $total_quantity ? number_format($total_quantity) : '—' }}</span>
                    <span class="stk-metric__label">إجمالي المخزون</span>
                </div>
            @endif

            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-shopping-cart"></i></span>
                <span class="stk-metric__value stk-num">{{ number_format($total_sold ?? 0) }}</span>
                <span class="stk-metric__label">إجمالي المبيعات (قطعة)</span>
            </div>

            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-calendar"></i></span>
                <span class="stk-metric__value stk-num">{{ number_format($sold_last_28_days ?? 0) }}</span>
                <span class="stk-metric__label">آخر 28 يوماً</span>
            </div>

            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-clock"></i></span>
                <span class="stk-metric__value stk-num">{{ number_format($sold_last_7_days ?? 0) }}</span>
                <span class="stk-metric__label">آخر 7 أيام</span>
            </div>

            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-bar-chart-2"></i></span>
                <span class="stk-metric__value stk-num">{{ $average_cost ? App\Support\CurrencySettings::format($average_cost) : '—' }}</span>
                <span class="stk-metric__label">متوسط التكلفة</span>
            </div>

            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-toggle-right"></i></span>
                <span class="stk-metric__value">
                    @if ($isStaff)
                        <button type="button"
                                class="btn btn-sm {{ $product->status->value === 'active' ? 'btn-outline-success' : 'btn-outline-secondary' }} status-toggle-btn"
                                data-product-id="{{ $product->id }}"
                                data-current-status="{{ $product->status->value }}">
                            {{ $product->status->label() }}
                        </button>
                    @else
                        <span class="stk-state stk-state--{{ $product->status->value === 'active' ? 'on' : 'off' }}">
                            {{ $product->status->label() }}
                        </span>
                    @endif
                </span>
                <span class="stk-metric__label">الحالة</span>
            </div>
        </div>

        {{-- ══════════ التبويبات ══════════ --}}
        <div class="card">
            <div class="stk-table-head">
                <ul class="nav nav-tabs stk-tabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" data-toggle="tab" data-bs-toggle="tab" href="#home" role="tab">
                            <i class="feather icon-info"></i> المعلومات
                        </a>
                    </li>
                    @if ($isStocked)
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="tab" data-bs-toggle="tab" href="#profile" role="tab">
                                <i class="feather icon-repeat"></i> حركة المخزون
                            </a>
                        </li>
                    @endif
                    @if ($isCompiled)
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="tab" data-bs-toggle="tab" href="#compiled" role="tab">
                                <i class="feather icon-layers"></i> المكوّنات
                            </a>
                        </li>
                    @endif
                    <li class="nav-item">
                        <a class="nav-link" data-toggle="tab" data-bs-toggle="tab" href="#about" role="tab">
                            <i class="feather icon-git-commit"></i> الخطّ الزمني
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-toggle="tab" data-bs-toggle="tab" href="#activate" role="tab">
                            <i class="feather icon-activity"></i> سجلّ النشاط
                        </a>
                    </li>
                </ul>
            </div>

            <div class="tab-content p-2">

                {{-- ═════ المعلومات ═════ --}}
                <div class="tab-pane active" id="home" role="tabpanel">
                    <div class="row">
                        <div class="col-lg-4 col-12">
                            <div class="card">
                                <div class="card-body text-center">
                                    <img src="{{ $product->images ? asset('assets/uploads/product/' . $product->images) : asset('assets/uploads/no_image.jpg') }}"
                                         alt="{{ $product->name }}" class="stk-photo">
                                    <h5 class="mt-2 mb-1">{{ $product->name }}</h5>
                                    <span class="stk-media__sub stk-num">#{{ $product->serial_number }}</span>
                                    <div class="stk-tags justify-content-center mt-2">
                                        @if ($product->category)
                                            <span class="badge badge-light-primary">{{ $product->category->name }}</span>
                                        @endif
                                        @if ($product->brand)
                                            <span class="badge badge-light-secondary">{{ $product->brand->name }}</span>
                                        @endif
                                        @if ($isStocked)
                                            <span class="badge badge-light-{{ $materialTone }}">{{ $materialLabel }}</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-8 col-12">
                            <div class="card stk-card--tight">
                                <div class="card-header">
                                    <h4 class="card-title"><i class="feather icon-file-text"></i> التفاصيل</h4>
                                </div>
                                <div class="card-body">
                                    <dl class="stk-def stk-def--compact">
                                        <div class="stk-def__row">
                                            <dt>الباركود</dt>
                                            <dd class="stk-num">{{ $product->barcode ?: '—' }}</dd>
                                        </div>
                                        {{--
                                            «نوع التتبع» كان يُقرأ بـ`$trackingLabels[$product->inventory_type]`
                                            — و`$trackingLabels` مفاتيحه أرقام (0..4) وهي قيم
                                            `track_inventory`، بينما `inventory_type` نصّ
                                            (`stored`/`service`/…). فلا يوافق مفتاحٌ قيمةً أبداً
                                            وكان السطر يقول «غير محدد» مهما اختار المستخدم.
                                        --}}
                                        <div class="stk-def__row">
                                            <dt>نوع التتبع</dt>
                                            <dd>{{ $trackingLabels[$product->track_inventory] ?? 'غير محدد' }}</dd>
                                        </div>
                                        <div class="stk-def__row">
                                            <dt>طبيعة الصنف</dt>
                                            <dd>{{ $inventoryTypes[$product->inventory_type] ?? 'غير محدد' }}</dd>
                                        </div>
                                        <div class="stk-def__row">
                                            <dt>المورّد</dt>
                                            <dd>{{ $lookups['supplier'] ?: '—' }}</dd>
                                        </div>
                                        <div class="stk-def__row">
                                            <dt>المستودع الافتراضي</dt>
                                            <dd>{{ $lookups['storehouse'] ?: '—' }}</dd>
                                        </div>
                                        <div class="stk-def__row">
                                            <dt>أُضيف في</dt>
                                            <dd class="stk-num">
                                                {{ optional($product->created_at)->format('Y-m-d H:i') }}
                                                @if ($lookups['creator'])
                                                    <span class="stk-media__sub">بواسطة {{ $lookups['creator'] }}</span>
                                                @endif
                                            </dd>
                                        </div>
                                        @if ($product->description)
                                            <div class="stk-def__row">
                                                <dt>الوصف</dt>
                                                <dd>{{ $product->description }}</dd>
                                            </div>
                                        @endif
                                    </dl>
                                </div>
                            </div>

                            {{--
                                التسعير — لم يكن في الصفحة إطلاقاً سوى سعرَي الشراء
                                والبيع. أمّا هامش الربح والحدّ الأدنى للبيع والخصم
                                والضرائب وحسابا الإيراد والتكلفة فكانت تُحفظ في
                                القاعدة ولا تُعرض في أي شاشة.
                            --}}
                            <div class="card stk-card--tight">
                                <div class="card-header">
                                    <h4 class="card-title"><i class="feather icon-dollar-sign"></i> التسعير والضرائب</h4>
                                </div>
                                <div class="card-body">
                                    <dl class="stk-def stk-def--compact">
                                        <div class="stk-def__row">
                                            <dt>سعر الشراء</dt>
                                            <dd>@money($product->purchase_price)</dd>
                                        </div>
                                        @if ($average_cost && (float) $average_cost != (float) $product->purchase_price)
                                            <div class="stk-def__row">
                                                <dt>متوسط التكلفة الفعلي</dt>
                                                <dd>@money($average_cost)</dd>
                                            </div>
                                        @endif
                                        <div class="stk-def__row">
                                            <dt>سعر البيع</dt>
                                            <dd>@money($product->sale_price)</dd>
                                        </div>
                                        <div class="stk-def__row">
                                            <dt>هامش الربح</dt>
                                            <dd>
                                                @if ($computedMargin !== null)
                                                    <span class="stk-num">{{ number_format($computedMargin, 2) }}%</span>
                                                    <span class="stk-media__sub">
                                                        ربح @money($product->sale_price - $product->purchase_price) للوحدة
                                                    </span>
                                                    @if ($product->profit_margin !== null && abs((float) $product->profit_margin - $computedMargin) > 0.01)
                                                        <span class="stk-state stk-state--warn">
                                                            المحفوظ {{ number_format((float) $product->profit_margin, 2) }}%
                                                        </span>
                                                    @endif
                                                @else
                                                    —
                                                @endif
                                            </dd>
                                        </div>
                                        @if ($product->min_sale_price)
                                            <div class="stk-def__row">
                                                <dt>أدنى سعر بيع</dt>
                                                <dd>@money($product->min_sale_price)</dd>
                                            </div>
                                        @endif
                                        @if ($product->discount)
                                            <div class="stk-def__row">
                                                <dt>الخصم</dt>
                                                <dd>
                                                    @if ($product->discount_type === 'percentage')
                                                        <span class="stk-num">{{ number_format((float) $product->discount, 2) }}%</span>
                                                    @else
                                                        @money($product->discount)
                                                    @endif
                                                </dd>
                                            </div>
                                        @endif
                                        <div class="stk-def__row">
                                            <dt>الضريبة الأولى</dt>
                                            <dd>{{ $lookups['tax1'] ?: '—' }}</dd>
                                        </div>
                                        @if ($lookups['tax2'])
                                            <div class="stk-def__row">
                                                <dt>الضريبة الثانية</dt>
                                                <dd>{{ $lookups['tax2'] }}</dd>
                                            </div>
                                        @endif
                                        <div class="stk-def__row">
                                            <dt>حساب الإيراد</dt>
                                            <dd class="stk-num">{{ $lookups['sales_account'] ?: '—' }}</dd>
                                        </div>
                                        <div class="stk-def__row">
                                            <dt>حساب تكلفة المبيعات</dt>
                                            <dd class="stk-num">{{ $lookups['sales_cost_account'] ?: '—' }}</dd>
                                        </div>
                                    </dl>
                                </div>
                            </div>

                            {{-- الوحدات والتنبيهات — لم تكن معروضة --}}
                            <div class="card stk-card--tight">
                                <div class="card-header">
                                    <h4 class="card-title"><i class="feather icon-bell"></i> الوحدات والتنبيهات</h4>
                                </div>
                                <div class="card-body">
                                    <dl class="stk-def stk-def--compact">
                                        <div class="stk-def__row">
                                            <dt>وحدة الشراء</dt>
                                            <dd>{{ $lookups['purchase_unit'] ?: '—' }}</dd>
                                        </div>
                                        <div class="stk-def__row">
                                            <dt>وحدة البيع</dt>
                                            <dd>{{ $lookups['sales_unit'] ?: '—' }}</dd>
                                        </div>
                                        @if ($lookups['sub_unit'])
                                            <div class="stk-def__row">
                                                <dt>الوحدة الفرعية</dt>
                                                <dd>{{ $lookups['sub_unit'] }}</dd>
                                            </div>
                                        @endif
                                        <div class="stk-def__row">
                                            <dt>حدّ إعادة الطلب</dt>
                                            <dd>
                                                @if ($product->low_stock_thershold > 0)
                                                    <span class="stk-num">{{ number_format((float) $product->low_stock_thershold) }}</span>
                                                    @if ($total_quantity !== null && $total_quantity <= $product->low_stock_thershold)
                                                        <span class="stk-state stk-state--warn">بلغ الحدّ</span>
                                                    @endif
                                                @else
                                                    <span class="text-muted">بلا حدّ — لن يُنبَّه عن هذا الصنف</span>
                                                @endif
                                            </dd>
                                        </div>
                                        @if ($product->low_stock_alert)
                                            <div class="stk-def__row">
                                                <dt>حدّ التنبيه الإضافي</dt>
                                                <dd class="stk-num">{{ number_format((float) $product->low_stock_alert) }}</dd>
                                            </div>
                                        @endif
                                        <div class="stk-def__row">
                                            <dt>تاريخ الانتهاء</dt>
                                            <dd>
                                                @if ($product->expiry_date)
                                                    <span class="stk-num">{{ \Carbon\Carbon::parse($product->expiry_date)->format('Y-m-d') }}</span>
                                                    @php
                                                        $daysLeft = (int) \Carbon\Carbon::now()->startOfDay()
                                                            ->diffInDays(\Carbon\Carbon::parse($product->expiry_date)->startOfDay(), false);
                                                    @endphp
                                                    @if ($daysLeft < 0)
                                                        <span class="stk-state stk-state--off">منتهي منذ {{ abs($daysLeft) }} يوماً</span>
                                                    @elseif ($daysLeft <= 30)
                                                        <span class="stk-state stk-state--warn">بقي {{ $daysLeft }} يوماً</span>
                                                    @endif
                                                @else
                                                    —
                                                @endif
                                            </dd>
                                        </div>
                                        @if ($product->notify_before_days)
                                            <div class="stk-def__row">
                                                <dt>التنبيه قبل الانتهاء</dt>
                                                <dd class="stk-num">{{ $product->notify_before_days }} يوماً</dd>
                                            </div>
                                        @endif
                                    </dl>
                                </div>
                            </div>

                            <div class="card stk-card--tight">
                                <div class="card-header">
                                    <h4 class="card-title"><i class="feather icon-shopping-bag"></i> المتجر والإرجاع</h4>
                                </div>
                                <div class="card-body">
                                    <dl class="stk-def stk-def--compact">
                                        <div class="stk-def__row">
                                            <dt>متاح أونلاين</dt>
                                            <dd>
                                                <span class="stk-state stk-state--{{ $product->available_online ? 'on' : 'off' }}">
                                                    {{ $product->available_online ? 'نعم' : 'لا' }}
                                                </span>
                                            </dd>
                                        </div>
                                        <div class="stk-def__row">
                                            <dt>متاح بنقاط البيع</dt>
                                            <dd>
                                                <span class="stk-state stk-state--{{ $product->available_pos ? 'on' : 'off' }}">
                                                    {{ $product->available_pos ? 'نعم' : 'لا' }}
                                                </span>
                                            </dd>
                                        </div>
                                        @if ($product->weight)
                                            <div class="stk-def__row">
                                                <dt>الوزن</dt>
                                                <dd class="stk-num">{{ number_format($product->weight, 3) }} كجم</dd>
                                            </div>
                                        @endif
                                        @if ($dimensionLine)
                                            <div class="stk-def__row">
                                                <dt>الأبعاد (سم)</dt>
                                                <dd class="stk-num">{{ $dimensionLine }}</dd>
                                            </div>
                                        @endif
                                        <div class="stk-def__row">
                                            <dt>قابل للإرجاع</dt>
                                            <dd>
                                                @if ($product->is_returnable ?? true)
                                                    <span class="stk-state stk-state--on">نعم</span>
                                                    @if ($product->return_days)
                                                        <span class="stk-media__sub">خلال {{ $product->return_days }} يوماً</span>
                                                    @endif
                                                @else
                                                    <span class="stk-state stk-state--off">لا</span>
                                                @endif
                                            </dd>
                                        </div>
                                        <div class="stk-def__row">
                                            <dt>منتج مميَّز</dt>
                                            <dd>
                                                <span class="stk-state stk-state--{{ $product->featured_product ? 'on' : 'off' }}">
                                                    {{ $product->featured_product ? 'نعم' : 'لا' }}
                                                </span>
                                            </dd>
                                        </div>
                                        @if ($product->available_online)
                                            <div class="stk-def__row">
                                                <dt>مشاهدات المتجر</dt>
                                                <dd class="stk-num">{{ number_format((int) $product->view_count) }}</dd>
                                            </div>
                                            <div class="stk-def__row">
                                                <dt>مرّات الطلب</dt>
                                                <dd class="stk-num">{{ number_format((int) $product->order_count) }}</dd>
                                            </div>
                                        @endif
                                        @if ($product->has_variants)
                                            <div class="stk-def__row">
                                                <dt>له متغيّرات</dt>
                                                <dd>
                                                    <span class="stk-state stk-state--on">نعم</span>
                                                    @if ($product->variant_display_type)
                                                        <span class="stk-media__sub">عرض: {{ $product->variant_display_type }}</span>
                                                    @endif
                                                </dd>
                                            </div>
                                        @endif
                                    </dl>
                                </div>
                            </div>

                            {{-- الوسوم والملاحظات الداخلية — لم تكن تُعرض في أي شاشة --}}
                            @if ($tagList->isNotEmpty() || $product->Internal_notes)
                                <div class="card stk-card--tight">
                                    <div class="card-header">
                                        <h4 class="card-title"><i class="feather icon-tag"></i> الوسوم والملاحظات</h4>
                                    </div>
                                    <div class="card-body">
                                        @if ($tagList->isNotEmpty())
                                            <div class="stk-tags mb-1">
                                                @foreach ($tagList as $tag)
                                                    <span class="stk-tag">{{ $tag }}</span>
                                                @endforeach
                                            </div>
                                        @endif
                                        @if ($product->Internal_notes)
                                            <dl class="stk-def mb-0">
                                                <div class="stk-def__row">
                                                    <dt>ملاحظات داخلية</dt>
                                                    <dd>{{ $product->Internal_notes }}</dd>
                                                </div>
                                            </dl>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- الكميات في المستودعات — تُخفى بإيقاف «إظهار الكمية
                         في المخزن» من إعدادات المنتجات --}}
                    @if ($isStocked && ($showWarehouseQuantity ?? true) && ($quantities->isNotEmpty() || $variants->isNotEmpty()))
                        <div class="card stk-table-card">
                            <div class="stk-table-head">
                                <h6 class="stk-table-head__title">
                                    <i class="feather icon-home"></i> الكميات في المستودعات
                                </h6>
                            </div>

                            @if ($variants->isEmpty())
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th>المستودع</th>
                                                <th class="text-center" style="width:30%">الكمية</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($quantities as $row)
                                                @if ($row->storeHouse)
                                                    <tr>
                                                        <td>{{ $row->storeHouse->name }}</td>
                                                        <td class="text-center stk-num">{{ number_format($row->total_quantity) }}</td>
                                                    </tr>
                                                @endif
                                            @endforeach
                                            <tr class="stk-total-row">
                                                <td><strong>الإجمالي</strong></td>
                                                <td class="text-center stk-num stk-balance">{{ number_format($total_quantity ?? 0) }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                {{-- منتج بمتغيّرات: مصفوفة متغيّر × مستودع --}}
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th>المتغيّر</th>
                                                <th class="text-center">SKU / الباركود</th>
                                                @foreach ($variantWarehouses as $warehouse)
                                                    <th class="text-center">{{ $warehouse->name }}</th>
                                                @endforeach
                                                <th class="text-center">الإجمالي</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @php $grandTotal = 0; @endphp
                                            @foreach ($variants as $variant)
                                                @php
                                                    $stockByWarehouse = $variant->warehouseStock->keyBy('storehouse_id');
                                                    $variantTotal = (int) $variant->warehouseStock->sum('quantity');
                                                    $grandTotal += $variantTotal;
                                                    $attributes = $variant->attributeValues
                                                        ->map(fn ($v) => ($v->attribute->name ?? '') . ': ' . ($v->value ?? ''))
                                                        ->implode(' / ');
                                                @endphp
                                                <tr>
                                                    <td>
                                                        <strong>{{ $attributes ?: 'متغيّر #' . $variant->id }}</strong>
                                                        @if ($variant->is_default)
                                                            <span class="badge badge-light-primary">افتراضي</span>
                                                        @endif
                                                        @unless ($variant->is_active)
                                                            <span class="badge badge-light">غير نشط</span>
                                                        @endunless
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="stk-num d-block">{{ $variant->sku ?: '—' }}</span>
                                                        @if ($variant->barcode)
                                                            <span class="stk-media__sub stk-num">{{ $variant->barcode }}</span>
                                                        @endif
                                                    </td>
                                                    @foreach ($variantWarehouses as $warehouse)
                                                        @php $stock = $stockByWarehouse->get($warehouse->id); @endphp
                                                        <td class="text-center stk-num">
                                                            {{ $stock ? number_format($stock->quantity) : 0 }}
                                                        </td>
                                                    @endforeach
                                                    <td class="text-center stk-num"><strong>{{ number_format($variantTotal) }}</strong></td>
                                                </tr>
                                            @endforeach

                                            <tr class="stk-total-row">
                                                <td colspan="2"><strong>الإجمالي</strong></td>
                                                @foreach ($variantWarehouses as $warehouse)
                                                    @php
                                                        $columnTotal = $variants->sum(fn ($v) => (int) optional(
                                                            $v->warehouseStock->firstWhere('storehouse_id', $warehouse->id)
                                                        )->quantity);
                                                    @endphp
                                                    <td class="text-center stk-num">{{ number_format($columnTotal) }}</td>
                                                @endforeach
                                                <td class="text-center stk-num stk-balance">{{ number_format($grandTotal) }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    @endif

                    {{-- وحدات المنتج --}}
                    @if ($units->count())
                        <div class="card stk-table-card">
                            <div class="stk-table-head">
                                <h6 class="stk-table-head__title">
                                    <i class="feather icon-box"></i> وحدات المنتج
                                    <span class="stk-count">{{ $units->count() }}</span>
                                </h6>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>الوحدة</th>
                                            <th class="text-center">الباركود</th>
                                            <th class="text-center">شراء</th>
                                            <th class="text-center">بيع</th>
                                            <th class="text-center">أدنى بيع</th>
                                            <th class="text-center">جملة</th>
                                            <th class="text-center">الحالة</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($units as $productUnit)
                                            <tr>
                                                <td>
                                                    <strong>{{ optional(optional($productUnit->categoryUnit)->unit)->name ?? 'غير محدد' }}</strong>
                                                    @if ($productUnit->is_default)
                                                        <span class="badge badge-light-primary">افتراضي</span>
                                                    @endif
                                                </td>
                                                <td class="text-center stk-num">{{ $productUnit->barcode ?: '—' }}</td>
                                                <td class="text-center stk-num">
                                                    {{ $productUnit->purchase_price ? App\Support\CurrencySettings::format($productUnit->purchase_price) : '—' }}
                                                </td>
                                                <td class="text-center stk-num">
                                                    {{ $productUnit->sale_price ? App\Support\CurrencySettings::format($productUnit->sale_price) : '—' }}
                                                </td>
                                                <td class="text-center stk-num">
                                                    {{ $productUnit->min_sale_price ? App\Support\CurrencySettings::format($productUnit->min_sale_price) : '—' }}
                                                </td>
                                                <td class="text-center stk-num">
                                                    {{ $productUnit->wholesale_price ? App\Support\CurrencySettings::format($productUnit->wholesale_price) : '—' }}
                                                </td>
                                                <td class="text-center">
                                                    <span class="stk-state stk-state--{{ $productUnit->is_active ? 'on' : 'off' }}">
                                                        {{ $productUnit->is_active ? 'نشط' : 'غير نشط' }}
                                                    </span>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                </div>

                {{-- ═════ حركة المخزون ═════ --}}
                @if ($isStocked)
                    <div class="tab-pane" id="profile" role="tabpanel">
                        <div id="stock-movements-content">
                            @include('stock::products.partials.stock_movements', [
                                'stock_movements' => $stock_movements ?? collect(),
                                'product' => $product,
                                'permission_sources' => $permission_sources ?? collect(),
                            ])
                        </div>
                    </div>
                @endif

                {{-- ═════ المكوّنات ═════ --}}
                @if ($isCompiled)
                    <div class="tab-pane" id="compiled" role="tabpanel">
                        <div class="card stk-table-card">
                            @if ($CompiledProducts && count($CompiledProducts) > 0)
                                <div class="stk-table-head">
                                    <h6 class="stk-table-head__title">
                                        <i class="feather icon-layers"></i> مكوّنات التجميعة
                                        <span class="stk-count">{{ count($CompiledProducts) }}</span>
                                    </h6>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th style="width:44px">#</th>
                                                <th>المكوّن</th>
                                                <th class="text-center">الكمية المطلوبة</th>
                                                <th class="text-center">الحالة</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($CompiledProducts as $index => $component)
                                                <tr>
                                                    <td class="stk-num text-muted">{{ $index + 1 }}</td>
                                                    <td>
                                                        @if ($component->Product)
                                                            <a href="{{ route('products.show', $component->product_id) }}"
                                                               class="stk-media__title">{{ $component->Product->name }}</a>
                                                        @else
                                                            <span class="text-muted">منتج محذوف</span>
                                                        @endif
                                                    </td>
                                                    <td class="text-center stk-num">{{ $component->qyt ?? 0 }}</td>
                                                    <td class="text-center">
                                                        @if ($component->Product)
                                                            <span class="stk-state stk-state--{{ $component->Product->status->value === 'active' ? 'on' : 'off' }}">
                                                                {{ $component->Product->status->label() }}
                                                            </span>
                                                        @else
                                                            <span class="text-muted">—</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="card-body">
                                    <div class="stk-empty">
                                        <div class="stk-empty__icon"><i class="feather icon-layers"></i></div>
                                        <h5 class="stk-empty__title">لا مكوّنات</h5>
                                        <p class="stk-empty__hint">لم تُضَف أي منتجات لهذه التجميعة بعد</p>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

                {{-- ═════ الخطّ الزمني ═════
                     المُعرّف هنا هو ما تكتب فيه loadTimeline؛ كان غائباً فلا
                     يظهر شيء عند فتح التبويب. --}}
                <div class="tab-pane" id="about" role="tabpanel">
                    <div class="card">
                        <div class="card-body" id="timeline-content">
                            @include('stock::products.partials.timeline', [
                                'stock_movements' => $stock_movements ?? collect(),
                                'product' => $product,
                                'average_cost' => $average_cost ?? 0,
                            ])
                        </div>
                    </div>
                </div>

                {{-- ═════ سجلّ النشاط ═════ --}}
                <div class="tab-pane" id="activate" role="tabpanel">
                    <div class="card">
                        <div class="card-body" id="activity-logs-content">
                            @include('stock::products.partials.activity_logs', ['logs' => $logs])
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- نافذة الحذف: نموذج DELETE لا رابط GET — كان الرابط يصطدم بـ405 --}}
    <div class="modal fade" id="modal_DELETE" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content text-right">
                <div class="modal-header">
                    <h5 class="modal-title">حذف {{ $product->name }}</h5>
                    <button type="button" class="close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="إغلاق">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="mb-1">سيُحذف المنتج نهائياً.</p>
                    <div class="alert alert-warning mb-0">
                        <i class="feather icon-alert-triangle"></i>
                        يُحذف معه كل ما ارتبط به من بيانات — لا يمكن التراجع.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal" data-bs-dismiss="modal">
                        إلغاء
                    </button>
                    <form method="POST" action="{{ route('products.delete', $product->id) }}" class="d-inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger">
                            <i class="feather icon-trash-2"></i> حذف نهائي
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        (function () {
            const productId = @json($product->id);
            const base = @json(url('/stock/products'));
            const token = $('meta[name="csrf-token"]').attr('content');

            /* تحميل محتوى تبويب بـAjax — النمط واحد للثلاثة، فلا داعي لثلاث
               دوال بأجسامٍ متطابقة. تُعرَّف على window لأن الترقيم المحقون
               ينادي `loadTimeline(2)` و`loadActivityLogs(2)` بالاسم. */
            function loader(url, target) {
                return function (page) {
                    const $box = $(target);
                    $box.html('<div class="text-center p-4"><div class="spinner-border text-primary" role="status"></div></div>');

                    $.getJSON(url, { page: page || 1 })
                        .done(function (response) {
                            if (response && response.html) {
                                $box.html(response.html);
                            } else {
                                $box.html('<div class="alert alert-info mb-0">لا توجد بيانات</div>');
                            }
                        })
                        .fail(function () {
                            $box.html('<div class="alert alert-danger mb-0">تعذّر تحميل البيانات</div>');
                        });
                };
            }

            window.loadTimeline = loader(base + '/timeline/' + productId, '#timeline-content');
            window.loadActivityLogs = loader(base + '/activity-logs/' + productId, '#activity-logs-content');

            $(function () {
                /* المحتوى مُصيَّرٌ من الخادم أصلاً؛ لا نعيد جلبه إلا عند
                   التنقّل بين الصفحات داخل التبويب. */

                $('.status-toggle-btn').on('click', function () {
                    const $btn = $(this);
                    const next = $btn.data('current-status') === 'active' ? 'unactive' : 'active';
                    const original = $btn.html();

                    $btn.prop('disabled', true).text('جارٍ…');

                    $.post(base + '/' + productId + '/update-status', { status: next, _token: token })
                        .done(function (response) {
                            if (response && response.success) {
                                location.reload();
                                return;
                            }
                            alert((response && response.message) || 'تعذّر تحديث الحالة');
                            $btn.prop('disabled', false).html(original);
                        })
                        .fail(function (xhr) {
                            alert((xhr.responseJSON && xhr.responseJSON.message) || 'تعذّر تحديث الحالة');
                            $btn.prop('disabled', false).html(original);
                        });
                });
            });
        })();
    </script>
@endsection
