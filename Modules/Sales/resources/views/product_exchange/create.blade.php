@extends('sales::master')

{{-- إنشاء عملية تبديل.

     أُعيد بناء الشاشة على نظام تصميم المبيعات (`sl-`) — نفسِه الذي تُبنى به
     شاشةُ التعديل، والحقولُ قالبٌ واحدٌ تشتركان فيه
     (`partials.form`). كانت على بطاقات القالب القديمة: ترويسةٌ بلا مسار،
     وصندوقان أخضرُ وأحمرُ متقطّعا الحدود بألوانٍ مكتوبةٍ بالسداسي لا تتبع
     «مظهر النظام»، وثلاثةُ تنبيهاتٍ ملوّنة فوق حقلَين.

     الحفظُ Ajax كما كان: `store()` يردّ JSON — نجاحاً برابط العرض، أو
     رفضاً بنصّه (حدٌّ يوميّ، نقصُ مخزون، فترةٌ مقفلة). --}}

@section('title', __('sales_exchanges.new'))

@section('content')
    @include('sales::partials.settings-theme')
    @include('components.hint-tips')

    @php
        // قالبُ الصفّ الجديد يُصاغ هنا لا داخل `@json(view(...))`: مُحلِّل
        // وسائط Blade يقطع الوسيط عند أوّل فاصلةٍ في مصفوفةٍ متعدّدة الأسطر
        $itemTemplate = view('sales::product_exchange.partials.item-row', [
            'index' => '__I__',
            'item' => null,
            'products' => $products,
            'reasons' => $reasons,
        ])->render();
    @endphp

    <div class="content-body sl-scope">

        @section('page-actions')
            <button type="submit" form="exchangeForm" class="btn btn-primary">
                <i class="feather icon-save"></i> {{ __('sales_settings.ui.save') }}
            </button>

            <a href="{{ route('product-exchanges.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-arrow-{{ ($languageIsRtl ?? true) ? 'right' : 'left' }}"></i>
                {{ __('sales_settings.ui.back') }}
            </a>
        @endsection

        @include('sales::components.page-head', [
            'title' => __('sales_exchanges.new'),
            'icon' => 'repeat',
            'sub' => __('sales_exchanges.subtitle'),
            'crumbs' => [
                __('sales_settings.ui.home') => route('dashboard'),
                __('sales_exchanges.title') => route('product-exchanges.index'),
                __('sales_exchanges.new') => null,
            ],
        ])

        @include('layouts.alerts.success')
        @include('layouts.alerts.error')
        @include('sales::components.form-errors')

        {{-- ══════════ إرشادُ الشاشة ══════════ --}}
        @include('sales::components.guide', [
            'key' => 'sales-exchanges.create',
            'open' => false,
            'title' => __('sales_exchanges.guide.create.title'),
            'lead' => __('sales_exchanges.guide.lead_create'),
            'blocks' => [
                __('sales_exchanges.guide.what_is.title') => [
                    __('sales_exchanges.guide.what_is.a'),
                    __('sales_exchanges.guide.what_is.b'),
                    __('sales_exchanges.guide.what_is.c'),
                ],
                __('sales_exchanges.guide.limits.title') => [
                    __('sales_exchanges.guide.limits.a'),
                    __('sales_exchanges.guide.limits.b'),
                    __('sales_exchanges.guide.limits.c'),
                ],
                __('sales_exchanges.guide.effects.title') => [
                    __('sales_exchanges.guide.effects.a'),
                    __('sales_exchanges.guide.effects.b'),
                ],
                __('sales_exchanges.guide.settings.title') => [
                    __('sales_exchanges.guide.settings.a'),
                    __('sales_exchanges.guide.settings.b'),
                ],
            ],
        ])

        <form id="exchangeForm" method="POST"
              action="{{ route('product-exchanges.store') }}"
              enctype="multipart/form-data">
            @csrf

            @include('sales::product_exchange.partials.form', ['exchange' => null])

            <div class="sl-form-foot">
                <a href="{{ route('product-exchanges.index') }}" class="btn btn-outline-secondary">
                    {{ __('sales_settings.ui.cancel') }}
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="feather icon-save"></i> {{ __('sales_settings.ui.save') }}
                </button>
            </div>
        </form>
    </div>
@endsection

@section('scripts')
    @include('sales::product_exchange.partials.form-scripts', [
        'itemTemplate' => $itemTemplate ?? '',
        'ajax' => true,
    ])
@endsection
