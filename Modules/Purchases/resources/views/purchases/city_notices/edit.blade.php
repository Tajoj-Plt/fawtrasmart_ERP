{{--
    تعديل إشعار مدين — الوجه الآخر لشاشة الإنشاء.

    كلتاهما تبنيان على نموذج دورة المشتريات المشترك (`partials/form`) نفسه
    الذي تبني به شاشة «فاتورة الشراء»، فالفرق بينهما مصدر البيانات لا الشكل:
    هنا تُملأ الحقول من الإشعار المحفوظ، وبنوده تُعاد صفوفاً بأسعارها وخصومها
    وضرائبها ووحداتها.

    **ملاحظة:** `CityNoticesController::edit()` يمنع الوصول إلى هذه الشاشة
    سياسةً — لا يُعدَّل إشعار بعد إنشائه لأن قيده المحاسبي كُتِب — ويُعيد
    توجيهاً برسالة. القالب يبقى مبنيّاً وصحيحاً ليعمل متى رُفعت تلك السياسة،
    كما هو الحال في شاشة تعديل الفاتورة.
--}}
@extends('purchases::master')

@section('title')
    {{ __('purchase_cycle.documents.debit_note') }}
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
            $rows = collect($cityNotice->items ?? [])
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

        $original = $cityNotice->originalDocument;

        $defaults = [
            'date' => old('date', $cityNotice->date ? \Illuminate\Support\Carbon::parse($cityNotice->date)->format('Y-m-d') : date('Y-m-d')),
            'supplier_id' => $cityNotice->supplier_id,
            'terms' => $cityNotice->terms,
            'notes' => $cityNotice->notes,
            'attachments' => $cityNotice->attachments,
            'discount_amount' => $cityNotice->discount_amount,
            'discount_type' => $cityNotice->discount_type == 2 ? 'percentage' : 'amount',
            'shipping_cost' => $cityNotice->shipping_cost,
            'tax_id' => $cityNotice->tax_type,
            'advance_payment' => $cityNotice->advance_payment,
            'advance_payment_type' => $cityNotice->advance_payment_type == 2 ? 'percentage' : 'amount',
            'payment_method' => $cityNotice->payment_method,
            'reference_number' => $cityNotice->reference_number,
            'reference_id' => $cityNotice->reference_id,
            'reference_code' => $original->code ?? '',
            'treasury_id' => $treasury_id ?? ($MainTreasury->id ?? null),
            'is_received' => (bool) $cityNotice->is_received,
            'received_date' => $cityNotice->received_date ? \Illuminate\Support\Carbon::parse($cityNotice->received_date)->format('Y-m-d') : null,
        ];
    @endphp

    <div class="po-page po-doc--sl">
        @include('purchases::partials.head', [
            'crumbStyle' => 'breadcrumb',
            'icon' => 'file-text',
            'sub' => 'يُنقص ما علينا للمورد بقيدٍ يعكس الفاتورة',
            'title' => __('purchase_cycle.documents.debit_note'),
            'crumbs' => [
                ['label' => __('purchase_cycle.sections.debit_note'), 'url' => route('CityNotices.index')],
                ['label' => __('purchase_orders.breadcrumb.edit')],
            ],
        ])

        @include('purchases::partials.doc-context', ['code' => $cityNotice->code])

        {{-- لوحة إرشاد الشاشة — مطويّةً افتراضياً --}}
        @include('purchases::components.guide', [
            'key' => 'debit-notes.edit',
            'title' => __('purchase_cycle.guide.notice.form.title'),
            'lead' => __('purchase_cycle.guide.notice.what_is'),
            'open' => false,
            'blocks' => [
                __('purchase_cycle.guide.notice.vs_return.title') => [
                    __('purchase_cycle.guide.notice.vs_return.a'),
                    __('purchase_cycle.guide.notice.vs_return.b'),
                    __('purchase_cycle.guide.notice.vs_return.c'),
                ],
                __('purchase_cycle.guide.notice.enable.title') => [
                    __('purchase_cycle.guide.notice.enable.a'),
                ],
            ],
        ])


        @include('purchases::partials.form', [
            'action' => route('CityNotices.update', $cityNotice->id),
            'isEdit' => true,
            'backUrl' => route('CityNotices.show', $cityNotice->id),
            'code' => $cityNotice->code,
            'documentLabel' => __('purchase_cycle.documents.debit_note'),
            'products' => $products,
            'suppliers' => $suppliers ?? collect(),
            'taxes' => $taxes,
            'treasuries' => $treasuries ?? collect(),
            'referenceInvoices' => $availableInvoices ?? collect(),
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
                'payment' => true,
                'receiving' => true,
                // المستند يُخرج البضاعة لا يستلمها، فتنقلب التسمية
                'receiving_words' => 'goods',
                'receiving_default_setting' => 'notice_default_received',
                'reference' => $original ? 'locked' : 'optional',
                'reference_label' => __('purchase_cycle.debit_note.source_invoice'),
            ],
        ])
    </div>
@endsection
