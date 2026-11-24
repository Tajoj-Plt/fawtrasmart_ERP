{{--
    نموذج أمر التصنيع — تُشغّله شاشتا الإنشاء والتعديل معاً.

    كانت الشاشتان نحو 4000 سطر متطابقة تقريباً: أربع كتل جافاسكربت تُعيد كتابة
    «أضف صفّاً» و«احذف صفّاً» و«اجمع الإجمالي»، ومعها ثمانية قوالب صفوف مكرّرة
    مرّتين (مرّة لكلّ شاشة). صارت بيانات وصفيّة واحدة يقرؤها محرّك واحد.

    وأُصلح هنا:
    • تكلفة محطة العمل كانت تُجلب من `/api/workstations/{id}` — وهو طريق غير
      موجود في المشروع، فيبقى الحقل صفراً ويُحفظ الأمر بتكلفة تصنيع صفريّة.
      صارت التكلفة مضمَّنة في الخيار نفسه.
    • كل إضافة صفٍّ كانت تُطلق نافذة Swal، فأربعة صفوف = أربع نوافذ متتالية.
    • Select2 وSweetAlert كانا يُحمَّلان من CDN فوق نسختَي المشروع.

    المتغيّرات: $action, $submitLabel, $order (اختياري في الإنشاء)،
    وقوائم المراجع القادمة من المتحكّم.
--}}

@php
    $order = $order ?? null;

    /* ما يظهر وما يُلزَم — «الإعدادات العامة للتصنيع» */
    $ofs = \Modules\Manufacturing\Support\OrderFormSettings::class;
    $stageMode = $ofs::field('stage');

    $costTypes = [1 => 'مبلغ ثابت', 2 => 'بناءً على الكمية', 3 => 'معادلة'];

    $sections = [
        [
            'key' => 'raw',
            'title' => 'المواد الخام',
            'icon' => 'package',
            'add' => 'إضافة مادة',
            'sign' => '+',
            'headers' => ['المادة الخام', 'الوحدة', 'تكلفة الوحدة', 'الكمية', 'المرحلة', 'الإجمالي'],
            'widths' => [28, 15, 13, 12, 18, 14],
        ],
        [
            'key' => 'expenses',
            'title' => 'المصروفات',
            'icon' => 'dollar-sign',
            'add' => 'إضافة مصروف',
            'sign' => '+',
            'headers' => ['حساب المصروف', 'نوع التكلفة', 'المبلغ', 'الوصف', 'المرحلة', 'الإجمالي'],
            'widths' => [24, 15, 12, 19, 16, 14],
        ],
        [
            'key' => 'manufacturing',
            'title' => 'عمليات التصنيع',
            'icon' => 'settings',
            'add' => 'إضافة عملية',
            'sign' => '+',
            'headers' => ['محطة العمل', 'نوع التكلفة', 'الساعات', 'تكلفة الساعة', 'الوصف', 'المرحلة', 'الإجمالي'],
            'widths' => [20, 13, 10, 12, 17, 15, 13],
        ],
        [
            'key' => 'indirect',
            'title' => 'المصروفات غير المباشرة',
            'icon' => 'pie-chart',
            'add' => 'إضافة مصروف غير مباشر',
            'sign' => '+',
            'headers' => ['التكلفة غير المباشرة', 'الحساب', 'طريقة الحساب', 'المرحلة', 'نصيب الأمر'],
            'widths' => [32, 16, 22, 16, 14],
        ],
        [
            'key' => 'endlife',
            'title' => 'المواد الهالكة',
            'icon' => 'trash-2',
            'add' => 'إضافة مادة',
            'sign' => '−',
            'headers' => ['المادة', 'الوحدة', 'السعر', 'الكمية', 'المرحلة', 'الإجمالي'],
            'widths' => [28, 15, 13, 12, 18, 14],
        ],
    ];

    /* الصفوف المحفوظة — فارغة في الإنشاء، ومصفوفةٌ جاهزة في التعديل */
    $initialRows = [
        'raw' => collect($rawMaterials ?? [])->map(fn ($item) => [
            'product' => $item->product_id,
            'unit' => $item->unit_id,
            'price' => (float) $item->unit_price,
            'qty' => (float) $item->quantity,
            'stage' => $item->production_stage_id,
            'factor' => (float) ($item->conversion_factor ?? 1),
        ])->values(),

        'expenses' => collect($expenseItems ?? [])->filter(fn ($item) => empty($item->indirect_cost_id))->map(fn ($item) => [
            'account' => $item->account_id,
            'costType' => $item->cost_type,
            'amount' => (float) $item->price,
            'description' => $item->description,
            'stage' => $item->production_stage_id,
        ])->values(),

        'manufacturing' => collect($manufacturingItems ?? [])->map(fn ($item) => [
            'workstation' => $item->workstation_id,
            'costType' => $item->cost_type,
            'time' => (float) $item->operating_time,
            'cost' => (float) $item->total_cost,
            'description' => $item->description,
            'stage' => $item->production_stage_id,
        ])->values(),

        'indirect' => collect($expenseItems ?? [])->filter(fn ($item) => ! empty($item->indirect_cost_id))->map(fn ($item) => [
            'indirect' => $item->indirect_cost_id,
            'stage' => $item->production_stage_id,
        ])->values(),

        'endlife' => collect($endLifeItems ?? [])->map(fn ($item) => [
            'product' => $item->product_id,
            'unit' => $item->unit_id,
            'price' => (float) $item->unit_price,
            'qty' => (float) $item->quantity,
            'stage' => $item->production_stage_id,
            'factor' => (float) ($item->conversion_factor ?? 1),
        ])->values(),
    ];

    /*
        بعد خطأ في الحفظ تُعاد الصفوف كما أدخلها المستخدم.

        حقولُ الرأس كانت تعود بـ`old()`، أمّا الجداول الأربعة فتُبنى
        بالسكربت من `$initialRows` وحدها — فكان أيُّ خطأ تحقّقٍ يُفرغ المواد
        والمصروفات والعمليات والهوالك كلَّها ويُلزم المستخدم بإدخالها من
        جديد. الآن: إن حمل الطلبُ المردود صفوفاً فهي التي تُعرض.
    */
    if (old('_token')) {
        $oldRows = function (string $key, callable $map) {
            return collect((array) old($key, []))->keys()->map($map)->values();
        };

        $initialRows = [
            'raw' => $oldRows('raw_product_id', fn ($i) => [
                'product' => old("raw_product_id.$i"),
                'unit' => old("raw_unit_id.$i"),
                'price' => (float) old("raw_unit_price.$i"),
                'qty' => old("raw_quantity.$i"),
                'stage' => old("raw_production_stage_id.$i"),
                'factor' => (float) (old("raw_conversion_factor.$i") ?: 1),
            ]),
            'expenses' => $oldRows('expenses_account_id', fn ($i) => [
                'account' => old("expenses_account_id.$i"),
                'costType' => old("expenses_cost_type.$i"),
                'amount' => (float) old("expenses_price.$i"),
                'description' => old("expenses_description.$i"),
                'stage' => old("expenses_production_stage_id.$i"),
            ]),
            'manufacturing' => $oldRows('workstation_id', fn ($i) => [
                'workstation' => old("workstation_id.$i"),
                'costType' => old("manu_cost_type.$i"),
                'time' => old("operating_time.$i"),
                'cost' => (float) old("manu_total_cost.$i"),
                'description' => old("manu_description.$i"),
                'stage' => old("manu_production_stage_id.$i"),
            ]),
            'indirect' => $oldRows('indirect_cost_id', fn ($i) => [
                'indirect' => old("indirect_cost_id.$i"),
                'stage' => old("indirect_stage_id.$i"),
            ]),
            'endlife' => $oldRows('end_life_product_id', fn ($i) => [
                'product' => old("end_life_product_id.$i"),
                'unit' => old("end_life_unit_id.$i"),
                'price' => (float) old("end_life_unit_price.$i"),
                'qty' => old("end_life_quantity.$i"),
                'stage' => old("end_life_production_stage_id.$i"),
                'factor' => (float) (old("end_life_conversion_factor.$i") ?: 1),
            ]),
        ];
    }

    $reference = [
        'products' => collect($raw_materials)->map(fn ($product) => [
            'id' => $product->id,
            'name' => $product->name,
        ])->values(),

        /* حساباتُ بنود المصروف — مصروفاتُ التصنيع المباشرة وحدها، لا الدليل كلُّه */
        'accounts' => (function () use ($expenseAccounts, $accounts, $production_materials) {
            $list = collect($expenseAccounts ?? $accounts);

            /* حسابُ مصروفٍ محفوظٌ على قائمة مواد يُضاف إلى القائمة — وإلّا
               نُسخ بندُه إلى الأمر بحسابٍ فارغ */
            $fromBoms = collect($production_materials ?? [])
                ->flatMap(fn ($bom) => $bom->ProductionMaterialsItem->pluck('expenses_account_id'))
                ->filter()->unique()->diff($list->pluck('id'));

            if ($fromBoms->isNotEmpty()) {
                $list = $list->concat(\App\Models\Account::whereIn('id', $fromBoms->all())->get(['id', 'name', 'code']));
            }

            return $list->map(fn ($account) => [
                'id' => $account->id,
                'name' => ($account->code ? $account->code . ' — ' : '') . $account->name,
            ])->values();
        })(),

        'stages' => collect($stages)->map(fn ($stage) => [
            'id' => $stage->id,
            'name' => $stage->stage_name,
        ])->values(),

        'workstations' => collect($workstations)->map(fn ($workstation) => [
            'id' => $workstation->id,
            'name' => $workstation->name,
            'cost' => (float) $workstation->total_cost,
        ])->values(),

        'costTypes' => collect($costTypes)->map(fn ($label, $id) => ['id' => $id, 'name' => $label])->values(),

        /* التكاليف غير المباشرة المتاحة — كلٌّ بنصيب وحدته وطريقة حسابه */
        'indirectCosts' => collect($indirectCosts ?? [])->map(fn ($cost) => [
            'id' => $cost->id,
            'name' => ($cost->code ?: '#' . $cost->id) . ' — ' . (optional($cost->account)->name ?? 'حساب')
                . ' (' . \Illuminate\Support\Carbon::parse($cost->from_date)->format('Y-m-d')
                . ' ← ' . \Illuminate\Support\Carbon::parse($cost->to_date)->format('Y-m-d') . ')',
            'account' => optional($cost->account)->name ?? '—',
            'rate' => $cost->effective_rate === null ? null : (float) $cost->effective_rate,
            'total' => (float) $cost->total,
            'basis' => (int) $cost->based_on,
        ])->values(),
    ];

    /*
        بنودُ كل قائمة مواد — تُنسخ إلى الأمر عند اختيار القائمة.

        كان اختيارُ «قائمة المواد» لا يفعل شيئاً: يُحفظ معرّفُها مع الأمر
        وتُدخَل المواد يدوياً صفّاً صفّاً. الآن تُملأ الجداول من القائمة
        مضروبةً في نسبة (كمية الأمر ÷ كمية القائمة).
    */
    $bomLines = collect($production_materials ?? [])->mapWithKeys(fn ($bom) => [
        $bom->id => [
            'quantity' => (float) ($bom->quantity ?: 1),
            'name' => $bom->name,
            'product' => $bom->product_id,
            'path' => $bom->production_path_id,
            'account' => $bom->account_id,
            'raw' => $bom->ProductionMaterialsItem->filter(fn ($i) => $i->raw_product_id)->map(fn ($i) => [
                'product' => $i->raw_product_id,
                'unit' => $i->raw_unit_id ?? '',
                'price' => (float) $i->raw_unit_price,
                'qty' => (float) $i->raw_quantity,
                'stage' => $i->raw_production_stage_id,
                'factor' => (float) ($i->raw_conversion_factor ?: 1),
            ])->values(),
            'expenses' => $bom->ProductionMaterialsItem->filter(fn ($i) => $i->expenses_account_id)->map(fn ($i) => [
                'account' => $i->expenses_account_id,
                'costType' => $i->expenses_cost_type,
                'amount' => (float) $i->expenses_price,
                'description' => $i->expenses_description,
                'stage' => $i->expenses_production_stage_id,
            ])->values(),
            'manufacturing' => $bom->ProductionMaterialsItem->filter(fn ($i) => $i->workstation_id)->map(fn ($i) => [
                'workstation' => $i->workstation_id,
                'costType' => $i->manu_cost_type,
                'time' => (float) $i->operating_time,
                'cost' => (float) $i->manu_total_cost,
                'description' => $i->manu_description,
                'stage' => $i->manu_production_stage_id,
            ])->values(),
            'endlife' => $bom->ProductionMaterialsItem->filter(fn ($i) => $i->end_life_product_id)->map(fn ($i) => [
                'product' => $i->end_life_product_id,
                'unit' => '',
                'price' => (float) $i->end_life_unit_price,
                'qty' => (float) $i->end_life_quantity,
                'stage' => $i->end_life_production_stage_id,
                'factor' => 1,
            ])->values(),
        ],
    ]);

    /* وحدات كل منتج ومعاملات تحويلها — تُقرأ لملء قائمة الوحدة عند اختيار المنتج */
    $unitsByProduct = collect($productsWithUnits ?? [])->mapWithKeys(fn ($product) => [
        $product['id'] => [
            'purchase' => (float) ($product['purchase_price'] ?? 0),
            'sale' => (float) ($product['sale_price'] ?? 0),
            'units' => collect($product['units'] ?? [])->map(fn ($unit) => [
                'id' => $unit['id'] ?? ('category_' . ($unit['category_unit_id'] ?? '')),
                'name' => $unit['name'],
                'factor' => (float) ($unit['conversion_factor'] ?? 1),
                'base' => (bool) ($unit['is_base_unit'] ?? false),
                'default' => (bool) ($unit['is_default'] ?? false),
                'purchase' => (float) ($unit['purchase_price'] ?? 0),
                'sale' => (float) ($unit['sale_price'] ?? 0),
            ])->values(),
        ],
    ]);

    /* القسمُ المخفيّ من الإعدادات يسقط — إلّا إن حمل الأمرُ بنوداً فيه */
    $sections = array_values(array_filter($sections, function ($section) use ($ofs, $initialRows) {
        $key = $section['key'];

        return $key === 'raw'
            || $ofs::section($key)
            || collect($initialRows[$key] ?? [])->isNotEmpty();
    }));
@endphp

{{-- جداول البنود: أعمدةٌ بعرضٍ ثابت وخلايا متراصّة — كانت القوائم تتمدّد
     بطول نصّها فتدفع الأعمدة وتُخرج عمود الإجمالي عن الشاشة --}}
<style>
    #manufacturingOrderForm [data-section] table { table-layout: fixed; min-width: 900px; }
    #manufacturingOrderForm [data-section] th { white-space: nowrap; font-size: .8rem; }
    #manufacturingOrderForm [data-section] td { vertical-align: top; padding: .5rem .4rem; }
    #manufacturingOrderForm [data-section] td .form-control { padding-inline: .5rem; }
    #manufacturingOrderForm [data-section] td .select2-container { width: 100% !important; max-width: 100%; }
    #manufacturingOrderForm [data-section] td textarea { resize: vertical; min-height: 38px; }
    #manufacturingOrderForm [data-section] .js-total { background: var(--app-surface-2, #f8f8fb); font-weight: 600; }
    #manufacturingOrderForm.mfg-stage-hidden .js-stage-cell { display: none; }
</style>

<form id="manufacturingOrderForm" class="{{ $stageMode === 'hidden' ? 'mfg-stage-hidden' : '' }}" action="{{ $action }}" method="POST" enctype="multipart/form-data">
    @csrf

    <div class="card">
        <div class="card-header">
            <h4 class="card-title"><i class="feather icon-clipboard"></i> بيانات الأمر</h4>
        </div>

        <div class="card-body">
            {{-- «تابع لـ» — تُختم مع الأمر عند إنشائه ولا تُنقل بعده --}}
            <div class="row">
                @include('manufacturing::partials.affiliation-field', [
                    'value' => $order->branch_id ?? null,
                    'locked' => (bool) $order,
                ])
            </div>

            <div class="row">
                <div class="col-md-3 col-sm-6">
                    <div class="form-group">
                        <label for="name" class="stk-req">اسم الأمر</label>
                        <input type="text" id="name" name="name" required class="form-control"
                               value="{{ old('name', $order->name ?? '') }}">
                    </div>
                </div>

                <div class="col-md-3 col-sm-6">
                    <div class="form-group">
                        <label for="code">الكود</label>
                        {{--
                            للعرض فقط — لا يُرسَل ولا يُحفَظ.

                            كان حقلاً قابلاً للتحرير باسم `code`، فيُحفظ ما يكتبه
                            المستخدم وتُهمَل إعدادات الترقيم. الرقم يُصدَر الآن
                            في الخادم بـ`issueDocumentNumber()` داخل معاملة بقفل،
                            فلا تصادم ولا كسر في التسلسل.
                        --}}
                        <input type="text" id="code" disabled
                               class="form-control text-center stk-num"
                               value="{{ $order->code ?? ($serial_number ?? '') }}">
                        @unless ($order)
                            <span class="stk-help">يُصدَر تلقائياً من إعدادات الترقيم عند الحفظ</span>
                        @endunless
                    </div>
                </div>

                <div class="col-md-3 col-sm-6">
                    <div class="form-group">
                        <label for="from_date" class="stk-req">من تاريخ</label>
                        <input type="date" id="from_date" name="from_date" required class="form-control"
                               value="{{ old('from_date', $order->from_date ?? '') }}">
                    </div>
                </div>

                <div class="col-md-3 col-sm-6">
                    <div class="form-group">
                        <label for="to_date" class="stk-req">إلى تاريخ</label>
                        <input type="date" id="to_date" name="to_date" required class="form-control"
                               value="{{ old('to_date', $order->to_date ?? '') }}">
                    </div>
                </div>

                <div class="col-md-4 col-sm-6">
                    <div class="form-group">
                        <label for="product_id" class="stk-req">المنتج المصنَّع</label>
                        <select id="product_id" name="product_id" required class="form-control select2">
                            <option value="">اختر المنتج…</option>
                            @foreach ($products as $product)
                                <option value="{{ $product->id }}"
                                        {{ old('product_id', $order->product_id ?? '') == $product->id ? 'selected' : '' }}>
                                    {{ $product->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="col-md-2 col-sm-6">
                    <div class="form-group">
                        <label for="quantity" class="stk-req">الكمية</label>
                        <input type="number" id="quantity" name="quantity" required min="0.0001" step="any"
                               class="form-control stk-num text-center"
                               value="{{ old('quantity', $order->quantity ?? 1) }}">
                    </div>
                </div>

@if ($ofs::visible('production_material_id'))
                                <div class="col-md-3 col-sm-6">
                    <div class="form-group">
                        <label for="production_material_id" class="{{ $ofs::required('production_material_id') ? 'stk-req' : '' }}">قائمة المواد</label>
                        {{-- اختيارُها يملأ الجداول من بنودها مضروبةً في كمية الأمر --}}
                        <select id="production_material_id" name="production_material_id" @required($ofs::required('production_material_id')) class="form-control select2">
                            <option value="">اختر القائمة…</option>
                            @foreach ($production_materials as $material)
                                <option value="{{ $material->id }}" data-product="{{ $material->product_id }}"
                                        {{ old('production_material_id', $order->production_material_id ?? '') == $material->id ? 'selected' : '' }}>
                                    {{ $material->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                @endif

                @if ($ofs::visible('production_path_id'))
                                <div class="col-md-3 col-sm-6">
                    <div class="form-group">
                        <label for="production_path_id" class="{{ $ofs::required('production_path_id') ? 'stk-req' : '' }}">مسار الإنتاج</label>
                        <select id="production_path_id" name="production_path_id" @required($ofs::required('production_path_id')) class="form-control select2">
                            <option value="">اختر المسار…</option>
                            @foreach ($paths as $path)
                                <option value="{{ $path->id }}"
                                        {{ old('production_path_id', $order->production_path_id ?? '') == $path->id ? 'selected' : '' }}>
                                    {{ $path->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                @endif

                @if ($ofs::visible('employee_id'))
                                <div class="col-md-4 col-sm-6">
                    <div class="form-group">
                        <label for="employee_id" class="{{ $ofs::required('employee_id') ? 'stk-req' : '' }}">الموظف المسؤول</label>
                        <select id="employee_id" name="employee_id" @required($ofs::required('employee_id')) class="form-control select2">
                            <option value="">اختر الموظف…</option>
                            @foreach ($employees as $employee)
                                <option value="{{ $employee->id }}"
                                        {{ old('employee_id', $order->employee_id ?? '') == $employee->id ? 'selected' : '' }}>
                                    {{ $employee->full_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                @endif

                @if ($ofs::visible('storehouse_id'))
                                {{--
                    مستودع المواد — اختياري.

                    الحجزُ يُقاس لكل مستودعٍ على حدة، فلا يصحّ حجزُ كميةٍ
                    «في مكانٍ ما». فمن اختار مستودعاً هنا حُجزت مواده فيه
                    فور الحفظ، ومن تركه فارغاً بقي السلوك كما كان: لا حجز،
                    ويُختار المستودع عند استلام المواد.
                --}}
                <div class="col-md-4 col-sm-6">
                    <div class="form-group">
                        <label for="storehouse_id" class="{{ $ofs::required('storehouse_id') ? 'stk-req' : '' }}">مستودع المواد</label>
                        <select id="storehouse_id" name="storehouse_id" @required($ofs::required('storehouse_id')) class="form-control select2">
                            <option value="">يُحدَّد عند الاستلام — بلا حجز</option>
                            @foreach ($storehouses ?? [] as $storehouse)
                                <option value="{{ $storehouse->id }}"
                                        {{ old('storehouse_id', $order->storehouse_id ?? '') == $storehouse->id ? 'selected' : '' }}>
                                    {{ $storehouse->name }}
                                </option>
                            @endforeach
                        </select>
                        <span class="stk-help">اختيارُه يحجز المواد فوراً فلا يعتمد أمرٌ آخر عليها</span>
                    </div>
                </div>
                @endif

                @if ($ofs::visible('client_id'))
                                <div class="col-md-4 col-sm-6">
                    <div class="form-group">
                        <label for="client_id" class="{{ $ofs::required('client_id') ? 'stk-req' : '' }}">العميل</label>
                        <select id="client_id" name="client_id" @required($ofs::required('client_id')) class="form-control select2">
                            <option value="">بلا عميل</option>
                            @foreach ($clients as $client)
                                <option value="{{ $client->id }}"
                                        {{ old('client_id', $order->client_id ?? '') == $client->id ? 'selected' : '' }}>
                                    {{ $client->trade_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                @endif

                @if ($ofs::visible('account_id'))
                                <div class="col-md-4 col-sm-6">
                    <div class="form-group mb-0">
                        <label for="account_id" class="{{ $ofs::required('account_id') ? 'stk-req' : '' }}">الحساب</label>
                        <select id="account_id" name="account_id" @required($ofs::required('account_id')) class="form-control select2">
                            <option value="">بلا حساب</option>
                            @foreach ($accounts as $account)
                                <option value="{{ $account->id }}"
                                        {{ old('account_id', $order->account_id ?? '') == $account->id ? 'selected' : '' }}>
                                    {{ $account->dropdown_label ?? $account->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>

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
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            @foreach ($section['headers'] as $header)
                                <th class="{{ $loop->first ? '' : 'text-center' }} {{ $header === 'المرحلة' ? 'js-stage-cell' : '' }}"
                                    style="width:{{ $section['widths'][$loop->index] ?? 14 }}%">{{ $header }}</th>
                            @endforeach
                            <th class="text-center" style="width:52px"></th>
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
        </div>
    @endforeach

    <div class="card">
        <div class="card-header">
            <h4 class="card-title"><i class="feather icon-bar-chart-2"></i> إجمالي التكلفة</h4>
            <span class="stk-count">{{ App\Support\CurrencySettings::code() }}</span>
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

            <input type="hidden" name="last_total_cost" id="last_total_cost" value="0.00">
        </div>

        <div class="stk-form-foot">
            <span class="stk-help me-auto">الحقول المعلَّمة بـ<span class="stk-req"></span> إلزامية</span>
            <a href="{{ route('manufacturing.orders.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-x"></i> إلغاء
            </a>
            <button type="submit" class="btn btn-primary" id="submitBtn">
                <i class="feather icon-check"></i> {{ $submitLabel }}
            </button>
        </div>
    </div>
</form>

@push('scripts')
<script src="{{ asset('app-assets/vendors/js/extensions/sweetalert2.all.min.js') }}"></script>
<script>
$(function () {
    const data = @json($reference);
    const productUnits = @json($unitsByProduct);
    const initial = @json($initialRows);
    const bomLines = @json($bomLines);

    const esc = value => $('<div>').text(value == null ? '' : value).html();
    const fixed = value => Number(value || 0).toFixed(2);
    // سعرُ الوحدة بكسوره (حتى ستّ خانات) — موادُّ بالمليلتر والغرام تكلفتُها أجزاءُ هللة
    const cost = value => String(Number(Number(value || 0).toFixed(6)));
    const stageMode = @json($stageMode);

    function options(list, placeholder) {
        const head = placeholder ? '<option value="">' + placeholder + '</option>' : '';

        return head + list.map(item =>
            '<option value="' + item.id + '"'
            + (item.cost !== undefined ? ' data-cost="' + item.cost + '"' : '')
            + '>' + esc(item.name) + '</option>'
        ).join('');
    }

    const cell = inner => '<td>' + inner + '</td>';

    /* بناء صفٍّ لكل قسم — الفروق كلها هنا، والسلوك مشترك */
    const builders = {
        raw: () => ''
            + cell('<select name="raw_product_id[]" class="form-control select2 js-product" data-price="purchase" required>'
                + options(data.products, 'اختر المنتج…') + '</select>'
                + '<input type="hidden" name="raw_conversion_factor[]" class="js-factor" value="1">'
                + '<input type="hidden" name="raw_quantity_in_base_unit[]" class="js-base-qty" value="0">'
                + '<small class="stk-help js-unit-note" hidden></small>')
            + cell('<select name="raw_unit_id[]" class="form-control js-unit">'
                + '<option value="">الوحدة الأساسية</option></select>')
            + cell('<input type="number" step="any" name="raw_unit_price[]" class="form-control stk-num text-center js-price">')
            + cell('<input type="number" step="any" min="0.0001" value="1" required'
                + ' name="raw_quantity[]" class="form-control stk-num text-center js-qty">')
            + cell('<select name="raw_production_stage_id[]" class="form-control select2" required>'
                + options(data.stages, '') + '</select>')
            + cell('<input type="number" step="any" readonly name="raw_total[]"'
                + ' class="form-control stk-num text-center js-total">'),

        expenses: () => ''
            + cell('<select name="expenses_account_id[]" class="form-control select2" required>'
                + options(data.accounts, 'اختر الحساب…') + '</select>')
            + cell('<select name="expenses_cost_type[]" class="form-control js-cost-type">'
                + options(data.costTypes, '') + '</select>')
            + cell('<input type="number" step="any" required name="expenses_price[]"'
                + ' class="form-control stk-num text-center js-amount">')
            + cell('<textarea name="expenses_description[]" class="form-control js-description" rows="1"></textarea>')
            + cell('<select name="expenses_production_stage_id[]" class="form-control select2" required>'
                + options(data.stages, '') + '</select>')
            + cell('<input type="number" step="any" readonly name="expenses_total[]"'
                + ' class="form-control stk-num text-center js-total">'),

        manufacturing: () => ''
            + cell('<select name="workstation_id[]" class="form-control select2 js-workstation" required>'
                + options(data.workstations, 'اختر المحطة…') + '</select>')
            + cell('<select name="manu_cost_type[]" class="form-control js-cost-type">'
                + options(data.costTypes, '') + '</select>')
            + cell('<input type="number" step="any" required name="operating_time[]"'
                + ' class="form-control stk-num text-center js-time">')
            + cell('<input type="number" step="any" name="manu_total_cost[]"'
                + ' class="form-control stk-num text-center js-cost">')
            + cell('<textarea name="manu_description[]" class="form-control js-description" rows="1"></textarea>')
            + cell('<select name="manu_production_stage_id[]" class="form-control select2" required>'
                + options(data.stages, '') + '</select>')
            + cell('<input type="number" step="any" readonly name="manu_total[]"'
                + ' class="form-control stk-num text-center js-total">'),

        /* المصروف غير المباشر: يُختار المستند، والمبلغُ يحسبه النظام —
           نصيب الوحدة × كمية الأمر (أو × تكلفته المباشرة) — ولا يُكتب */
        indirect: () => ''
            + cell('<select name="indirect_cost_id[]" class="form-control select2 js-indirect" required>'
                + '<option value="">اختر التكلفة غير المباشرة…</option>'
                + data.indirectCosts.map(item =>
                    '<option value="' + item.id + '" data-rate="' + (item.rate === null ? '' : item.rate) + '" data-total="' + item.total + '" data-basis="' + item.basis + '"'
                    + ' data-account="' + esc(item.account) + '">' + esc(item.name) + '</option>'
                ).join('') + '</select>')
            + cell('<span class="js-indirect-account stk-media__sub">—</span>')
            + cell('<span class="js-indirect-basis stk-media__sub">—</span>')
            + cell('<select name="indirect_stage_id[]" class="form-control select2">'
                + options(data.stages, '') + '</select>')
            + cell('<input type="number" step="any" readonly'
                + ' class="form-control stk-num text-center js-total">'),

        endlife: () => ''
            + cell('<select name="end_life_product_id[]" class="form-control select2 js-product" data-price="purchase" required>'
                + options(data.products, 'اختر المنتج…') + '</select>'
                + '<input type="hidden" name="end_life_conversion_factor[]" class="js-factor" value="1">'
                + '<input type="hidden" name="end_life_quantity_in_base_unit[]" class="js-base-qty" value="0">'
                + '<small class="stk-help js-unit-note" hidden></small>')
            + cell('<select name="end_life_unit_id[]" class="form-control js-unit">'
                + '<option value="">الوحدة الأساسية</option></select>')
            + cell('<input type="number" step="any" name="end_life_unit_price[]" class="form-control stk-num text-center js-price">')
            + cell('<input type="number" step="any" min="0.0001" value="1" required'
                + ' name="end_life_quantity[]" class="form-control stk-num text-center js-qty">')
            + cell('<select name="end_life_production_stage_id[]" class="form-control select2" required>'
                + options(data.stages, '') + '</select>')
            + cell('<input type="number" step="any" readonly name="end_life_total[]"'
                + ' class="form-control stk-num text-center js-total">'),
    };

    // الهوالك تُطرح من التكلفة لا تُضاف إليها
    const subtracted = { endlife: true };

    function rowTotal($row, direct) {
        if ($row.find('.js-indirect').length) {
            const option = $row.find('.js-indirect option:selected')[0];
            const rate = option ? parseFloat(option.dataset.rate) || 0 : 0;
            const costTotal = option ? parseFloat(option.dataset.total) || 0 : 0;
            // لا نصيبَ وحدةٍ: لا أوامرَ في فترة التكلفة، فيتحمّل هذا الأمرُ إجماليَّها
            const whole = option && option.value && option.dataset.rate === '';
            const byCost = option && Number(option.dataset.basis) === 2;
            const quantity = parseFloat($('#quantity').val()) || 0;

            $row.find('.js-indirect-account').text(option && option.value ? option.dataset.account : '—');
            $row.find('.js-indirect-basis').text(! option || ! option.value
                ? '—'
                : (whole
                    ? 'إجمالي التكلفة كاملاً (لا أوامر أخرى في فترتها)'
                    : (byCost
                        ? Number(rate.toFixed(6)) + ' × تكلفة الأمر ' + fixed(direct)
                        : Number(rate.toFixed(6)) + ' × كمية الأمر ' + quantity)));

            if (whole) return Math.round(costTotal * 100) / 100;

            return Math.round(Math.min(costTotal, rate * (byCost ? direct : quantity)) * 100) / 100;
        }

        if ($row.find('.js-time').length) {
            return (parseFloat($row.find('.js-cost').val()) || 0) * (parseFloat($row.find('.js-time').val()) || 0);
        }

        if ($row.find('.js-amount').length) {
            return parseFloat($row.find('.js-amount').val()) || 0;
        }

        return (parseFloat($row.find('.js-price').val()) || 0) * (parseFloat($row.find('.js-qty').val()) || 0);
    }

    function refresh() {
        let grand = 0;
        // التكلفة المباشرة (مواد + مصروفات + تشغيل) — أساسُ المصروف غير المباشر «بحسب التكلفة»
        let direct = 0;

        $('[data-section]').each(function () {
            const $card = $(this);
            const key = $card.data('section');
            let subtotal = 0;

            $card.find('[data-rows] tr').each(function () {
                const $row = $(this);
                const total = rowTotal($row, direct);

                $row.find('.js-total').val(fixed(total));

                /* الكمية بالوحدة الأساسية تُحفظ مع الصفّ */
                if ($row.find('.js-base-qty').length) {
                    const qty = parseFloat($row.find('.js-qty').val()) || 0;
                    const factor = parseFloat($row.find('.js-factor').val()) || 1;
                    const base = qty * factor;

                    $row.find('.js-base-qty').val(base);
                    $row.find('.js-unit-note')
                        .prop('hidden', factor === 1)
                        .text('= ' + fixed(base) + ' بالوحدة الأساسية');
                }

                subtotal += total;
            });

            const count = $card.find('[data-rows] tr').length;

            $card.find('[data-count]').text(count);
            $card.find('[data-subtotal]').text(fixed(subtotal));
            $card.find('[data-empty]')[0].hidden = count > 0;

            $('[data-total-for="' + key + '"]').text(fixed(subtotal));
            grand += subtracted[key] ? -subtotal : subtotal;
            if (key === 'raw' || key === 'expenses' || key === 'manufacturing') direct += subtotal;
        });

        $('#grand-total').text(fixed(grand));
        $('#last_total_cost').val(fixed(grand));
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

        /* المرحلة بحسب الإعدادات: إلزامية كما هي، اختيارية بخيارٍ فارغ، مخفيّة بلا قيمة */
        const $stages = $row.find('select[name$="production_stage_id[]"], select[name="indirect_stage_id[]"]');
        $stages.closest('td').addClass('js-stage-cell');

        if (stageMode !== 'required') {
            $stages.prop('required', false).prepend('<option value="">بلا مرحلة</option>').val('');
        }

        if (values) fillRow($row, values);

        if ($.fn.select2) {
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

    /* ملء قائمة الوحدات من وحدات المنتج المختار */
    function loadUnits($row, selectedUnit, savedFactor) {
        const $product = $row.find('.js-product');
        const $unit = $row.find('.js-unit');

        if (! $product.length || ! $unit.length) return;

        const product = productUnits[$product.val()];
        const priceKey = $product.data('price') === 'sale' ? 'sale' : 'purchase';

        $unit.html('<option value="">الوحدة الأساسية</option>');
        $row.find('.js-factor').val(1);

        // القائمة لا تُعطَّل: الحقلُ المعطَّل لا يُرسَل، فتنزاح وحداتُ الصفوف عن موادّها
        if (! product) return;

        if (selectedUnit === undefined) $row.find('.js-price').val(cost(product[priceKey]));

        const units = product.units || [];


        /* الوحدةُ المحفوظة تُعاد بمعرّفها، وإلّا بمعاملها — وحدةُ التصنيف لا معرّفَ لها */
        const byId = units.some(unit => selectedUnit && String(unit.id) === String(selectedUnit));
        let matched = false;

        units.forEach(unit => {
            const label = unit.name
                + (unit.base ? ' (أساسية)' : '')
                + (unit.factor !== 1 ? ' × ' + unit.factor : '');

            $('<option>')
                .val(unit.id)
                .text(label)
                .attr('data-factor', unit.factor)
                .attr('data-price', unit[priceKey])
                .prop('selected', (function () {
                    if (selectedUnit === undefined) return unit.default;
                    if (byId) return String(unit.id) === String(selectedUnit);
                    if (matched || ! savedFactor) return false;

                    matched = Math.abs(Number(unit.factor) - Number(savedFactor)) < 1e-9;

                    return matched;
                })())
                .appendTo($unit);
        });

        if ($unit.val()) applyUnit($row, selectedUnit === undefined);
    }

    function applyUnit($row, overwritePrice) {
        const option = $row.find('.js-unit option:selected')[0];
        const factor = option ? parseFloat(option.dataset.factor) || 1 : 1;
        const price = option ? parseFloat(option.dataset.price) || 0 : 0;

        $row.find('.js-factor').val(factor);
        if (overwritePrice && price > 0) $row.find('.js-price').val(cost(price));
    }

    function fillRow($row, values) {
        $row.find('.js-product').val(values.product ?? '');
        if ($row.find('.js-product').length) loadUnits($row, values.unit ?? '', values.factor);

        $row.find('.js-workstation').val(values.workstation ?? '');
        $row.find('.js-indirect').val(values.indirect ?? '');
        // حسابُ المصروف لم يكن يُعبَّأ إطلاقاً: يعود البندُ في التعديل وبعد
        // النسخ من القائمة بحسابٍ فارغ
        $row.find('select[name="expenses_account_id[]"]').val(values.account ?? '');
        $row.find('.js-cost-type').val(values.costType ?? 1);
        $row.find('.js-price').val(values.price !== undefined ? cost(values.price) : '');
        $row.find('.js-qty').val(values.qty ?? '');
        $row.find('.js-amount').val(values.amount !== undefined ? fixed(values.amount) : '');
        $row.find('.js-time').val(values.time ?? '');
        $row.find('.js-cost').val(values.cost !== undefined ? cost(values.cost) : '');
        $row.find('.js-description').val(values.description ?? '');
        $row.find('select[name$="production_stage_id[]"], select[name="indirect_stage_id[]"]').val(values.stage ?? '');
        if (values.factor) $row.find('.js-factor').val(values.factor);
    }

    $('[data-add]').on('click', function () {
        addRow($(this).closest('[data-section]').data('section'));
    });

    $('[data-rows]').on('click', '.js-remove', function () {
        $(this).closest('tr').remove();
        refresh();
    });

    $('[data-rows]').on('change', '.js-product', function () {
        loadUnits($(this).closest('tr'));
        refresh();
    });

    $('[data-rows]').on('change', '.js-unit', function () {
        applyUnit($(this).closest('tr'), true);
        refresh();
    });

    /* تكلفة المحطة كانت تُطلب من طريقٍ غير موجود — صارت في الخيار نفسه */
    $('[data-rows]').on('change', '.js-workstation', function () {
        const option = this.options[this.selectedIndex];
        $(this).closest('tr').find('.js-cost').val(cost(option.dataset.cost));
        refresh();
    });

    $('[data-rows]').on('input', '.js-price, .js-qty, .js-amount, .js-time, .js-cost', refresh);

    /* المصروف غير المباشر نفسُه لا يُختار مرّتين في الأمر الواحد */
    $('[data-rows]').on('change', '.js-indirect', function () {
        const value = this.value;
        const self = this;

        const duplicate = value && $('.js-indirect').filter(function () {
            return this !== self && this.value === value;
        }).length;

        if (duplicate) {
            $(this).val('').trigger('change.select2');
            Swal.fire({ icon: 'warning', title: 'مختارٌ من قبل', text: 'هذه التكلفة غير المباشرة مضافة إلى الأمر في صفٍّ آخر.' });
        }

        refresh();
    });

    // كميةُ الأمر أساسُ المصروف غير المباشر — تُعاد الحسبة عند تغييرها
    $('#quantity').on('input change', refresh);

    /*
        اختيارُ «قائمة المواد» يملأ الجداول من بنودها.

        الكمياتُ وأوقاتُ التشغيل تُضرب في (كمية الأمر ÷ كمية القائمة)،
        والمصروفُ «بناءً على الكمية» كذلك؛ والمبلغُ الثابت يبقى كما هو.
    */
    // كميةٌ كتبها المستخدم بيده تُحترم؛ وإلّا تأتي كميةُ القائمة
    let quantityTouched = @json((bool) ($order || old('_token')));
    let scaledQuantity = parseFloat($('#quantity').val()) || 0;

    $('#quantity').on('input', function () { quantityTouched = true; });

    /*
        تغييرُ كمية الأمر يُعيد تناسبَ البنود — حين يكون الأمرُ مبنياً على
        قائمة مواد: كمياتُ المواد والهالك وساعاتُ التشغيل والمصروفُ «بناءً
        على الكمية» تُضرب في (الكمية الجديدة ÷ السابقة)، والمبلغُ الثابت
        يبقى. ما عدّله المستخدم في صفٍّ يتناسب معه ولا يُمحى.
    */
    $('#quantity').on('change', function () {
        const next = parseFloat(this.value) || 0;

        if ($('#production_material_id').val() && scaledQuantity > 0 && next > 0 && next !== scaledQuantity) {
            const ratio = next / scaledQuantity;
            const scale = function () {
                if (this.value !== '') this.value = Number((parseFloat(this.value) * ratio).toFixed(4));
            };

            $('[data-section="raw"] .js-qty, [data-section="endlife"] .js-qty, [data-section="manufacturing"] .js-time').each(scale);

            $('[data-section="expenses"] [data-rows] tr').each(function () {
                if (Number($(this).find('.js-cost-type').val()) === 2) $(this).find('.js-amount').each(scale);
            });
        }

        if (next > 0) scaledQuantity = next;
        refresh();
    });

    function applyBom(bomId) {
        const bom = bomLines[bomId];
        if (! bom) return;

        /*
            القائمةُ تعبّئ الأمرَ كلَّه — لا بنودَه وحدها.

            المنتجُ والمسارُ من القائمة دائماً (هي وصفةُ منتجٍ بعينه على مسارٍ
            بعينه)، والكميةُ كميتُها ما لم يكتب المستخدم كميةً، والاسمُ
            والحسابُ يُملآن إن كانا فارغين.
        */
        if (bom.product) $('#product_id').val(bom.product).trigger('change');
        if (bom.path && $('#production_path_id').length) $('#production_path_id').val(bom.path).trigger('change');
        if (! quantityTouched) $('#quantity').val(bom.quantity || 1);
        if (! $('#name').val() && bom.name) $('#name').val(bom.name);
        if (bom.account && $('#account_id').length && ! $('#account_id').val()) {
            $('#account_id').val(bom.account).trigger('change');
        }

        const ratio = (parseFloat($('#quantity').val()) || 1) / (bom.quantity || 1);
        scaledQuantity = parseFloat($('#quantity').val()) || scaledQuantity;
        const scaled = value => Number((Number(value || 0) * ratio).toFixed(4));

        ['raw', 'expenses', 'manufacturing', 'endlife'].forEach(function (key) {
            if (! $('[data-section="' + key + '"]').length) return;

            $('[data-section="' + key + '"] [data-rows]').empty();

            (bom[key] || []).forEach(function (line) {
                const values = Object.assign({}, line);

                if (key === 'raw' || key === 'endlife') {
                    values.qty = scaled(line.qty);

                    /* السعرُ سعرُ القائمة كما حُفظ — تكلفةُ الأمر هي تكلفةُ قائمة
                       مواده (بنسبة الكمية). وبندٌ سعرُه صفر في القائمة يأخذ
                       متوسطَ تكلفة الصنف × معامل الوحدة. */
                    if (! (Number(line.price) > 0)) {
                        const known = productUnits[line.product];
                        const average = known ? Number(known.purchase) * (Number(line.factor) || 1) : 0;

                        if (average > 0) values.price = average;
                    }
                }
                if (key === 'manufacturing') values.time = scaled(line.time);
                if (key === 'expenses' && Number(line.costType) === 2) values.amount = scaled(line.amount);

                addRow(key, values);
            });
        });

        if (! $('[data-section="raw"] [data-rows] tr').length) addRow('raw');
        refresh();
    }

    /* قائمةُ المواد تخصّ المنتجَ المصنَّع — قوائمُ المنتجات الأخرى تُعطَّل */
    function filterBoms() {
        const product = $('#product_id').val();
        const $bom = $('#production_material_id');

        $bom.find('option').each(function () {
            const owner = this.getAttribute('data-product');
            this.disabled = !! (product && this.value && owner && String(owner) !== String(product));
        });

        if ($bom.find('option:selected').prop('disabled')) {
            $bom.val('').trigger('change.select2');
        }

        if ($.fn.select2 && $bom.data('select2')) {
            $bom.select2({ placeholder: 'اختر…', dir: 'rtl', width: '100%' });
        }
    }

    $('#product_id').on('change', filterBoms);

    $('#production_material_id').on('change', function () {
        const bomId = this.value;
        if (! bomId || ! bomLines[bomId]) return;

        const hasData = $('[data-section="raw"] .js-product').filter(function () { return this.value; }).length > 0;

        if (! hasData) {
            applyBom(bomId);
            return;
        }

        Swal.fire({
            icon: 'question',
            title: 'نسخ بنود القائمة؟',
            text: 'ستُستبدل المواد والمصروفات والعمليات والهوالك الحالية ببنود قائمة المواد المختارة.',
            showCancelButton: true,
            confirmButtonText: 'نعم، انسخ البنود',
            cancelButtonText: 'أبقِ ما أدخلته',
            reverseButtons: true,
        }).then(function (result) {
            if (result.isConfirmed) applyBom(bomId);
        });
    });

    if ($.fn.select2) {
        $('#manufacturingOrderForm > .card:first .select2').select2({ placeholder: 'اختر…', dir: 'rtl', width: '100%' });
    }

    $('#manufacturingOrderForm').on('submit', function (e) {
        const errors = [];

        const from = $('#from_date').val();
        const to = $('#to_date').val();

        if (from && to && new Date(from) > new Date(to)) errors.push('تاريخ البداية بعد تاريخ النهاية');
        if (! $('[data-section="raw"] [data-rows] tr').length) errors.push('أضف مادة خام واحدة على الأقل');

        // موضعُ الخطأ بالقسم ورقم الصفّ — لا «بيانات ناقصة» مبهمة
        $('[data-section]').each(function () {
            const title = $(this).find('.stk-table-head__title').clone().children().remove().end().text().trim();

            $(this).find('[data-rows] tr').each(function (index) {
                $(this).find('select[required], input[required]').each(function () {
                    if (! this.value) {
                        const column = $(this).closest('td').index();
                        const header = $(this).closest('table').find('thead th').eq(column).text().trim();
                        errors.push(title + ' — الصف ' + (index + 1) + ': ' + (header || 'حقل') + ' مطلوب');
                    }
                });
            });
        });

        if (errors.length) {
            e.preventDefault();
            Swal.fire({ icon: 'error', title: 'بيانات ناقصة', html: errors.join('<br>') });
            return;
        }

        $('#submitBtn').prop('disabled', true).html('<i class="feather icon-loader"></i> جارٍ الحفظ…');
    });

    filterBoms();

    /* الصفوف المحفوظة، وإلّا صفّ مادة خام واحد يبدأ به المستخدم */
    Object.keys(initial).forEach(function (key) {
        if (! $('[data-section="' + key + '"]').length) return;
        (initial[key] || []).forEach(values => addRow(key, values));
    });
    if (! $('[data-section="raw"] [data-rows] tr').length) addRow('raw');

    refresh();
});
</script>
@endpush
