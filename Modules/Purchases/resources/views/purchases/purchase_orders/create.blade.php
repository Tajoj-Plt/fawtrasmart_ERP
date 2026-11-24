@extends('purchases::master')

@section('title')
    {{ __('purchase_cycle.documents.purchase_order_request') }}
@stop

@section('css')
    @include('purchases::partials.style')
    @include('purchases::partials.doc-theme-sl')
    @include('components.hint-tips')
@endsection

@section('content')
    @php
        $rows = old('items', []);
        $products = $items ?? collect();
        $taxes = $taxs ?? collect();
        $defaults = [
            'date' => date('Y-m-d'),
            'supplier_id' => $selectedSupplier->id ?? null,
        ];

        // التحويل من عرض سعر معتمَد: المستند يُنقل كاملاً — المورّد
        // والتواريخ والملاحظات والبنود بأسعارها وخصومها وضرائبها
        if (! empty($quotation)) {
            $defaults['supplier_id'] = $defaults['supplier_id'] ?: ($quotation->supplier_id ?? null);
            $defaults['notes'] = $defaults['notes'] ?? $quotation->notes;
            $defaults['date'] = old('date', optional($quotation->date)->format('Y-m-d') ?: date('Y-m-d'));

            if (! $rows) {
                $rows = $quotation->items->map(fn ($item) => [
                    'product_id' => $item->product_id,
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    // وحدة البند تُنقل معه — وحدةُ الصنف بلا صفٍّ في `product_units` لا معرّف لها،
                    // فالاسم والمعامل هما ما يعيد «عبوة» مكان «حبّة» الافتراضية
                    'product_unit_id' => $item->product_unit_id,
                    'unit_name' => $item->unit_name,
                    'conversion_factor' => $item->conversion_factor ?: 1,
                    // سعرُ وحدة البند لا سعر الحبّة الأساسية — كرتونٌ ١٩١٫٣٠ كان يصل ٠٫١٩
                    'unit_price' => $item->unit_price ?: ($item->product
                        ? \Modules\Purchases\Support\ProductUnits::linePrice([
                            'product_unit_id' => $item->product_unit_id,
                            'conversion_factor' => $item->conversion_factor,
                        ], $item->product)
                        : 0),
                    // خصمُ البند نسبةً من سطره — العمودُ مخفيّ؛ انظر `OrderLine::discountFields()`
                    ...\Modules\Purchases\Support\OrderLine::discountFields($item),
                    'tax_1' => $item->tax_1 ?? 0,
                ])->values()->all();
            }
        }
    @endphp

    <div class="po-page po-doc--sl">
        @include('purchases::partials.head', [
            'crumbStyle' => 'breadcrumb',
            'icon' => 'clipboard',
            'sub' => 'طلبٌ للمورد — لا قيد ولا حركة مخزون حتى الاستلام',
            'title' => __('purchase_cycle.documents.purchase_order_request'),
            'crumbs' => [
                ['label' => __('purchase_cycle.sections.purchase_order_request'), 'url' => route('PurchaseOrders.index')],
                ['label' => __('purchase_orders.breadcrumb.create')],
            ],
        ])

        @include('purchases::partials.doc-context', ['code' => $code])

        {{-- لوحة إرشاد الشاشة — مفتاحها يخصّ الإنشاء وحده --}}
        @include('purchases::components.guide', [
            'key' => 'purchase-order-requests.create',
            'title' => __('purchase_cycle.guide.order_request.form.title'),
            'lead' => __('purchase_cycle.guide.order_request.what_is'),
            'open' => false,
            'blocks' => [
                __('purchase_cycle.guide.order_request.cycle.title') => [
                    __('purchase_cycle.guide.order_request.cycle.a'),
                    __('purchase_cycle.guide.order_request.cycle.b'),
                    __('purchase_cycle.guide.order_request.cycle.c'),
                ],
                __('purchase_cycle.guide.order_request.form.save.title') => [
                    __('purchase_cycle.guide.order_request.form.save.a'),
                    __('purchase_cycle.guide.order_request.form.save.b'),
                    __('purchase_cycle.guide.order_request.form.save.c'),
                ],
                __('purchase_cycle.guide.order_request.statuses.title') => [
                    __('purchase_cycle.guide.order_request.statuses.a'),
                    __('purchase_cycle.guide.order_request.statuses.b'),
                    __('purchase_cycle.guide.order_request.statuses.c'),
                ],
                __('purchase_cycle.guide.order_request.form.noedit.title') => [
                    __('purchase_cycle.guide.order_request.form.noedit.a'),
                    __('purchase_cycle.guide.order_request.form.noedit.b'),
                ],
            ],
            'facts' => [
                __('purchase_cycle.guide.order_request.facts.no_ledger_yet'),
                __('purchase_cycle.guide.order_request.facts.to_invoice'),
                __('purchase_cycle.guide.order_request.facts.final'),
            ],
        ])

        @include('purchases::partials.form', [
            'action' => route('PurchaseOrders.store'),
            'isEdit' => false,
            'backUrl' => route('PurchaseOrders.index'),
            'code' => $code,
            'documentLabel' => __('purchase_cycle.documents.purchase_order_request'),
            'products' => $products ?? collect(),
            'suppliers' => $suppliers ?? collect(),
            'taxes' => $taxes ?? collect(),
            'rows' => $rows,
            'defaults' => $defaults,
            'config' => [
                'title' => false,
                'supplier_mode' => 'single',
                'due_date' => true,
                'prices' => true,
                'tax_field' => 'tax_1',
                'attachments' => true,
                'settlement' => true,
                'date_field' => 'date',
                'branch' => true,
                'manual_status_setting' => 'manual_purchase_orders_status',
            ],
        ])
    </div>
@endsection
