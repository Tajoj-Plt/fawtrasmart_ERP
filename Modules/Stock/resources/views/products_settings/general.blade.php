@extends('stock::master')

@section('title', 'إعدادات المخزون — عام')

@section('content')
    <div class="content-body">

        {{--
            أُعيد بناء الشاشة على نظام تصميم الوحدة (stk-*) بدل صفحةٍ قديمة
            تحمل ٢٦٠ سطر CSS خاصاً بها (شريط جانبي مثبّت، بنفسجي مكتوب
            بالقيمة، أيقونات fa) لا يشبه شيئاً من شاشات النظام.

            **والمفاتيح فُرزت على الصدق**: التدقيق أظهر أن سبعة من أحد عشر
            مفتاحاً تُحفَظ ولا يقرؤها سطرٌ واحد في النظام. كانت تُعرض كلُّها
            سواسية فيطفئ المستخدم مفتاحاً ولا يتغيّر شيء ويظنّ النظام
            معطوباً. الميتة الآن معطَّلة بشارة «قريباً» — كما تفرّق بلاطات
            إعدادات الجرد بين المبنيّ والموعود — وقيمها المحفوظة تُمرَّر
            كما هي فلا تُصفَّر خلسة.
        --}}

        @include('stock::components.page-head', [
            'title' => 'إعدادات المخزون',
            'icon' => 'sliders',
            'sub' => 'الافتراضيات وخيارات تشغيل المخزون',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'إعدادات الجرد' => route('inventory_settings.index'),
                'عام' => null,
            ],
            'actions' => '<button type="submit" form="inventory-settings-form" class="btn btn-primary">'
                . '<i class="feather icon-check"></i> حفظ الإعدادات</button>',
        ])

        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        <form id="inventory-settings-form" action="{{ route('inventory_settings.store') }}" method="POST">
            @csrf

            @php
                $settings = $general_settings;
                $isOn = fn (string $key) => (bool) old($key, $settings->{$key} ?? 0);

                // المفاتيح المطبَّقة فعلاً — لكلٍّ منها كودٌ يقرؤها
                $liveToggles = [
                    ['key' => 'enable_negative_stock', 'icon' => 'trending-down', 'title' => 'السماح بالمخزون السالب', 'hint' => 'قبول الصرف والبيع ولو تجاوز الرصيد المتاح — بلا تفعيله تُرفض الحركة التي تُسالب الرصيد'],
                    ['key' => 'show_available_quantity_in_warehouse', 'icon' => 'eye', 'title' => 'إظهار الكمية في المخزن', 'hint' => 'عمود الكمية في قائمة المنتجات، وبطاقة الإجمالي وجدول المستودعات في شاشة المنتج'],
                    ['key' => 'enable_multi_units_system', 'icon' => 'box', 'title' => 'نظام الوحدات المتعددة', 'hint' => 'وحدات بيعٍ وشراءٍ متعددة للصنف الواحد بمعاملات تحويل'],
                    ['key' => 'enable_assembly_and_compound_units', 'icon' => 'layers', 'title' => 'التجميعات والوحدات المركبة', 'hint' => 'منتجات تُركَّب من مكوّنات، وتظهر شاشاتها في قائمة المنتجات'],
                ];

                // مفاتيح تُحفَظ ولا يقرؤها النظام بعد — تُعرض معطَّلة بلا إيهام
                $pendingToggles = [
                    ['key' => 'advanced_pricing_options', 'title' => 'خيارات التسعير المتقدمة'],
                    ['key' => 'enable_stock_requests', 'title' => 'طلبات المخزون بين المستودعات'],
                    ['key' => 'enable_sales_stock_authorization', 'title' => 'اعتماد مخزني لفواتير البيع'],
                    ['key' => 'enable_purchase_stock_authorization', 'title' => 'اعتماد مخزني لفواتير الشراء'],
                    ['key' => 'track_products_by_serial_or_batch', 'title' => 'تفعيل التتبع بالتسلسلي/الشحنة افتراضياً'],
                    ['key' => 'allow_negative_tracking_elements', 'title' => 'السالب لعناصر التتبع'],
                    ['key' => 'inventory_quantity_by_date', 'title' => 'حساب كمية الجرد حسب التاريخ'],
                ];
            @endphp

            <div class="row">
                <div class="col-lg-5 col-12">
                    {{-- ══════════ الافتراضيات ══════════ --}}
                    <div class="card stk-card--tight">
                        <div class="card-header">
                            <h4 class="card-title"><i class="feather icon-anchor"></i> الافتراضيات</h4>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label for="storehouse_id">المستودع الافتراضي</label>
                                <select id="storehouse_id" name="storehouse_id" class="form-control select2">
                                    <option value="">— بلا افتراضي —</option>
                                    @foreach ($storehouses as $storehouse)
                                        <option value="{{ $storehouse->id }}"
                                            {{ old('storehouse_id', $settings->storehouse_id ?? null) == $storehouse->id ? 'selected' : '' }}>
                                            {{ $storehouse->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <small class="text-muted">يُفتح تلقائياً في العمليات التي لا يُحدَّد لها مستودع.</small>
                            </div>

                            <div class="form-group">
                                {{-- كان هذا الحقل يعرض ويحفظ قيمة «المستودع الافتراضي»
                                     خطأً (نسخ ولصق) — صار يقرأ قيمته الصحيحة --}}
                                <label for="sub_account">الحساب الفرعي الافتراضي</label>
                                <select id="sub_account" name="sub_account" class="form-control select2">
                                    <option value="">— بلا افتراضي —</option>
                                    @foreach ($storehouses as $storehouse)
                                        <option value="{{ $storehouse->id }}"
                                            {{ old('sub_account', $settings->sub_account ?? null) == $storehouse->id ? 'selected' : '' }}>
                                            {{ $storehouse->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="form-group mb-0">
                                <label for="price_list_id">قائمة الأسعار الافتراضية</label>
                                <select id="price_list_id" name="price_list_id" class="form-control select2">
                                    <option value="">— بلا افتراضي —</option>
                                    @foreach ($price_lists as $price_list)
                                        <option value="{{ $price_list->id }}"
                                            {{ old('price_list_id', $settings->price_list_id ?? null) == $price_list->id ? 'selected' : '' }}>
                                            {{ $price_list->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <small class="text-muted">تُطبَّق على المنتجات الجديدة تلقائياً.</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-7 col-12">
                    {{-- ══════════ خيارات التشغيل ══════════ --}}
                    <div class="card stk-card--tight">
                        <div class="card-header">
                            <h4 class="card-title"><i class="feather icon-toggle-right"></i> خيارات التشغيل</h4>
                        </div>
                        <div class="card-body">
                            @foreach ($liveToggles as $toggle)
                                <div class="d-flex align-items-start justify-content-between py-1 {{ $loop->last ? '' : 'border-bottom' }}"
                                     style="gap:.75rem">
                                    <div>
                                        <strong class="d-block" style="font-size:.86rem">
                                            <i class="feather icon-{{ $toggle['icon'] }} text-primary"></i>
                                            {{ $toggle['title'] }}
                                        </strong>
                                        <span class="text-muted" style="font-size:.76rem">{{ $toggle['hint'] }}</span>
                                    </div>
                                    <div class="custom-control custom-switch custom-switch-success flex-shrink-0">
                                        <input type="checkbox" class="custom-control-input" value="1"
                                               id="{{ $toggle['key'] }}" name="{{ $toggle['key'] }}"
                                               {{ $isOn($toggle['key']) ? 'checked' : '' }}>
                                        <label class="custom-control-label" for="{{ $toggle['key'] }}"></label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- ══════════ قيد التطوير ══════════ --}}
                    <div class="card stk-card--tight">
                        <div class="card-header">
                            <h4 class="card-title"><i class="feather icon-clock"></i> قيد التطوير</h4>
                        </div>
                        <div class="card-body">
                            <p class="text-muted mb-1" style="font-size:.78rem">
                                هذه الخيارات لم تُفعَّل في النظام بعد — تغييرها لا يؤثر حالياً، وستصير
                                قابلةً للضبط فور بناء ميزتها.
                            </p>

                            @foreach ($pendingToggles as $toggle)
                                {{-- القيمة المحفوظة تُمرَّر كما هي حتى لا يصفّرها الحفظ خلسة --}}
                                @if ($isOn($toggle['key']))
                                    <input type="hidden" name="{{ $toggle['key'] }}" value="1">
                                @endif

                                <div class="d-flex align-items-center justify-content-between py-1 {{ $loop->last ? '' : 'border-bottom' }}"
                                     style="gap:.75rem; opacity:.65">
                                    <span style="font-size:.82rem">
                                        {{ $toggle['title'] }}
                                        <span class="badge badge-light">قريباً</span>
                                    </span>
                                    <div class="custom-control custom-switch flex-shrink-0">
                                        <input type="checkbox" class="custom-control-input" disabled
                                               id="{{ $toggle['key'] }}" {{ $isOn($toggle['key']) ? 'checked' : '' }}>
                                        <label class="custom-control-label" for="{{ $toggle['key'] }}"></label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end mb-2">
                <a href="{{ route('inventory_settings.index') }}" class="btn btn-outline-secondary mr-1 me-1">
                    <i class="feather icon-arrow-right"></i> رجوع
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="feather icon-check"></i> حفظ الإعدادات
                </button>
            </div>
        </form>
    </div>
@endsection

@section('scripts')
    <script>
        $(function () {
            if ($.fn.select2) {
                $('.select2').select2({ dir: 'rtl', width: '100%' });
            }
        });
    </script>
@endsection
