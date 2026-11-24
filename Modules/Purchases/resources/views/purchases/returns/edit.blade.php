{{--
    تعديل مرتجع شراء — الوجه الآخر لشاشة الإنشاء.

    كلتاهما تبنيان على نموذج دورة المشتريات المشترك (`partials/form`) نفسه
    الذي تبني به شاشة «فاتورة الشراء»، فالفرق بينهما مصدر البيانات لا الشكل:
    هنا تُملأ الحقول من المرتجع المحفوظ، وبنوده تُعاد صفوفاً بأسعارها وخصومها
    وضرائبها ووحداتها.

    **ملاحظة:** `ReturnsInvoiceController::edit()` يمنع الوصول إلى هذه الشاشة
    سياسةً — لا يُعدَّل مرتجع بعد إنشائه لأن قيده المحاسبي وحركة مخزونه
    كُتِبا — ويُعيد توجيهاً برسالة. القالب يبقى مبنيّاً وصحيحاً ليعمل متى
    رُفعت تلك السياسة، كما هو الحال في شاشة تعديل الفاتورة.
--}}
@extends('purchases::master')

@section('title')
    {{ __('purchase_cycle.documents.purchase_return') }}
@stop

@section('css')
    @include('purchases::partials.style')
    @include('purchases::partials.doc-theme-sl')
    @include('components.hint-tips')
@endsection

@section('content')
    @php
        use App\Services\Purchases\PurchaseTaxCalculator;

        $products = $items ?? collect();
        $taxes = $taxs ?? collect();

        // البنود المعادة بعد فشل تحقّق تتقدّم على المحفوظة، وإلا ضاع الإدخال
        $rows = old('items');

        if ($rows === null) {
            $rows = collect($purchaseInvoiceReturn->items ?? [])
                ->map(fn ($item) => [
                    'product_id' => $item->product_id,
                    'product_unit_id' => $item->product_unit_id,
                    // الاسم والمعامل مع المعرّف: وحدةُ الصنف تُحفظ بمعرّفٍ فارغ
                    'unit_name' => $item->unit_name,
                    'conversion_factor' => $item->conversion_factor ?: 1,
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    // خصمُ البند نسبةً من سطره — العمودُ مخفيّ؛ انظر `OrderLine::discountFields()`
                    ...\Modules\Purchases\Support\OrderLine::discountFields($item),
                    'tax_1' => (float) $item->tax_1,
                    'tax_2' => (float) $item->tax_2,
                    'tax_1_id' => $item->tax_1_id ?: PurchaseTaxCalculator::matchByRate((float) $item->tax_1),
                    'tax_2_id' => $item->tax_2_id ?: PurchaseTaxCalculator::matchByRate((float) $item->tax_2),
                ])
                ->values()
                ->all();
        }

        $original = $purchaseInvoiceReturn->originalDocument;

        $defaults = [
            'date' => old('date', $purchaseInvoiceReturn->date ? \Illuminate\Support\Carbon::parse($purchaseInvoiceReturn->date)->format('Y-m-d') : date('Y-m-d')),
            'supplier_id' => $purchaseInvoiceReturn->supplier_id,
            'terms' => $purchaseInvoiceReturn->terms,
            'notes' => $purchaseInvoiceReturn->notes,
            'attachments' => $purchaseInvoiceReturn->attachments,
            'discount_amount' => $purchaseInvoiceReturn->discount_amount,
            'discount_type' => $purchaseInvoiceReturn->discount_type == 2 ? 'percentage' : 'amount',
            'shipping_cost' => $purchaseInvoiceReturn->shipping_cost,
            'tax_id' => $purchaseInvoiceReturn->tax_type,
            'payment_method' => $purchaseInvoiceReturn->payment_method,
            'reference_id' => $purchaseInvoiceReturn->reference_id,
            'reference_code' => $original->code ?? '',
            'refund_amount' => $purchaseInvoiceReturn->advance_payment,
            'treasury_id' => $treasury_id ?? ($MainTreasury->id ?? null),
            'is_received' => (bool) $purchaseInvoiceReturn->is_received,
            'received_date' => $purchaseInvoiceReturn->received_date ? \Illuminate\Support\Carbon::parse($purchaseInvoiceReturn->received_date)->format('Y-m-d') : null,
        ];
    @endphp

    <div class="po-page po-doc--sl">
        @include('purchases::partials.head', [
            'crumbStyle' => 'breadcrumb',
            'icon' => 'rotate-ccw',
            'sub' => 'يعكس قيدَ الفاتورة سطراً بسطر ويُخرج البضاعة',
            'title' => __('purchase_cycle.documents.purchase_return'),
            'crumbs' => [
                ['label' => __('purchase_cycle.sections.purchase_return'), 'url' => route('ReturnsInvoice.index')],
                ['label' => __('purchase_orders.breadcrumb.edit')],
            ],
        ])

        @include('purchases::partials.doc-context', ['code' => $purchaseInvoiceReturn->code])

        {{-- لوحة إرشاد الشاشة — مطويّةً افتراضياً --}}
        @include('purchases::components.guide', [
            'key' => 'purchase-returns.edit',
            'title' => __('purchase_cycle.guide.return.form.title'),
            'lead' => __('purchase_cycle.guide.return.what_is'),
            'open' => false,
            'blocks' => [
                __('purchase_cycle.guide.return.source.title') => [
                    __('purchase_cycle.guide.return.source.a'),
                    __('purchase_cycle.guide.return.source.b'),
                ],
                __('purchase_cycle.guide.return.effect.title') => [
                    __('purchase_cycle.guide.return.effect.a'),
                    __('purchase_cycle.guide.return.effect.b'),
                    __('purchase_cycle.guide.return.effect.c'),
                ],
            ],
        ])


        @include('purchases::partials.form', [
            'action' => route('ReturnsInvoice.update', $purchaseInvoiceReturn->id),
            'isEdit' => true,
            'backUrl' => route('ReturnsInvoice.show', $purchaseInvoiceReturn->id),
            'code' => $purchaseInvoiceReturn->code,
            'documentLabel' => __('purchase_cycle.documents.purchase_return'),
            'products' => $products,
            'suppliers' => $suppliers ?? collect(),
            'taxes' => $taxes,
            'treasuries' => $treasuries ?? collect(),
            'rows' => $rows,
            'defaults' => $defaults,
            'config' => [
                'title' => false,
                'supplier_mode' => 'single',
                'due_date' => false,
                'prices' => true,
                'tax_field' => 'tax_1',
                'attachments' => true,
                'settlement' => true,
                'date_field' => 'date',
                'terms' => true,
                // ضريبةٌ واحدة لكل بند — أُلغيت الضريبة الثانية بطلب المستخدم
                'second_tax' => false,
                'payment' => false,
                'receiving' => true,
                // المستند يُخرج البضاعة لا يستلمها، فتنقلب التسمية
                'receiving_words' => 'goods',
                'receiving_default_setting' => 'return_default_received',
                'reference' => 'locked',
                'reference_label' => __('purchase_cycle.return.source_invoice'),
                'refund' => true,
            ],
        ])
    </div>
@endsection
