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

        // البنود المعادة بعد فشل تحقّق تتقدّم على الفارغة
        $rows = old('items', []);
        $defaults = [
            'order_date' => date('Y-m-d'),
            'supplier_id' => [],
        ];

        // التحويل من طلب شراء معتمَد: تُنقل بياناته كاملة.
        // طلب التسعير بلا أسعار عمداً — هو طلبُ سعرٍ لا تسعيرة.
        if (! empty($purchaseOrder)) {
            $defaults['title'] = old('title', $purchaseOrder->title);
            $defaults['notes'] = old('notes', $purchaseOrder->notes);
            $defaults['order_date'] = old('order_date', optional($purchaseOrder->order_date)->format('Y-m-d') ?: date('Y-m-d'));
            $defaults['due_date'] = old('due_date', optional($purchaseOrder->due_date)->format('Y-m-d'));
        }

        if (! $rows && isset($orderItems)) {
            $rows = $orderItems->map(fn ($item) => [
                'product_id' => $item->product_id,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'product_unit_id' => $item->product_unit_id,
                'unit_name' => $item->unit_name,
                'conversion_factor' => $item->conversion_factor ?: 1,
            ])->values()->all();
        }

        // بنود التحويل مقفلة: ما اعتُمد في طلب الشراء لا يُبدَّل هنا، وإلا
        // خرج طلبُ التسعير يطلب غير ما وافقت عليه الإدارة
        $rows = ConvertedLines::mark($rows, $orderItems ?? []);
    @endphp

    <div class="po-page po-doc--sl">
        @include('purchases::partials.head', [
            'crumbStyle' => 'breadcrumb',
            'icon' => 'file-text',
            'sub' => 'عرضُ سعرٍ من المورد — لا أثر محاسبيّ',
            'title' => __('purchase_cycle.documents.quotation_request'),
            'crumbs' => [
                ['label' => __('purchase_cycle.sections.quotation_request'), 'url' => route('Quotations.index')],
                ['label' => __('purchase_orders.breadcrumb.create')],
            ],
        ])

        @include('purchases::partials.doc-context', ['code' => $code])

        {{-- لوحة إرشاد الشاشة — مفتاحها يخصّ الإنشاء وحده --}}
        @include('purchases::components.guide', [
            'key' => 'purchase-quotation-requests.create',
            'title' => __('purchase_cycle.guide.quotation_request.form.title'),
            'lead' => __('purchase_cycle.guide.quotation_request.what_is'),
            'open' => false,
            'blocks' => [
                __('purchase_cycle.guide.quotation_request.cycle.title') => [
                    __('purchase_cycle.guide.quotation_request.cycle.a'),
                    __('purchase_cycle.guide.quotation_request.cycle.b'),
                    __('purchase_cycle.guide.quotation_request.cycle.c'),
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
                __('purchase_cycle.guide.quotation_request.form.noedit.title') => [
                    __('purchase_cycle.guide.quotation_request.form.noedit.a'),
                    __('purchase_cycle.guide.quotation_request.form.noedit.b'),
                ],
            ],
            'facts' => [
                __('purchase_cycle.guide.quotation_request.facts.no_prices'),
                __('purchase_cycle.guide.quotation_request.facts.no_ledger'),
                __('purchase_cycle.guide.quotation_request.facts.final'),
            ],
        ])

        @include('purchases::partials.form', [
            'action' => route('Quotations.store'),
            'isEdit' => false,
            'backUrl' => route('Quotations.index'),
            'code' => $code,
            'documentLabel' => __('purchase_cycle.documents.quotation_request'),
            'products' => $products ?? collect(),
            'suppliers' => $suppliers ?? collect(),
            'taxes' => $taxes ?? collect(),
            'rows' => $rows,
            'defaults' => $defaults,
            // طلبُ الشراء الأصل — يُرسَل الآن مع النموذج فيقرؤه
            // `QuotationsController::store()` (كان يُقرأ من رابط `create` فقط
            // ولا يصل الحفظ، فلا تتحدَّث حالةُ طلب الشراء إلى «Convert to
            // Quotation» ولا تُربَط الفاتورةُ الجديدة بأصلها)
            'hidden_fields' => [
                'order_id' => $purchaseOrder->id ?? null,
            ],
            'config' => [
                'title' => true,
                'supplier_mode' => 'multi',
                'due_date' => true,
                'prices' => false,
                'attachments' => true,
                'date_field' => 'order_date',
                'branch' => true,
                // `manual_quote_orders` يُتاح عند الإنشاء وحده — update()
                // في QuotationsController لا يقرأ حقل الحالة إطلاقاً، فلا
                // يُمرَّر هذا المفتاح من edit.blade.php حتى لا يظهر حقل
                // يبدو فاعلاً ولا يغيّر شيئاً فعلياً
                'manual_status_setting' => 'manual_quote_orders',
            ],
        ])
    </div>
@endsection
