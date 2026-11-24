{{--
    إنشاء فاتورة مشتريات.

    أُعيد بناء الشاشة على نموذج دورة المشتريات المشترك (`partials/form`) —
    نفسه الذي تبني به شاشة «أمر الشراء» — بدل ألف وسبعمئة سطر مكتوبة لها
    وحدها. فصارت الشاشتان واحدةً بنيةً وشكلاً وسلوكاً: الترتيب نفسه
    (بيانات المستند ← البنود ← التفاصيل الإضافية ← الملاحظات وأزرار الحفظ)،
    وجدول البنود نفسه، وحساب الإجماليات نفسه.

    ما تنفرد به الفاتورة يأتي من مفاتيح `config`:
      • `terms`       مهلة السداد بالأيام
      • `second_tax`  ضريبة ثانية لكل بند
      • `payment`     تبويب الدفع: الدفعة المقدَّمة وخزينتها
      • `receiving`   تبويب التسليم: حالة الاستلام وتاريخه

    وما يأتي من الإعدادات:
      • الرقم         ← «تسلسل أرقام المستندات» (قسم purchase-invoice)
      • نِسَب الضريبة  ← «إعدادات الضريبة»
      • العملة والكسور ← «العملات»
      • صيغة التاريخ  ← إعدادات اللغة النشطة
      • الألوان والخط ← «مظهر النظام» عبر متغيّرات CSS
--}}
@extends('purchases::master')

@section('title')
    {{ __('purchase_cycle.documents.purchase_invoice') }}
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
            'date' => old('date', date('Y-m-d')),
            'supplier_id' => $selectedSupplierId ?? null,
            'treasury_id' => $treasury_id ?? ($MainTreasury->id ?? null),
            // المستودع المختار مبدئياً — نفسُ ترتيب حسم الخادم
            'store_house_id' => $defaultWarehouseId ?? null,
        ];
    @endphp

    <div class="po-page po-doc--sl">
        @include('purchases::partials.head', [
            'crumbStyle' => 'breadcrumb',
            'icon' => 'file-text',
            'sub' => 'تُرحَّل إلى ح/ المشتريات وذمم المورد عند الحفظ',
            'title' => __('purchase_cycle.documents.purchase_invoice'),
            'crumbs' => [
                ['label' => __('purchase_cycle.sections.purchase_invoice'), 'url' => route('invoicePurchases.index')],
                ['label' => __('purchase_orders.breadcrumb.create')],
            ],
        ])

        @include('purchases::partials.doc-context', ['code' => $code])

        @include('purchases::partials.period-block')

        {{-- لوحة إرشاد الشاشة — مفتاحها يخصّ الإنشاء وحده: من طوى الشرح هنا
             بعد أن أتقنه قد يظلّ يحتاجه في التعديل، وأبواب اللوحتين مختلفة --}}
        @include('purchases::components.guide', [
            'key' => 'purchase-invoices.create',
            'title' => __('purchase_cycle.guide.form.title'),
            'lead' => __('purchase_cycle.guide.what_is'),
            'open' => false,
            'blocks' => [
                __('purchase_cycle.guide.form.save.title') => [
                    __('purchase_cycle.guide.form.save.a'),
                    __('purchase_cycle.guide.form.save.b'),
                    __('purchase_cycle.guide.form.save.c'),
                    __('purchase_cycle.guide.form.save.d'),
                ],
                __('purchase_cycle.guide.money_flow.title') => [
                    __('purchase_cycle.guide.money_flow.a'),
                    __('purchase_cycle.guide.money_flow.b'),
                    __('purchase_cycle.guide.money_flow.c'),
                    __('purchase_cycle.guide.money_flow.d'),
                ],
                __('purchase_cycle.guide.statuses.title') => [
                    __('purchase_cycle.guide.statuses.a'),
                    __('purchase_cycle.guide.statuses.b'),
                    __('purchase_cycle.guide.statuses.c'),
                ],
                __('purchase_cycle.guide.form.blocked.title') => [
                    __('purchase_cycle.guide.form.blocked.a'),
                    __('purchase_cycle.guide.form.blocked.b'),
                    __('purchase_cycle.guide.form.blocked.c'),
                ],
                __('purchase_cycle.guide.form.noedit.title') => [
                    __('purchase_cycle.guide.form.noedit.a'),
                    __('purchase_cycle.guide.form.noedit.b'),
                ],
            ],
            'facts' => [
                __('purchase_cycle.guide.facts.stock'),
                __('purchase_cycle.guide.facts.stockless'),
                __('purchase_cycle.guide.facts.ledger'),
                __('purchase_cycle.guide.facts.supplier'),
            ],
        ])

        @include('purchases::partials.form', [
            'action' => route('invoicePurchases.store'),
            'isEdit' => false,
            'backUrl' => route('invoicePurchases.index'),
            'code' => $code,
            'documentLabel' => __('purchase_cycle.documents.purchase_invoice'),
            'products' => $products,
            'suppliers' => $suppliers ?? collect(),
            'taxes' => $taxes,
            'treasuries' => $treasuries ?? collect(),
            'warehouses' => $warehouses ?? collect(),
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
                'second_tax' => false,
                'payment' => true,
                'payment_default_setting' => 'default_paid_invoices',
                'invoice_status_setting' => 'manual_invoice_status',
                'receiving' => true,
                'receiving_default_setting' => 'default_received_invoices',
                'warehouse' => true,
                'refund' => false,
                'branch' => true,
            ],
        ])
    </div>
@endsection
