@extends('sales::master')

{{-- تعديل عملية تبديل — **شاشة لم تكن موجودة**.

     لم يكن للتبديل تعديلٌ إطلاقاً: لا مسار، ولا تابع في المتحكّم، ولا
     قالب. فمن أخطأ في صنفٍ أو كمية أو مستودع لا سبيل له إلّا حذفُ العملية
     كلِّها وإعادتُها برقمٍ جديد — فينقطع تسلسل الأرقام، ويضيع أثرُ ما جرى.

     الحفظ هنا يعكس أثرَ العملية القديم — كميّاتِها وإذنيها وقيديها — ثمّ
     يعيد تطبيقه بالبيانات الجديدة داخل معاملةٍ واحدة. ولذلك تُفحص
     الفترتان المحاسبيّتان معاً: فترةُ التاريخ الأصلي وفترةُ الجديد.

     النموذج إرسالٌ عادي لا Ajax — على عكس شاشة الإنشاء: `update()` يردّ
     تحويلاً برسالة، وهو ما تفهمه الشاشة بلا سطر JavaScript يفكّ استجابةً
     ويعرضها بيده. --}}

@section('title', __('sales_exchanges.edit') . ' — ' . $exchange->code)

@section('content')
    @include('sales::partials.settings-theme')
    @include('components.hint-tips')

    @php
        // قالبُ الصفّ الجديد يُصاغ هنا لا داخل `@json(view(...))`: مُحلِّل
        // وسائط Blade يقطع الوسيط عند أوّل فاصلةٍ في مصفوفةٍ متعدّدة
        // الأسطر، فيخرج قوسٌ لا يُغلق وصفحةُ خطأ في التصريف
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

            <a href="{{ route('product-exchanges.show', $exchange->id) }}" class="btn btn-outline-secondary">
                <i class="feather icon-arrow-{{ ($languageIsRtl ?? true) ? 'right' : 'left' }}"></i>
                {{ __('sales_settings.ui.back') }}
            </a>
        @endsection

        @include('sales::components.page-head', [
            'title' => __('sales_exchanges.edit') . ' — ' . $exchange->code,
            'icon' => 'repeat',
            'sub' => __('sales_exchanges.subtitle'),
            'crumbs' => [
                __('sales_settings.ui.home') => route('dashboard'),
                __('sales_exchanges.title') => route('product-exchanges.index'),
                $exchange->code => route('product-exchanges.show', $exchange->id),
                __('sales_settings.ui.edit') => null,
            ],
        ])

        @include('layouts.alerts.success')
        @include('layouts.alerts.error')
        @include('sales::components.form-errors')

        {{-- ══════════ إرشادُ الشاشة ══════════ --}}
        @include('sales::components.guide', [
            'key' => 'sales-exchanges.edit',
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
            ],
        ])

        {{-- تحذيرٌ صريح: التعديل عمليةٌ محاسبية لا تحريرُ صفّ --}}
        <div class="alert alert-warning">
            <i class="feather icon-alert-triangle"></i> {{ __('sales_exchanges.messages.edit_warning') }}
        </div>

        @include('sales::components.system-context', [
            'only' => ['currency', 'accounts', 'period'],
        ])

        <form id="exchangeForm" method="POST"
              action="{{ route('product-exchanges.update', $exchange->id) }}"
              enctype="multipart/form-data">
            @csrf
            @method('PUT')

            {{-- الحقولُ قالبٌ واحدٌ تشترك فيه مع شاشة الإنشاء --}}
            @include('sales::product_exchange.partials.form', ['exchange' => $exchange])

            <div class="sl-form-foot">
                <a href="{{ route('product-exchanges.show', $exchange->id) }}" class="btn btn-outline-secondary">
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
    {{-- إرسالٌ عادي لا Ajax: `update()` يردّ تحويلاً برسالة --}}
    @include('sales::product_exchange.partials.form-scripts', [
        'itemTemplate' => $itemTemplate ?? '',
        'ajax' => false,
    ])
@endsection
