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

        $rows = old('items');
        $products = $items ?? collect();
        $taxes = $taxs ?? collect();

        // طلب عرض الأسعار الذي صدر عنه هذا العرض — منه تُعرف البنود الموروثة
        $sourceQuotation = $purchaseQuotation->quotation_id
            ? \App\Models\PurchaseQuotation::with('items')->find($purchaseQuotation->quotation_id)
            : null;

        if ($rows === null) {
            $rows = $purchaseQuotation->items->map(fn ($item) => [
                'product_id' => $item->product_id,
                'description' => $item->description,
                'quantity' => $item->quantity,
                // وحدة البند تُنقل معه — وحدةُ الصنف بلا صفٍّ في `product_units` لا معرّف لها،
                // فالاسم والمعامل هما ما يعيد «عبوة» مكان «حبّة» الافتراضية
                'product_unit_id' => $item->product_unit_id,
                'unit_name' => $item->unit_name,
                'conversion_factor' => $item->conversion_factor ?: 1,
                'unit_price' => $item->unit_price,
                // خصمُ البند نسبةً من سطره — العمودُ مخفيّ؛ انظر `OrderLine::discountFields()`
                ...\Modules\Purchases\Support\OrderLine::discountFields($item),
                'tax_1' => $item->tax_1,
                // البنود المحفوظة قبل عمود المعرّف تُطابَق بالنسبة، وإلا
                // سقطت ضريبتها عند أول تعديل
                'tax_1_id' => $item->tax_1_id
                    ?: \App\Services\Purchases\PurchaseTaxCalculator::matchByRate((float) $item->tax_1),
            ])->values()->all();
        }

        // القفل على أصل البند لا على شاشة بعينها: ما وُرِث بالتحويل يبقى
        // موروثاً في التعديل. والسعر والخصم والضريبة تبقى مفتوحةً كما في
        // الإنشاء — هي ما يضيفه عرض السعر
        $rows = ConvertedLines::mark($rows, optional($sourceQuotation)->items ?? []);

        $defaults = [
            'date' => optional($purchaseQuotation->date)->format('Y-m-d'),
            'valid_days' => $purchaseQuotation->valid_days,
            'status' => $purchaseQuotation->status,
            'supplier_id' => $purchaseQuotation->supplier_id,
            'notes' => $purchaseQuotation->notes,
            'shipping_cost' => $purchaseQuotation->shipping_cost,
            'discount_amount' => $purchaseQuotation->discount_amount,
            'discount_type' => $purchaseQuotation->discount_type,
            'adjustment_label' => $purchaseQuotation->adjustment_label,
            'adjustment_type' => $purchaseQuotation->adjustment_type,
            'adjustment_value' => $purchaseQuotation->adjustment_value,
            'attachments' => $purchaseQuotation->attachments,
            'branch_id' => $purchaseQuotation->branch_id,
        ];
        $code = $purchaseQuotation->code;
    @endphp

    <div class="po-page">
        @include('purchases::partials.head', [
            'icon' => 'fa-tags',
            'sub' => 'مقارنةُ أسعارِ الموردين قبل الشراء',
            'title' => __('purchase_cycle.documents.purchase_quotation') . ' — ' . $purchaseQuotation->code,
            'crumbs' => [
                ['label' => __('purchase_cycle.sections.purchase_quotation'), 'url' => route('ViewPurchasePrice.index')],
                ['label' => $purchaseQuotation->code, 'url' => route('ViewPurchasePrice.show', $purchaseQuotation->id)],
                ['label' => __('purchase_orders.breadcrumb.edit')],
            ],
            'action' => view('purchases::partials.status', ['status' => $purchaseQuotation->status])->render(),
        ])

        {{-- شريطُ السياق كان يُطبع من داخل `partials/form`؛ صار قالباً مستقلاً --}}
        @include('purchases::partials.doc-context', ['code' => $code])

        @include('purchases::components.guide', [
            'key' => 'purchase-quotation.edit',
            'title' => 'تعديل عرض سعر شراء',
            'lead' => 'التعديل متاحٌ فقط بينما العرض «تحت المراجعة» — بعد الاعتماد أو الرفض يُقفَل نهائياً.',
            'open' => false,
            'blocks' => [
                'ما يبقى مقفولاً' => [
                    'البنود الموروثة من طلب عرض سعر سابق تبقى مقفلة الصنف والكمّية والوصف — والسعر والخصم والضريبة تبقى مفتوحة للتعديل.',
                ],
            ],
            'facts' => [
                'الاعتماد نهائي — لا رجوع عنه بعد الموافقة.',
            ],
        ])

        @include('purchases::partials.form', [
            'action' => route('ViewPurchasePrice.update', $purchaseQuotation->id),
            'isEdit' => true,
            'backUrl' => route('ViewPurchasePrice.show', $purchaseQuotation->id),
            'code' => $code,
            'documentLabel' => __('purchase_cycle.documents.purchase_quotation') . ' — ' . $purchaseQuotation->code,
            'products' => $products ?? collect(),
            'suppliers' => $suppliers ?? collect(),
            'taxes' => $taxes ?? collect(),
            'rows' => $rows,
            'defaults' => $defaults,
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
