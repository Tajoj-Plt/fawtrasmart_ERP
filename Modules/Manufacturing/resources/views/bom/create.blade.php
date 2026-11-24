@extends('manufacturing::master')

@php
    /*
        نموذجُ قائمة المواد — للإنشاء والتعديل معاً (`bom/edit.blade.php`
        يمتدّ من هذا الملف ولا يزيد عليه).

        كانت الشاشتان نسختين: الإنشاءُ يبني صفوفه بالسكربت والتعديلُ يبنيها
        من الخادم بقوالبَ مكرّرة، فتباعدتا — قائمةُ الوحدات تُقرأ من جدولٍ
        فارغ وهي إلزامية، والوحدةُ المختارة لا تُحفظ ولا تُعاد، ومنتجاتُ
        التعديل غير منتجات الإنشاء. الآن محرّكٌ واحد يبني الصفوف من
        `$form['rows']` في الحالين: المحفوظُ عند التعديل، والمُدخَلُ عند
        العودة بخطأ تحقّق.

        المتغيّرات من `BOMController::bomFormData()`:
        $productionMaterial (null في الإنشاء) · $products · $accounts ·
        $paths · $form[reference|units|rows] · $currencyCode · $serial_number
    */
    $bom = $productionMaterial ?? null;
    $returned = old('_token') !== null;

    $costTypes = collect([1 => 'مبلغ ثابت', 2 => 'بناءً على الكمية', 3 => 'معادلة'])
        ->map(fn ($label, $id) => ['id' => $id, 'name' => $label])->values();

    /*
        أعمدةُ كل جدول وعرضُها الأدنى — العرضُ على العمود لا على الحقل،
        فلا تنضغط قائمةُ المنتج إلى حرفين ولا يخرج حقلٌ عن خليّته.
    */
    $sections = [
        [
            'key' => 'raw',
            'title' => 'المواد الخام',
            'icon' => 'package',
            'add' => 'إضافة مادة خام',
            'sign' => '+',
            'headers' => [
                'المنتج' => 220, 'الوحدة' => 175, 'سعر الوحدة' => 115,
                'الكمية' => 115, 'المرحلة' => 170, 'الإجمالي' => 125,
            ],
        ],
        [
            'key' => 'expenses',
            'title' => 'المصروفات',
            'icon' => 'dollar-sign',
            'add' => 'إضافة مصروف',
            'sign' => '+',
            'headers' => [
                'الحساب' => 200, 'نوع التكلفة' => 150, 'المبلغ' => 110,
                'الوصف' => 170, 'المرحلة' => 170, 'الإجمالي' => 120,
            ],
            'hint' => '«مبلغ ثابت» يبقى كما هو مهما كانت كمية الإنتاج. «بناءً على الكمية» مبلغُه لكمية هذه القائمة، ويُقاس مع كمية أمر التصنيع.',
        ],
        [
            'key' => 'manufacturing',
            'title' => 'عمليات التصنيع',
            'icon' => 'settings',
            'add' => 'إضافة عملية',
            'sign' => '+',
            'headers' => [
                'محطة العمل' => 165, 'نوع التكلفة' => 135, 'الوقت (ساعة)' => 105,
                'تكلفة الساعة' => 105, 'الوصف' => 125, 'المرحلة' => 150, 'الإجمالي' => 115,
            ],
            'hint' => 'الإجمالي = تكلفة الساعة × وقت التشغيل.',
        ],
        [
            'key' => 'endlife',
            'title' => 'المواد الهالكة',
            'icon' => 'trash-2',
            'add' => 'إضافة مادة هالكة',
            'sign' => '−',
            'headers' => [
                'المنتج' => 260, 'السعر' => 130, 'الكمية' => 130,
                'المرحلة' => 200, 'الإجمالي' => 140,
            ],
            'hint' => 'قيمة الهالك والنواتج الثانوية تُطرح من تكلفة القائمة.',
        ],
    ];

    $pageTitle = $bom ? 'تعديل قائمة المواد' : 'قائمة مواد إنتاج جديدة';
    $cancelUrl = $bom ? route('Bom.show', $bom->id) : route('BOM.index');
    $submitLabel = $bom ? 'حفظ التعديلات' : 'حفظ القائمة';
@endphp

@section('title', $bom ? 'تعديل ' . $bom->name : 'قائمة مواد إنتاج جديدة')

@section('content')
    <div class="content-body">

        @include('stock::components.page-head', [
            'title' => $pageTitle,
            'icon' => $bom ? 'edit-2' : 'layers',
            'sub' => $bom ? $bom->name : 'مكوّنات المنتج المصنَّع: مواد خام ومصروفات وعمليات وهوالك',
            'crumbs' => $bom
                ? [
                    'الرئيسية' => url('/'),
                    'قوائم المواد' => route('BOM.index'),
                    $bom->name => route('Bom.show', $bom->id),
                    'تعديل' => null,
                ]
                : [
                    'الرئيسية' => url('/'),
                    'قوائم المواد' => route('BOM.index'),
                    'جديدة' => null,
                ],
            'actions' => '<a href="' . $cancelUrl . '" class="btn btn-outline-secondary">'
                . '<i class="feather icon-x"></i> إلغاء</a>'
                . '<button type="submit" form="bomForm" class="btn btn-primary">'
                . '<i class="feather icon-check"></i> ' . $submitLabel . '</button>',
        ])

        @include('layouts.alerts.error')
        @include('stock::components.form-errors')
        @include('layouts.alerts.success')

        <form id="bomForm" method="POST" enctype="multipart/form-data"
              action="{{ $bom ? route('Bom.update', $bom->id) : route('Bom.store') }}">
            @csrf
            @if ($bom)
                @method('PUT')
            @endif

            {{-- ══════════ بيانات القائمة ══════════ --}}
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title"><i class="feather icon-info"></i> بيانات القائمة</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        {{-- «تابع لـ» — الجهة التي تتبعها القائمة --}}
                        @include('manufacturing::partials.affiliation-field', $bom ? ['value' => $bom->branch_id] : [])

                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="name" class="stk-req">الاسم</label>
                                <input type="text" id="name" name="name" required maxlength="255" class="form-control"
                                       value="{{ old('name', $bom->name ?? '') }}">
                            </div>
                        </div>

                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="code">الكود</label>
                                {{-- للعرض فقط — يُصدَر في الخادم عند الحفظ، ولا يُعاد ترقيمه بالتعديل --}}
                                <input type="text" id="code" disabled
                                       class="form-control text-center stk-num"
                                       value="{{ $bom->code ?? ($serial_number ?? '') }}">
                                @unless ($bom)
                                    <span class="stk-help">يُصدَر تلقائياً من إعدادات الترقيم عند الحفظ</span>
                                @endunless
                            </div>
                        </div>

                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="quantity" class="stk-req">كمية الإنتاج</label>
                                <input type="number" id="quantity" name="quantity" required min="0.01" step="any"
                                       class="form-control stk-num"
                                       value="{{ old('quantity', $bom ? (float) $bom->quantity : 1) }}">
                                <span class="stk-help">الكمية التي تُنتجها المكوّنات أدناه</span>
                            </div>
                        </div>

                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="product_id" class="stk-req">المنتج</label>
                                {{-- المنتجُ المصنَّع — نهائيّ أو نصفُ مصنَّع. والموادُّ الخام في الجداول أدناه --}}
                                <select id="product_id" name="product_id" required class="form-control select2">
                                    <option value="">اختر المنتج…</option>
                                    @foreach ($products as $product)
                                        <option value="{{ $product->id }}"
                                                data-unit="{{ $product->bom_unit_name }}"
                                                {{ old('product_id', $bom->product_id ?? '') == $product->id ? 'selected' : '' }}>
                                            {{ $product->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <span class="stk-help" id="product-unit-display"></span>
                            </div>
                        </div>

                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="account_id" class="stk-req">الحساب</label>
                                <select id="account_id" name="account_id" required class="form-control select2">
                                    <option value="">اختر الحساب…</option>
                                    {{-- حساباتُ الأصول — لا حساباتُ مصروف البنود --}}
                                    @foreach ($headerAccounts ?? $accounts as $account)
                                        <option value="{{ $account->id }}"
                                            {{ old('account_id', $bom->account_id ?? '') == $account->id ? 'selected' : '' }}>
                                            {{ $account->code ? $account->code . ' — ' : '' }}{{ $account->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <span class="stk-help">من حسابات الأصول (مخزون المنتج)</span>
                            </div>
                        </div>

                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="production_path_id" class="stk-req">مسار الإنتاج</label>
                                <select id="production_path_id" name="production_path_id" required class="form-control select2">
                                    <option value="">اختر المسار…</option>
                                    @foreach ($paths as $path)
                                        <option value="{{ $path->id }}"
                                            {{ old('production_path_id', $bom->production_path_id ?? '') == $path->id ? 'selected' : '' }}>
                                            {{ $path->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        {{--
                            المفتاحان بعد خطأ تحقّق يعودان بما أُرسل: المربّعُ غير
                            المؤشَّر لا يُرسَل أصلاً، فـ`old('status', 1)` كانت
                            تُعيده مؤشَّراً وقد أطفأه المستخدم.
                        --}}
                        <div class="col-md-6">
                            <label class="stk-switch">
                                <input type="checkbox" name="status" value="1"
                                       {{ ($returned ? old('status') : ($bom->status ?? 1)) ? 'checked' : '' }}>
                                <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                                <span class="stk-switch__label">
                                    <span class="stk-switch__title">القائمة نشطة</span>
                                    <span class="stk-switch__hint">غير النشطة لا تُستعمل في أوامر التصنيع</span>
                                </span>
                            </label>
                        </div>

                        <div class="col-md-6">
                            <label class="stk-switch">
                                <input type="checkbox" name="default" value="1"
                                       {{ ($returned ? old('default') : ($bom->default ?? 0)) ? 'checked' : '' }}>
                                <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                                <span class="stk-switch__label">
                                    <span class="stk-switch__title">القائمة الافتراضية</span>
                                    <span class="stk-switch__hint">تُختار تلقائياً لهذا المنتج</span>
                                </span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ══════════ المكوّنات ══════════ --}}
            @foreach ($sections as $section)
                <div class="card stk-table-card" data-section="{{ $section['key'] }}">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title">
                            <i class="feather icon-{{ $section['icon'] }}"></i> {{ $section['title'] }}
                            <span class="stk-count" data-count>0</span>
                        </h6>

                        <div class="d-flex align-items-center" style="gap:.6rem">
                            <span class="stk-balance stk-num" data-subtotal>0.00</span>
                            <button type="button" class="btn btn-sm btn-outline-primary" data-add>
                                <i class="feather icon-plus"></i> {{ $section['add'] }}
                            </button>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover mb-0"
                               style="min-width:{{ array_sum($section['headers']) + 60 }}px">
                            <thead>
                                <tr>
                                    @foreach ($section['headers'] as $header => $width)
                                        <th class="{{ $loop->first ? '' : 'text-center' }}"
                                            style="min-width:{{ $width }}px">{{ $header }}</th>
                                    @endforeach
                                    <th class="text-center" style="width:60px">حذف</th>
                                </tr>
                            </thead>
                            <tbody data-rows></tbody>
                        </table>
                    </div>

                    <div class="card-body" data-empty>
                        <div class="stk-empty">
                            <div class="stk-empty__icon"><i class="feather icon-{{ $section['icon'] }}"></i></div>
                            <h5 class="stk-empty__title">لا {{ $section['title'] }}</h5>
                            <p class="stk-empty__hint">اضغط «{{ $section['add'] }}» لإضافة أول صفّ</p>
                        </div>
                    </div>

                    @isset ($section['hint'])
                        <div class="card-body pt-0 pb-1">
                            <span class="stk-help"><i class="feather icon-info"></i> {{ $section['hint'] }}</span>
                        </div>
                    @endisset
                </div>
            @endforeach

            {{-- ══════════ الإجمالي ══════════ --}}
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title"><i class="feather icon-bar-chart-2"></i> إجمالي التكلفة</h4>
                    <span class="stk-count">{{ $currencyCode ?? App\Support\CurrencySettings::code() }}</span>
                </div>
                <div class="card-body">
                    <dl class="stk-def">
                        @foreach ($sections as $section)
                            <div class="stk-def__row">
                                <dt>{{ $section['sign'] }} {{ $section['title'] }}</dt>
                                <dd class="stk-num" data-total-for="{{ $section['key'] }}">0.00</dd>
                            </div>
                        @endforeach

                        <div class="stk-def__row">
                            <dt><strong>الإجمالي النهائي</strong></dt>
                            <dd class="stk-num stk-balance" id="grand-total">0.00</dd>
                        </div>
                    </dl>

                    {{-- للعرض وحده: الخادم يحسب الإجمالي من البنود ولا يقرأ رقماً مرسَلاً --}}
                    <span class="stk-help">الإجمالي = المواد الخام + المصروفات + عمليات التصنيع − المواد الهالكة</span>
                </div>

                <div class="stk-form-foot">
                    <span class="stk-help me-auto">الحقول المعلَّمة بـ<span class="stk-req"></span> إلزامية</span>
                    <a href="{{ $cancelUrl }}" class="btn btn-outline-secondary">
                        <i class="feather icon-x"></i> إلغاء
                    </a>
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <i class="feather icon-check"></i> {{ $submitLabel }}
                    </button>
                </div>
            </div>
        </form>
    </div>
@endsection

@section('scripts')
    <script src="{{ asset('app-assets/vendors/js/extensions/sweetalert2.all.min.js') }}"></script>
    <script>
        $(function () {
            const data = @json($form['reference']);
            const productUnits = @json($form['units']);
            const initial = @json($form['rows']);

            data.costTypes = @json($costTypes);

            const esc = value => $('<div>').text(value == null ? '' : value).html();
            const fixed = value => Number(value || 0).toFixed(2);
            const number = value => parseFloat(value) || 0;
            // التقريبُ نفسُه في الخادم (`RecalculatesTotals::money`) — فيتطابق الرقمان
            const money = value => Math.round((Number(value) || 0) * 100 + Number.EPSILON) / 100;

            function options(list, placeholder) {
                const head = placeholder ? '<option value="">' + placeholder + '</option>' : '';

                return head + list.map(item =>
                    '<option value="' + item.id + '"'
                    + (item.cost !== undefined ? ' data-cost="' + item.cost + '"' : '')
                    + '>' + esc(item.name) + '</option>'
                ).join('');
            }

            const cell = inner => '<td>' + inner + '</td>';

            /* المرحلة إلزاميةٌ حين توجد مراحل — وإلّا تعذّر الحفظ على من لم يعرّف مساراً بمراحله */
            const stage = name => '<select name="' + name + '" class="form-control select2 js-stage"'
                + (data.stages.length ? ' required' : '') + '>'
                + options(data.stages, 'اختر المرحلة…') + '</select>';

            const total = name => '<input type="number" step="0.01" readonly tabindex="-1" name="' + name + '"'
                + ' class="form-control stk-num text-center js-total">';

            /* بناء صفٍّ لكل قسم — الفروق كلها هنا، والسلوك مشترك */
            const builders = {
                raw: () => ''
                    + cell('<select name="raw_product_id[]" class="form-control select2 js-product" data-price="purchase" required>'
                        + options(data.products, 'اختر المنتج…') + '</select>')
                    /*
                        الوحدة من وحدات المنتج المختار، ومعاملُ تحويلها في الحقل
                        المخفي بجوارها. القائمةُ لا تُعطَّل أبداً: الحقلُ المعطَّل
                        لا يُرسَل، فتنزاح `raw_unit_id[]` عن صفوفها.
                    */
                    + cell('<select name="raw_unit_id[]" class="form-control js-unit">'
                        + '<option value="" data-factor="1">الوحدة الأساسية</option></select>'
                        + '<input type="hidden" name="raw_conversion_factor[]" class="js-factor" value="1">')
                    + cell('<input type="number" step="0.01" min="0" name="raw_unit_price[]"'
                        + ' class="form-control stk-num text-center js-price">')
                    + cell('<input type="number" step="any" min="0.0001" value="1" required'
                        + ' name="raw_quantity[]" class="form-control stk-num text-center js-qty">'
                        + '<small class="stk-help js-unit-note" hidden></small>')
                    + cell(stage('raw_production_stage_id[]'))
                    + cell(total('raw_total[]')),

                expenses: () => ''
                    + cell('<select name="expenses_account_id[]" class="form-control select2 js-account" required>'
                        + options(data.accounts, 'اختر الحساب…') + '</select>')
                    + cell('<select name="expenses_cost_type[]" class="form-control js-cost-type">'
                        + options(data.costTypes, '') + '</select>')
                    + cell('<input type="number" step="0.01" min="0" required name="expenses_price[]"'
                        + ' class="form-control stk-num text-center js-amount">')
                    + cell('<textarea name="expenses_description[]" class="form-control js-description" rows="1"></textarea>')
                    + cell(stage('expenses_production_stage_id[]'))
                    + cell(total('expenses_total[]')),

                manufacturing: () => ''
                    + cell('<select name="workstation_id[]" class="form-control select2 js-workstation" required>'
                        + options(data.workstations, 'اختر المحطة…') + '</select>')
                    + cell('<select name="manu_cost_type[]" class="form-control js-cost-type">'
                        + options(data.costTypes, '') + '</select>')
                    + cell('<input type="number" step="0.01" min="0.01" required name="operating_time[]"'
                        + ' class="form-control stk-num text-center js-time">')
                    + cell('<input type="number" step="0.01" min="0" name="manu_total_cost[]"'
                        + ' class="form-control stk-num text-center js-cost">')
                    + cell('<textarea name="manu_description[]" class="form-control js-description" rows="1"></textarea>')
                    + cell(stage('manu_production_stage_id[]'))
                    + cell(total('manu_total[]')),

                endlife: () => ''
                    + cell('<select name="end_life_product_id[]" class="form-control select2 js-product" data-price="purchase" required>'
                        + options(data.products, 'اختر المنتج…') + '</select>')
                    + cell('<input type="number" step="0.01" min="0" name="end_life_unit_price[]"'
                        + ' class="form-control stk-num text-center js-price">')
                    + cell('<input type="number" step="any" min="0.0001" value="1" required'
                        + ' name="end_life_quantity[]" class="form-control stk-num text-center js-qty">')
                    + cell(stage('end_life_production_stage_id[]'))
                    + cell(total('end_life_total[]')),
            };

            // الهوالك تُطرح من التكلفة لا تُضاف إليها — والقاعدةُ نفسُها في الخادم
            const subtracted = { endlife: true };

            /*
                إجمالي الصفّ — الصيغُ نفسُها في `BuildsBomItems`:
                  التشغيل     تكلفة الساعة × وقت التشغيل
                  المصروف     المبلغ كما هو — لا يُضرب في كمية الإنتاج
                  مادة/هالك   السعر × الكمية
            */
            function rowTotal($row) {
                if ($row.find('.js-time').length) {
                    return money(money($row.find('.js-cost').val()) * number($row.find('.js-time').val()));
                }

                if ($row.find('.js-amount').length) {
                    return money($row.find('.js-amount').val());
                }

                return money(money($row.find('.js-price').val()) * number($row.find('.js-qty').val()));
            }

            function refresh() {
                let grand = 0;

                $('[data-section]').each(function () {
                    const $card = $(this);
                    const key = $card.data('section');
                    let subtotal = 0;

                    $card.find('[data-rows] tr').each(function () {
                        const $row = $(this);
                        const value = rowTotal($row);

                        $row.find('.js-total').val(fixed(value));

                        /* الكمية بالوحدة الأساسية — تُعرض حين تكون الوحدةُ المختارة غيرَها */
                        if ($row.find('.js-factor').length) {
                            const factor = number($row.find('.js-factor').val()) || 1;

                            $row.find('.js-unit-note')
                                .prop('hidden', factor === 1)
                                .text('= ' + Number((number($row.find('.js-qty').val()) * factor).toFixed(4)) + ' بالوحدة الأساسية');
                        }

                        subtotal += value;
                    });

                    const count = $card.find('[data-rows] tr').length;

                    $card.find('[data-count]').text(count);
                    $card.find('[data-subtotal]').text(fixed(subtotal));
                    $card.find('[data-empty]')[0].hidden = count > 0;

                    $('[data-total-for="' + key + '"]').text(fixed(subtotal));
                    grand += subtracted[key] ? -subtotal : subtotal;
                });

                $('#grand-total').text(fixed(grand));
            }

            /*
                ملء قائمة الوحدات من وحدات المنتج المختار.

                `selectedUnit` غير معرَّف = صفٌّ جديد أو منتجٌ بُدِّل: تُختار
                الوحدةُ الافتراضية ويُملأ السعر. معرَّف = صفٌّ محفوظ: تُعاد
                وحدتُه ويبقى سعرُه كما حُفظ.
            */
            function loadUnits($row, selectedUnit) {
                const $product = $row.find('.js-product');
                const $unit = $row.find('.js-unit');
                const product = productUnits[$product.val()];
                const priceKey = $product.data('price') === 'sale' ? 'sale' : 'purchase';
                const fresh = selectedUnit === undefined;

                if (fresh) $row.find('.js-price').val(product ? String(Number(Number(product[priceKey] || 0).toFixed(6))) : '');

                if (! $unit.length) return;

                $unit.html('<option value="" data-factor="1">الوحدة الأساسية</option>');
                $unit.find('option').attr('data-price', product ? product[priceKey] : 0);
                $row.find('.js-factor').val(1);

                if (! product) return;

                (product.units || []).forEach(unit => {
                    $('<option>')
                        .val(unit.id)
                        .text(unit.name + (unit.base ? ' (أساسية)' : '') + (unit.factor !== 1 ? ' × ' + unit.factor : ''))
                        .attr('data-factor', unit.factor)
                        .attr('data-price', unit[priceKey])
                        .prop('selected', fresh ? unit.default : String(unit.id) === String(selectedUnit))
                        .appendTo($unit);
                });

                applyUnit($row, fresh);
            }

            function applyUnit($row, overwritePrice) {
                const option = $row.find('.js-unit option:selected')[0];
                const factor = option ? number(option.dataset.factor) || 1 : 1;
                const price = option ? number(option.dataset.price) : 0;

                $row.find('.js-factor').val(factor);
                if (overwritePrice && price > 0) $row.find('.js-price').val(fixed(price));
            }

            function fillRow($row, values) {
                const blank = value => value === undefined || value === null || value === '';

                $row.find('.js-product').val(values.product ?? '');

                if ($row.find('.js-product').length) {
                    loadUnits($row, values.unit ?? '');

                    /*
                        وحدةٌ محفوظة لم تعد في قائمة المنتج (عُطّلت أو حُذفت):
                        يبقى معاملُها كما حُفظ بدل أن يعود الصفّ صامتاً إلى
                        الوحدة الأساسية فتتغيّر الكمية الفعلية.
                    */
                    const factor = number(values.factor) || 1;

                    if ($row.find('.js-unit').length && ! $row.find('.js-unit').val() && factor !== 1) {
                        $row.find('.js-unit').append(
                            $('<option value="">').text('وحدة محفوظة × ' + factor).attr('data-factor', factor).prop('selected', true)
                        );
                        $row.find('.js-factor').val(factor);
                    }
                }

                $row.find('.js-account').val(values.account ?? '');
                $row.find('.js-workstation').val(values.workstation ?? '');
                $row.find('.js-cost-type').val(values.costType || 1);
                $row.find('.js-price').val(blank(values.price) ? '' : values.price);
                $row.find('.js-qty').val(blank(values.qty) ? '' : values.qty);
                $row.find('.js-amount').val(blank(values.amount) ? '' : values.amount);
                $row.find('.js-time').val(blank(values.time) ? '' : values.time);
                $row.find('.js-cost').val(blank(values.cost) ? '' : values.cost);
                $row.find('.js-description').val(values.description ?? '');
                $row.find('.js-stage').val(values.stage ?? '');
            }

            function addRow(key, values) {
                const $card = $('[data-section="' + key + '"]');

                const $row = $(
                    '<tr>' + builders[key]()
                    + '<td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger js-remove"'
                    + ' aria-label="حذف الصف"><i class="feather icon-trash-2"></i></button></td>'
                    + '</tr>'
                );

                $card.find('[data-rows]').append($row);

                if (values) fillRow($row, values);

                if ($.fn.select2) {
                    // القائمةُ المنسدلة تُعلَّق بالبطاقة: داخل `.table-responsive` تُقصّ
                    $row.find('.select2').select2({
                        placeholder: 'اختر…',
                        dir: 'rtl',
                        width: '100%',
                        // القائمة تُرسَم في <body>: داخل البطاقة تقصّها حدودُها (overflow) فلا تظهر خياراتُها
                dropdownParent: $(document.body),
                dropdownAutoWidth: true,
                    });
                }

                refresh();

                return $row;
            }

            $('[data-add]').on('click', function () {
                addRow($(this).closest('[data-section]').data('section'));
            });

            $('[data-rows]').on('click', '.js-remove', function () {
                $(this).closest('tr').remove();
                refresh();
            });

            /* اختيار منتج يملأ وحداته وسعره؛ واختيار محطة يملأ تكلفة ساعتها */
            $('[data-rows]').on('change', '.js-product', function () {
                loadUnits($(this).closest('tr'));
                refresh();
            });

            $('[data-rows]').on('change', '.js-unit', function () {
                applyUnit($(this).closest('tr'), true);
                refresh();
            });

            $('[data-rows]').on('change', '.js-workstation', function () {
                const option = this.options[this.selectedIndex];
                $(this).closest('tr').find('.js-cost').val(fixed(option ? option.dataset.cost : 0));
                refresh();
            });

            $('[data-rows]').on('input', '.js-price, .js-qty, .js-amount, .js-time, .js-cost', refresh);

            if ($.fn.select2) {
                $('#bomForm > .card:first .select2').select2({ placeholder: 'اختر…', dir: 'rtl', width: '100%' });
            }

            $('#product_id').on('change', function () {
                const option = this.options[this.selectedIndex];
                const unit = option ? option.dataset.unit : '';

                $('#product-unit-display').text(unit ? 'وحدة المنتج: ' + unit : '');
            }).trigger('change');

            $('#bomForm').on('submit', function (e) {
                const errors = [];
                const finished = $('#product_id').val();

                if (! $('[data-section="raw"] [data-rows] tr').length) errors.push('أضف مادة خام واحدة على الأقل');

                // موضعُ الخطأ بالقسم ورقم الصفّ — لا «بيانات ناقصة» مبهمة
                $('[data-section]').each(function () {
                    const key = $(this).data('section');
                    const title = $(this).find('.stk-table-head__title').clone().children().remove().end().text().trim();

                    $(this).find('[data-rows] tr').each(function (index) {
                        const where = title + ' — الصف ' + (index + 1) + ': ';

                        $(this).find('select[required], input[required]').each(function () {
                            if (! this.value) {
                                const column = $(this).closest('td').index();
                                const header = $(this).closest('table').find('thead th').eq(column).text().trim();
                                errors.push(where + (header || 'حقل') + ' مطلوب');
                            }
                        });

                        if (key === 'raw' && finished && $(this).find('.js-product').val() === finished) {
                            errors.push(where + 'المنتج المصنَّع نفسه لا يكون مادةً خاماً في قائمته');
                        }
                    });
                });

                if (errors.length) {
                    e.preventDefault();
                    Swal.fire({ icon: 'error', title: 'بيانات ناقصة', html: errors.map(esc).join('<br>') });

                    return;
                }

                $('#submitBtn').prop('disabled', true);
            });

            /* الصفوف المحفوظة أو المُعادة بعد خطأ — وإلّا صفّ مادة خام واحد يبدأ به المستخدم */
            Object.keys(initial).forEach(key => (initial[key] || []).forEach(values => addRow(key, values)));

            @unless ($bom || $returned)
                if (! $('[data-section="raw"] [data-rows] tr').length) addRow('raw');
            @endunless

            refresh();
        });
    </script>
@endsection
