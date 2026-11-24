{{--
    إنشاء مرتجع شراء.

    أُعيد بناء الشاشة على نموذج دورة المشتريات المشترك (`partials/form`) —
    نفسه الذي تبني به شاشة «فاتورة الشراء» — بدل ألفَي سطر مكتوبة لها وحدها.
    فصارت الشاشتان واحدةً بنيةً وشكلاً وسلوكاً: الترتيب نفسه (بيانات المستند
    ← البنود ← التفاصيل الإضافية ← الملاحظات وأزرار الحفظ)، وجدول البنود
    نفسه، وحساب الإجماليات نفسه.

    **المرتجع مستندٌ تابع.** لا تُفتح هذه الشاشة إلا من فاتورة شراء قائمة،
    فـ`$originalInvoice` مضمون الوجود: بنوده تُنسَخ عن بنودها، ومورّده مورّدها،
    ومعرّفها يرافقه مخفيّاً إلى الخادم (`config['reference'] = 'locked'`) فلا
    يُنسَب المرتجع إلى غير فاتورته.

    ما تنفرد به شاشة المرتجع عن شاشة الفاتورة:
      • `reference`  الفاتورة الأصلية — تُعرَض ولا تُبدَّل
      • `refund`     تبويب الاسترداد: المبلغ المردود وخزينته

    وما يأتي من الإعدادات:
      • الرقم         ← «تسلسل أرقام المستندات» (قسم purchase-return)
      • نِسَب الضريبة  ← «إعدادات الضريبة»
      • الخزائن       ← «الحسابات المحاسبية» (أبناء الخزائن والبنوك)
      • العملة والكسور ← «العملات»
      • صيغة التاريخ  ← إعدادات اللغة النشطة
      • الألوان والخط ← «مظهر النظام» عبر متغيّرات CSS
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

        // البنود المعادة بعد فشل تحقّق تتقدّم على المنسوخة، وإلا ضاع الإدخال
        $rows = old('items');

        if ($rows === null) {
            $rows = collect($originalInvoice->items ?? [])
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
                    // وإلا سقطت ضريبتها عن المرتجع
                    'tax_1_id' => $item->tax_1_id ?: PurchaseTaxCalculator::matchByRate((float) $item->tax_1),
                    'tax_2_id' => $item->tax_2_id ?: PurchaseTaxCalculator::matchByRate((float) $item->tax_2),
                ])
                ->values()
                ->all();
        }

        $defaults = [
            'date' => old('date', date('Y-m-d')),
            'supplier_id' => $originalInvoice->supplier_id,
            'terms' => $originalInvoice->terms,
            'discount_amount' => $originalInvoice->discount_amount,
            'discount_type' => $originalInvoice->discount_type == 2 ? 'percentage' : 'amount',
            'shipping_cost' => $originalInvoice->shipping_cost,
            'tax_id' => $originalInvoice->tax_type,
            'payment_method' => $originalInvoice->payment_method,
            'reference_id' => $originalInvoice->id,
            'reference_code' => $originalInvoice->code,
            // خزينة الاسترداد: خزينة الفاتورة الأصلية إن عُرِفت، وإلا خزينة
            // الموظّف أو الخزينة الرئيسية من «الحسابات المحاسبية»
            'treasury_id' => $treasury_id ?? ($MainTreasury->id ?? null),
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
                ['label' => $originalInvoice->code, 'url' => route('invoicePurchases.show', $originalInvoice->id)],
                ['label' => __('purchase_orders.breadcrumb.create')],
            ],
        ])

        @include('purchases::partials.doc-context', ['code' => $code])

        @include('purchases::partials.period-block')

        {{-- لوحة إرشاد الشاشة — مطويّةً افتراضياً --}}
        @include('purchases::components.guide', [
            'key' => 'purchase-returns.create',
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
                __('purchase_cycle.guide.form.blocked.title') => [
                    __('purchase_cycle.guide.form.blocked.a'),
                    __('purchase_cycle.guide.form.blocked.b'),
                ],
            ],
            'facts' => [
                __('purchase_cycle.guide.facts.stock'),
                __('purchase_cycle.guide.facts.ledger'),
            ],
        ])


        @include('purchases::partials.form', [
            'action' => route('ReturnsInvoice.store'),
            'isEdit' => false,
            'backUrl' => route('invoicePurchases.show', $originalInvoice->id),
            'code' => $code,
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
                'branch' => true,
            ],
        ])
    </div>
@endsection
