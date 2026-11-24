{{--
    إنشاء طلب شراء.

    أُعيد بناء الشاشة على نموذج دورة المشتريات المشترك (`partials/form`) —
    نفسه الذي تبني به «أمر الشراء» و«فاتورة الشراء» — بدل نسخةٍ خاصّة بها
    وحدها. طلب الشراء بلا أسعار عمداً: هو طلبُ أصنافٍ وكمّياتٍ للموافقة
    عليه، لا تسعيرة — `config.prices = false` تُخفي أعمدة السعر والخصم
    والضريبة من الجدول ولوحة الإجماليات معاً.

    ما يأتي من الإعدادات:
      • الرقم    ← «تسلسل أرقام المستندات» (قسم purchase-order)
      • الحالة اليدوية ← «حالة يدوية لطلبات الشراء» (عند الإنشاء وحده،
        فـ`update()` لا يمسّ عمود الحالة إطلاقاً)
--}}
@extends('purchases::master')

@section('title')
    {{ __('purchase_orders.titles.create') }}
@stop

@section('css')
    @include('purchases::partials.style')
    @include('purchases::partials.doc-theme-sl')
    @include('components.hint-tips')
@endsection

@section('content')
    @php
        $rows = old('items', []);
        $products = $products ?? collect();
        $taxes = $taxes ?? collect();

        $defaults = [
            'order_date' => date('Y-m-d'),
        ];
    @endphp

    <div class="po-page po-doc--sl">
        @include('purchases::partials.head', [
            'crumbStyle' => 'breadcrumb',
            'icon' => 'clipboard',
            'sub' => 'طلبٌ داخليّ يسبق أمر الشراء',
            'title' => __('purchase_orders.titles.create'),
            'crumbs' => [
                ['label' => __('purchase_orders.breadcrumb.index'), 'url' => route('PurchaseRequests.index')],
                ['label' => __('purchase_orders.breadcrumb.create')],
            ],
        ])

        @include('purchases::partials.doc-context', ['code' => $code])

        {{-- لوحة إرشاد الشاشة — مفتاحها يخصّ الإنشاء وحده: من طوى الشرح هنا
             بعد أن أتقنه قد يظلّ يحتاجه في التعديل، وأبواب اللوحتين مختلفة --}}
        @include('purchases::components.guide', [
            'key' => 'purchase-orders.create',
            'title' => __('purchase_orders.guide.form.title'),
            'lead' => __('purchase_orders.guide.what_is'),
            'open' => false,
            'blocks' => [
                __('purchase_orders.guide.cycle.title') => [
                    __('purchase_orders.guide.cycle.a'),
                    __('purchase_orders.guide.cycle.b'),
                    __('purchase_orders.guide.cycle.c'),
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
                __('purchase_orders.guide.form.noedit.title') => [
                    __('purchase_orders.guide.form.noedit.a'),
                    __('purchase_orders.guide.form.noedit.b'),
                ],
            ],
            'facts' => [
                __('purchase_orders.guide.facts.no_stock'),
                __('purchase_orders.guide.facts.no_ledger'),
                __('purchase_orders.guide.facts.final'),
            ],
        ])

        @include('purchases::partials.form', [
            'action' => route('PurchaseRequests.store'),
            'isEdit' => false,
            'backUrl' => route('PurchaseRequests.index'),
            'code' => $code,
            'documentLabel' => __('purchase_orders.titles.create'),
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
                'manual_status_setting' => 'manual_purchase_orders',
            ],
        ])
    </div>
@endsection
