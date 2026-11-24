@extends('purchases::master')

@section('title')
    {{ __('purchase_cycle.documents.purchase_quotation') }}
@stop

@section('css')
    @include('purchases::partials.style')
    @include('components.hint-tips')
@endsection

@section('content')
    @php
        use Modules\Purchases\Support\ConvertedLines;

        $rows = old('items', []);
        $code = $purchasePriceNumber;
        $products = $items ?? collect();
        $taxes = $taxs ?? collect();
        $defaults = [
            'date' => date('Y-m-d'),
            'valid_days' => 30,
            'supplier_id' => $selectedSupplier->id ?? null,
        ];

        // التحويل من طلب عرض أسعار: يُنقل المستند كاملاً لا الكميات وحدها.
        // طلب التسعير بلا أسعار، فسعر الوحدة يأتي من سعر شراء الصنف المسجَّل.
        if (! empty($quotation)) {
            $defaults['notes'] = $defaults['notes'] ?? $quotation->notes;
            $defaults['date'] = old('date', optional($quotation->order_date)->format('Y-m-d') ?: date('Y-m-d'));

            // ملء تلقائي فقط عند مورد واحد مرتبط بطلب العرض الأصلي؛ وإلا
            // يبقى الحقل فارغاً ليختار المستخدم يدوياً من قائمة الموردين كاملة
            if (! $defaults['supplier_id'] && $quotation->suppliers->count() === 1) {
                $defaults['supplier_id'] = $quotation->suppliers->first()->id;
            }

            if (! $rows) {
                $rows = $quotation->items->map(fn ($item) => [
                    'product_id' => $item->product_id,
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    // سعرُ **وحدة البند** لا سعر الحبّة: كرتونٌ سعره ١٩١٫٣٠ كان يصل ٠٫١٩
                    // فيخرج إجماليُّ البند أصغرَ بمعامل التحويل
                    'unit_price' => $item->unit_price ?: ($item->product
                        ? \Modules\Purchases\Support\ProductUnits::linePrice([
                            'product_unit_id' => $item->product_unit_id,
                            'conversion_factor' => $item->conversion_factor,
                        ], $item->product)
                        : 0),
                    // خصمُ البند نسبةً من سطره — العمودُ مخفيّ؛ انظر `OrderLine::discountFields()`
                    ...\Modules\Purchases\Support\OrderLine::discountFields($item),
                    'tax_1' => $item->tax_1 ?? 0,
                    'product_unit_id' => $item->product_unit_id,
                    'unit_name' => $item->unit_name,
                    'conversion_factor' => $item->conversion_factor ?: 1,
                ])->values()->all();
            }
        }

        // بنود التحويل مقفلة: الصنف ووحدته وكميته ووصفه جاءت من طلب عرض
        // الأسعار — هذه الشاشة تُسعِّر ما طُلب لا تعيد طلبه. ولذلك يبقى
        // السعر والخصم والضريبة مفتوحةً: هي وحدها ما يضيفه عرض السعر
        $rows = ConvertedLines::mark($rows, optional($quotation ?? null)->items ?? []);
    @endphp

    <div class="po-page">
        @include('purchases::partials.head', [
            'icon' => 'fa-tags',
            'sub' => 'مقارنةُ أسعارِ الموردين قبل الشراء',
            'title' => __('purchase_cycle.documents.purchase_quotation'),
            'crumbs' => [
                ['label' => __('purchase_cycle.sections.purchase_quotation'), 'url' => route('ViewPurchasePrice.index')],
                ['label' => __('purchase_orders.breadcrumb.create')],
            ],
        ])

        {{-- شريطُ السياق كان يُطبع من داخل `partials/form`؛ صار قالباً مستقلاً --}}
        @include('purchases::partials.doc-context', ['code' => $code])

        {{-- ══════════ موردو طلب التسعير ══════════
             عرضُ السعر مورّدٌ واحد بالضرورة — فطلبٌ أُرسل إلى خمسةٍ يقابله
             خمسةُ عروض تُقارَن. وكانت الشاشة لا تذكر ذلك ولا تُسمّي أحداً
             منهم، فيظنّ من اختارهم أنّ التحويل أضاعهم. --}}
        @if (! empty($quotation) && ($quotationSuppliers ?? collect())->isNotEmpty())
            @php
                $quoted = $quotedSupplierIds ?? [];
                $pendingCount = $quotationSuppliers->reject(fn ($s) => in_array((int) $s->id, $quoted, true))->count();
            @endphp

            <div class="po-doc-note">
                <span class="po-doc-note__icon"><i class="feather icon-users"></i></span>
                <div class="po-doc-note__body">
                    <strong>{{ __('purchase_cycle.fields.suppliers') }}:</strong>
                    طلبُ التسعير <span class="po-code-inline">{{ $quotation->code }}</span>
                    أُرسل إلى {{ $quotationSuppliers->count() }} موردين — سجِّل عرضَ كلِّ واحدٍ على حدة.
                    <span class="po-hint">بقي {{ $pendingCount }} بلا عرض.</span>

                    <div class="po-doc-note__chips">
                        @foreach ($quotationSuppliers as $supplier)
                            @php $isQuoted = in_array((int) $supplier->id, $quoted, true); @endphp
                            <a class="po-chip {{ $isQuoted ? 'po-chip--done' : '' }}{{ (int) ($defaults['supplier_id'] ?? 0) === (int) $supplier->id ? ' po-chip--current' : '' }}"
                                href="{{ route('ViewPurchasePrice.create') }}?quotation_id={{ $quotation->id }}&supplier_id={{ $supplier->id }}">
                                <i class="feather icon-{{ $isQuoted ? 'check' : 'plus' }}"></i>{{ $supplier->name ?? $supplier->trade_name }}
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        @include('purchases::components.guide', [
            'key' => 'purchase-quotation.create',
            'title' => 'عرض سعر شراء — ما هذه الشاشة؟',
            'lead' => 'سجّل هنا ما عرضه المورّد فعلياً: الأصناف وأسعارها وخصمها وضريبتها. بخلاف طلب عرض السعر، هذا المستند مسعَّر — منه يُشتقّ أمر الشراء لاحقاً.',
            'open' => false,
            'blocks' => [
                'دورة المستند' => [
                    'يُحفظ العرض «تحت المراجعة» ابتداءً (أو بالحالة اليدوية إن فُعِّلت من الإعدادات).',
                    'بعد الموافقة («معتمد») يصير قابلاً للتحويل إلى أمر شراء — تحويلٌ ينسخ الأصناف والأسعار كاملة.',
                    'الرفض («مرفوض») يوقف العرض دون حذفه، ويبقى في السجلّ.',
                ],
                'قبل الحفظ' => [
                    'إن جاء هذا العرض من طلب عرض سعر سابق، تُنسخ بنوده تلقائياً وتبقى مقفلة — يبقى مفتوحاً إدخال السعر والخصم والضريبة لكل بند.',
                ],
            ],
            'facts' => [
                'التعديل متاح فقط بينما العرض «تحت المراجعة».',
                'الاعتماد نهائي — لا رجوع عنه بعد الموافقة.',
            ],
        ])

        @include('purchases::partials.form', [
            'action' => route('ViewPurchasePrice.store'),
            'isEdit' => false,
            'backUrl' => route('ViewPurchasePrice.index'),
            'code' => $code,
            'documentLabel' => __('purchase_cycle.documents.purchase_quotation'),
            'products' => $products ?? collect(),
            'suppliers' => $suppliers ?? collect(),
            'taxes' => $taxes ?? collect(),
            'rows' => $rows,
            'defaults' => $defaults,
            // طلبُ عرض السعر الأصل — يُرسَل الآن مع النموذج فيقرؤه
            // `ViewPurchasePriceController::store()` (كان يُقرأ من رابط
            // `create` فقط ولا يصل الحفظ، فلا يُحفَظ `quotation_id` على
            // العرض الجديد ولا يعمل حارسُ قفل البنود المحوَّلة)
            'hidden_fields' => [
                'quotation_id' => $quotation->id ?? null,
            ],
            'config' => [
                'title' => false,
                'supplier_mode' => 'single',
                'due_date' => false,
                'valid_days' => true,
                'prices' => true,
                'tax_field' => 'tax_1',
                'attachments' => true,
                'settlement' => true,
                'date_field' => 'date',
                'branch' => true,
                'manual_status_setting' => 'manual_purchase_quotes',
            ],
        ])
    </div>
@endsection
