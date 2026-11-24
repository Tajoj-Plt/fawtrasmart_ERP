@extends('sales::master')

{{-- إنشاء عرض سعر.

     الشاشة كلّها في `partials/form` — نفس القالب الذي تُبنى به شاشة
     التعديل، فلا يتباعد النموذجان مع أوّل حقلٍ يُضاف.

     ما كان هنا: ثلاثمئة وخمسون سطراً من الوسم، ونسخةٌ ثانية منها في
     `edit.blade.php` تختلف عنها في تسعة حقول ومنطق حساب. --}}

@section('title', $doc['t']('doc.create_title'))

@section('content')
    {{-- من أجل `.sl-guide` و`.sl-help`: `document-theme` لا تعرّفهما،
         وكلُّ قواعد `settings-theme` `.sl-`ـيّة ولا تتقاطع معها --}}
    @include('sales::partials.settings-theme')
    @include('components.hint-tips')
    @include('sales::partials.document-theme')
    @include('sales::partials.document-theme-po')

    <div class="content-body sl-scope sl-doc sl-doc--po">

        @section('page-actions')
            <a href="{{ route($doc['routes']['index']) }}" class="btn btn-outline-secondary">
                <i class="feather icon-arrow-{{ ($languageIsRtl ?? \App\Support\LanguageSettings::isRtl()) ? 'right' : 'left' }}"></i>
                {{ $doc['t']('ui.back') }}
            </a>
        @endsection

        @include('sales::components.page-head', [
            'title' => $doc['t']('doc.create_title'),
            'icon' => 'file-plus',
            'sub' => $doc['t']('doc.subtitle'),
            'crumbs' => [
                $doc['t']('ui.home') => route('dashboard'),
                $doc['t']('doc.title') => route($doc['routes']['index']),
                $doc['t']('ui.add') => null,
            ],
        ])

        @include('layouts.alerts.success')

        {{-- ══════════ إرشادُ الشاشة ══════════ --}}
@include('sales::components.guide', [
            'key' => 'sales-quotes.create',
            'title' => $doc['t']('guide.create.title'),
            'lead' => $doc['t']('guide.lead_create'),
            'open' => false,
            'blocks' => [
                $doc['t']('guide.conversion.title') => [
                    $doc['t']('guide.conversion.a'),
                    $doc['t']('guide.conversion.b'),
                    $doc['t']('guide.conversion.c'),
                    $doc['t']('guide.conversion.d'),
                ],
                $doc['t']('guide.money_flow.title') => [
                    $doc['t']('guide.money_flow.a'),
                    $doc['t']('guide.money_flow.b'),
                    $doc['t']('guide.money_flow.c'),
                ],
                $doc['t']('guide.statuses.title') => [
                    $doc['t']('guide.statuses.a'),
                    $doc['t']('guide.statuses.b'),
                    $doc['t']('guide.statuses.c'),
                ],
                $doc['t']('guide.what_is.title') => [
                    $doc['t']('guide.what_is.a'),
                    $doc['t']('guide.what_is.b'),
                    $doc['t']('guide.what_is.c'),
                ],
                $doc['t']('guide.terms.title') => [
                    $doc['t']('guide.terms.a'),
                    $doc['t']('guide.terms.b'),
                    $doc['t']('guide.terms.c'),
                    $doc['t']('guide.terms.d'),
                ],
                $doc['t']('guide.noedit.title') => [
                    $doc['t']('guide.noedit.a'),
                    $doc['t']('guide.noedit.b'),
                ],
            ],
            'facts' => [
                $doc['t']('guide.facts.a'),
                $doc['t']('guide.facts.b'),
                $doc['t']('guide.facts.c'),
            ],
        ])

        @include('sales::qoution.partials.form', [
            'formAction' => route($doc['routes']['store']),
            'isEdit' => false,
            // «تاريخ التسليم» أُزيل من أمر البيع بطلب المستخدم؛ عرضُ السعر يُبقي «صالح حتى»
            'config' => ['second_tax' => false, 'expiry_date' => ! $doc['is_sales_order']],
            'backUrl' => route($doc['routes']['index']),
            'docNumber' => $quoteNumber,
            'docLabel' => $doc['t']('doc.create_title'),
            'clients' => $clients,
            'products' => $products,
            'taxes' => $taxes,
            'rows' => $rows,
            'defaults' => $defaults,
            'periodBlock' => $periodBlock,
            'invoiceSettings' => $invoiceSettings,
            'manualStatuses' => $manualStatuses,
            'currentStatus' => null,
        ])
    </div>
@endsection
