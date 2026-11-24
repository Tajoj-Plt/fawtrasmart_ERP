{{--
    إنشاء إشعار مدين.

    أُعيد بناء الشاشة على نموذج دورة المشتريات المشترك (`partials/form`) —
    نفسه الذي تبني به شاشة «فاتورة الشراء» و«مرتجع الشراء» — فصارت الشاشات
    الثلاث واحدةً بنيةً وشكلاً وسلوكاً: الترتيب نفسه (بيانات المستند ← البنود
    ← التفاصيل الإضافية ← الملاحظات وأزرار الحفظ)، وجدول البنود نفسه، وحساب
    الإجماليات نفسه.

    الإشعار المدين — بخلاف المرتجع — قد يصدر عن فاتورة وقد يصدر مستقلاً:
      • جاء من فاتورة (`$originalInvoice`) → المرجع مقفل وبنوده منسوخة عنها
      • فُتِح من القائمة               → المرجع اختياري من قائمة الفواتير

    ما يأتي من الإعدادات:
      • الرقم         ← «تسلسل أرقام المستندات» (قسم purchase-debit-note)
      • نِسَب الضريبة  ← «إعدادات الضريبة»
      • الخزائن       ← «الحسابات المحاسبية» (أبناء الخزائن والبنوك)
      • العملة والكسور ← «العملات»
      • صيغة التاريخ  ← إعدادات اللغة النشطة
      • الألوان والخط ← «مظهر النظام» عبر متغيّرات CSS
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
        $original = $originalInvoice ?? null;

        // البنود المعادة بعد فشل تحقّق تتقدّم على المنسوخة، وإلا ضاع الإدخال
        $rows = old('items');

        if ($rows === null) {
            $rows = collect($original?->items ?? [])
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
                    // البنود المحفوظة قبل إضافة عمود المعرّف تُطابَق بالنسبة،
                    // وإلا سقطت ضريبتها عن الإشعار
                    'tax_1_id' => $item->tax_1_id ?: PurchaseTaxCalculator::matchByRate((float) $item->tax_1),
                    'tax_2_id' => $item->tax_2_id ?: PurchaseTaxCalculator::matchByRate((float) $item->tax_2),
                ])
                ->values()
                ->all();
        }

        $defaults = [
            'date' => old('date', date('Y-m-d')),
            'supplier_id' => $original?->supplier_id,
            'terms' => $original?->terms,
            'discount_amount' => $original?->discount_amount,
            'discount_type' => $original && $original->discount_type == 2 ? 'percentage' : 'amount',
            'shipping_cost' => $original?->shipping_cost,
            'tax_id' => $original?->tax_type,
            'payment_method' => $original?->payment_method,
            'reference_id' => $original?->id,
            'reference_code' => $original?->code,
            'treasury_id' => $treasury_id ?? ($MainTreasury->id ?? null),
        ];
    @endphp

    <div class="po-page po-doc--sl">
        @include('purchases::partials.head', [
            'crumbStyle' => 'breadcrumb',
            'icon' => 'file-text',
            'sub' => 'يُنقص ما علينا للمورد بقيدٍ يعكس الفاتورة',
            'title' => __('purchase_cycle.documents.debit_note'),
            'crumbs' => array_values(array_filter([
                ['label' => __('purchase_cycle.sections.debit_note'), 'url' => route('CityNotices.index')],
                $original ? ['label' => $original->code, 'url' => route('invoicePurchases.show', $original->id)] : null,
                ['label' => __('purchase_orders.breadcrumb.create')],
            ])),
        ])

        @include('purchases::partials.doc-context', ['code' => $code])

        @include('purchases::partials.period-block')

        {{-- لوحة إرشاد الشاشة — مطويّةً افتراضياً --}}
        @include('purchases::components.guide', [
            'key' => 'debit-notes.create',
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
                __('purchase_cycle.guide.form.blocked.title') => [
                    __('purchase_cycle.guide.form.blocked.a'),
                    __('purchase_cycle.guide.form.blocked.b'),
                ],
            ],
            'facts' => [
                __('purchase_cycle.guide.facts.ledger'),
                __('purchase_cycle.guide.facts.supplier'),
            ],
        ])


        @include('purchases::partials.form', [
            'action' => route('CityNotices.store'),
            'isEdit' => false,
            'backUrl' => $original ? route('invoicePurchases.show', $original->id) : route('CityNotices.index'),
            'code' => $code,
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
                // المرجع مقفل متى جاء الإشعار من فاتورة، واختياري متى فُتِح
                // من القائمة — فلا يُفقَد الربط ولا يُفرَض حيث لا يلزم
                'reference' => $original ? 'locked' : 'optional',
                'reference_label' => __('purchase_cycle.debit_note.source_invoice'),
                'branch' => true,
            ],
        ])
    </div>
@endsection
