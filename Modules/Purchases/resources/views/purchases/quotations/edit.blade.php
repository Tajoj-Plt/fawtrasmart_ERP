@extends('purchases::master')

@section('title')
    {{ __('purchase_cycle.documents.quotation_request') }}
@stop

@section('css')
    @include('purchases::partials.style')
    @include('purchases::partials.doc-theme-sl')
    @include('components.hint-tips')
@endsection

@section('content')
    @php
        use Modules\Purchases\Support\ConvertedLines;

        $rows = old('items');

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
            ])->values()->all();
        }

        // الطلب المولَّد بالتحويل يبقى مقفول البنود في التعديل أيضاً — القفل
        // على أصل البند لا على شاشة بعينها
        $rows = ConvertedLines::mark($rows, optional($purchaseQuotation->purchaseOrder)->items ?? []);

        $defaults = [
            'title' => $purchaseQuotation->title,
            'order_date' => optional($purchaseQuotation->order_date)->format('Y-m-d'),
            'due_date' => optional($purchaseQuotation->due_date)->format('Y-m-d'),
            'notes' => $purchaseQuotation->notes,
            'attachments' => $purchaseQuotation->attachments,
            'supplier_id' => $purchaseQuotation->suppliers->pluck('id')->all(),
            'branch_id' => $purchaseQuotation->branch_id,
        ];
        $code = $purchaseQuotation->code;
    @endphp

    <div class="po-page po-doc--sl">
        @include('purchases::partials.head', [
            'crumbStyle' => 'breadcrumb',
            'icon' => 'file-text',
            'sub' => 'عرضُ سعرٍ من المورد — لا أثر محاسبيّ',
            'title' => __('purchase_cycle.documents.quotation_request') . ' — ' . $purchaseQuotation->code,
            'crumbs' => [
                ['label' => __('purchase_cycle.sections.quotation_request'), 'url' => route('Quotations.index')],
                ['label' => $purchaseQuotation->code, 'url' => route('Quotations.show', $purchaseQuotation->id)],
                ['label' => __('purchase_orders.breadcrumb.edit')],
            ],
            'action' => view('purchases::partials.status', ['status' => $purchaseQuotation->status])->render(),
        ])

        @include('purchases::partials.doc-context', ['code' => $code])

        {{-- لوحة إرشاد الشاشة — مفتاحٌ يخصّ التعديل --}}
        @include('purchases::components.guide', [
            'key' => 'purchase-quotation-requests.edit',
            'title' => __('purchase_cycle.guide.quotation_request.form.title'),
            'lead' => __('purchase_orders.guidance.quotations_edit'),
            'open' => false,
            'blocks' => [
                __('purchase_cycle.guide.quotation_request.form.noedit.title') => [
                    __('purchase_cycle.guide.quotation_request.form.noedit.a'),
                    __('purchase_cycle.guide.quotation_request.form.noedit.b'),
                ],
                __('purchase_cycle.guide.quotation_request.form.save.title') => [
                    __('purchase_cycle.guide.quotation_request.form.save.a'),
                    __('purchase_cycle.guide.quotation_request.form.save.b'),
                    __('purchase_cycle.guide.quotation_request.form.save.c'),
                ],
                __('purchase_cycle.guide.quotation_request.statuses.title') => [
                    __('purchase_cycle.guide.quotation_request.statuses.a'),
                    __('purchase_cycle.guide.quotation_request.statuses.b'),
                    __('purchase_cycle.guide.quotation_request.statuses.c'),
                ],
            ],
        ])

        @include('purchases::partials.form', [
            'action' => route('Quotations.update', $purchaseQuotation->id),
            'isEdit' => true,
            'backUrl' => route('Quotations.show', $purchaseQuotation->id),
            'code' => $code,
            'documentLabel' => __('purchase_cycle.documents.quotation_request') . ' — ' . $purchaseQuotation->code,
            'products' => $products ?? collect(),
            'suppliers' => $suppliers ?? collect(),
            'taxes' => $taxes ?? collect(),
            'rows' => $rows,
            'defaults' => $defaults,
            'config' => [
                'title' => true,
                'supplier_mode' => 'multi',
                'due_date' => true,
                'prices' => false,
                'attachments' => true,
                'date_field' => 'order_date',
                'branch' => true,
            ],
        ])
    </div>
@endsection
