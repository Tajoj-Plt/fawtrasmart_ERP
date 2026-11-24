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
        $rows = old('items');
        $products = $items ?? collect();
        $taxes = $taxs ?? collect();

        if ($rows === null) {
            $rows = $purchaseOrdersRequests->items->map(fn ($item) => [
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

        $defaults = [
            // النموذج بلا $casts عمداً (انظر تعليق app/Models/PurchaseInvoice.php) —
            // التاريخ يصل من القاعدة نصّاً بصيغة Y-m-d جاهزة لحقل input[type=date]
            // مباشرة، فلا داعي لـ optional()->format() التي تفترض كائن Carbon
            'date' => $purchaseOrdersRequests->date,
            'due_date' => $purchaseOrdersRequests->due_date,
            'supplier_id' => $purchaseOrdersRequests->supplier_id,
            'notes' => $purchaseOrdersRequests->notes,
            'shipping_cost' => $purchaseOrdersRequests->shipping_cost,
            'discount_amount' => $purchaseOrdersRequests->discount_amount,
            'discount_type' => $purchaseOrdersRequests->discount_type,
            'adjustment_label' => $purchaseOrdersRequests->adjustment_label,
            'adjustment_type' => $purchaseOrdersRequests->adjustment_type,
            'adjustment_value' => $purchaseOrdersRequests->adjustment_value,
            'attachments' => $purchaseOrdersRequests->attachments,
            'branch_id' => $purchaseOrdersRequests->branch_id,
            'status' => $purchaseOrdersRequests->status,
        ];
        $code = $purchaseOrdersRequests->code;
    @endphp

    <div class="po-page po-doc--sl">
        @include('purchases::partials.head', [
            'crumbStyle' => 'breadcrumb',
            'icon' => 'clipboard',
            'sub' => 'طلبٌ للمورد — لا قيد ولا حركة مخزون حتى الاستلام',
            'title' => __('purchase_cycle.documents.purchase_order_request') . ' — ' . $purchaseOrdersRequests->code,
            'crumbs' => [
                ['label' => __('purchase_cycle.sections.purchase_order_request'), 'url' => route('PurchaseOrders.index')],
                ['label' => $purchaseOrdersRequests->code, 'url' => route('PurchaseOrders.show', $purchaseOrdersRequests->id)],
                ['label' => __('purchase_orders.breadcrumb.edit')],
            ],
            'action' => view('purchases::partials.status', ['status' => $purchaseOrdersRequests->status])->render(),
        ])

        @include('purchases::partials.doc-context', ['code' => $code])

        {{-- لوحة إرشاد الشاشة — مفتاحٌ يخصّ التعديل --}}
        @include('purchases::components.guide', [
            'key' => 'purchase-order-requests.edit',
            'title' => __('purchase_cycle.guide.order_request.form.title'),
            'lead' => __('purchase_orders.guidance.purchase_orders_edit'),
            'open' => false,
            'blocks' => [
                __('purchase_cycle.guide.order_request.form.noedit.title') => [
                    __('purchase_cycle.guide.order_request.form.noedit.a'),
                    __('purchase_cycle.guide.order_request.form.noedit.b'),
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
            ],
        ])

        @include('purchases::partials.form', [
            'action' => route('PurchaseOrders.update', $purchaseOrdersRequests->id),
            'isEdit' => true,
            'backUrl' => route('PurchaseOrders.show', $purchaseOrdersRequests->id),
            'code' => $code,
            'documentLabel' => __('purchase_cycle.documents.purchase_order_request') . ' — ' . $purchaseOrdersRequests->code,
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
