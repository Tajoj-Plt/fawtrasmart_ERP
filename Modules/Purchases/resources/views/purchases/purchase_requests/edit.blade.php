{{--
    تعديل طلب شراء — على نموذج دورة المشتريات المشترك، انظر create.blade.php.

    `manual_status_setting` غائبٌ عمداً هنا: `PurchaseRequestsController::update()`
    لا يمسّ عمود الحالة إطلاقاً (تلك مسؤولية أزرار الاعتماد/الرفض بعد
    الحفظ)، فحقلٌ هنا كان سيبدو فاعلاً بلا أي أثر.
--}}
@extends('purchases::master')

@section('title')
    {{ __('purchase_orders.titles.edit') }}
@stop

@section('css')
    @include('purchases::partials.style')
    @include('purchases::partials.doc-theme-sl')
    @include('components.hint-tips')
@endsection

@section('content')
    @php
        $rows = old('items');
        $products = $products ?? collect();
        $taxes = $taxes ?? collect();

        if ($rows === null) {
            $rows = $purchaseOrder->items->map(fn ($item) => [
                'product_id' => $item->product_id,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'product_unit_id' => $item->product_unit_id,
                'unit_name' => $item->unit_name,
                'conversion_factor' => $item->conversion_factor ?: 1,
            ])->values()->all();
        }

        $defaults = [
            'order_date' => optional($purchaseOrder->order_date)->format('Y-m-d') ?: date('Y-m-d'),
            'due_date' => optional($purchaseOrder->due_date)->format('Y-m-d'),
            'title' => $purchaseOrder->title,
            'notes' => $purchaseOrder->notes,
            'attachments' => $purchaseOrder->attachments,
            'branch_id' => $purchaseOrder->branch_id,
        ];
    @endphp

    <div class="po-page po-doc--sl">
        @include('purchases::partials.head', [
            'crumbStyle' => 'breadcrumb',
            'icon' => 'clipboard',
            'sub' => 'طلبٌ داخليّ يسبق أمر الشراء',
            'title' => __('purchase_orders.titles.edit').' — '.$purchaseOrder->code,
            'crumbs' => [
                ['label' => __('purchase_orders.breadcrumb.index'), 'url' => route('PurchaseRequests.index')],
                ['label' => $purchaseOrder->code, 'url' => route('PurchaseRequests.show', $purchaseOrder->id)],
                ['label' => __('purchase_orders.breadcrumb.edit')],
            ],
            'action' => view('purchases::partials.status', ['status' => $purchaseOrder->status])->render(),
        ])

        @include('purchases::partials.doc-context', ['code' => $purchaseOrder->code])

        {{-- لوحة إرشاد الشاشة — مفتاحٌ يخصّ التعديل: قاعدة «لا تعديل بعد
             الاعتماد أو الرفض» تهمّ من فتح هذه الشاشة لا من فتح شاشة الإنشاء --}}
        @include('purchases::components.guide', [
            'key' => 'purchase-orders.edit',
            'title' => __('purchase_orders.guide.form.title'),
            'lead' => __('purchase_orders.guidance.purchase_requests_edit'),
            'open' => false,
            'blocks' => [
                __('purchase_orders.guide.form.noedit.title') => [
                    __('purchase_orders.guide.form.noedit.a'),
                    __('purchase_orders.guide.form.noedit.b'),
                ],
                __('purchase_orders.guide.form.save.title') => [
                    __('purchase_orders.guide.form.save.a'),
                    __('purchase_orders.guide.form.save.b'),
                    __('purchase_orders.guide.form.save.c'),
                ],
                __('purchase_orders.guide.statuses.title') => [
                    __('purchase_orders.guide.statuses.a'),
                    __('purchase_orders.guide.statuses.b'),
                    __('purchase_orders.guide.statuses.c'),
                ],
            ],
        ])

        @include('purchases::partials.form', [
            'action' => route('PurchaseRequests.update', $purchaseOrder->id),
            'isEdit' => true,
            'backUrl' => route('PurchaseRequests.show', $purchaseOrder->id),
            'code' => $purchaseOrder->code,
            'documentLabel' => __('purchase_orders.titles.edit').' — '.$purchaseOrder->code,
            'products' => $products,
            'suppliers' => collect(),
            'taxes' => $taxes,
            'rows' => $rows,
            'defaults' => $defaults,
            'config' => [
                'title' => true,
                'supplier_mode' => 'none',
                'due_date' => true,
                'prices' => false,
                'attachments' => true,
                'date_field' => 'order_date',
                'branch' => true,
            ],
        ])
    </div>
@endsection
