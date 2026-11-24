@extends('sales::master')

{{-- تفاصيل عرض الحسم.

     أربعة أعطاب كانت هنا:

       • `{{ $offer->title }}` في عنوان الصفحة — لا عمود بهذا الاسم في
         `offers`، فالعنوان يخرج فارغاً.
       • نداءُ المسار «Offers.edit» بحرف كبير — واسمه `offers.edit`،
         فزرّ التعديل يرمي `RouteNotFoundException` قبل أن تُعرَض الصفحة.
       • `$offer->status` في ثلاثة مواضع — والشاشات الأخرى تكتب في
         `is_active`، فالحالة تظهر «غير نشط» أبداً.
       • زرّ حذف يفتح `#modal_DELETE1` ولا وجود لهذا المُشْرِف في الصفحة،
         وتبويب «سجل النشاطات» بلا محتوى.

     والمبالغ كانت `number_format(…) . ' ريال'` — عملةٌ مكتوبة في القالب
     مهما ضُبِط في «العملات». --}}

@section('title', __('sales_settings.offers.show_title'))

@section('content')
    @include('sales::partials.settings-theme')
    @include('components.hint-tips')

    @php
        $today = now()->startOfDay();
        $from = $offer->valid_from;
        $to = $offer->valid_to;

        $expired = $to && $to->lt($today);
        $pending = $from && $from->gt($today);
    @endphp

    <div class="content-body sl-scope">

        @section('page-actions')
            <a href="{{ route('offers.edit', $offer->id) }}" class="btn btn-primary">
                <i class="feather icon-edit-2"></i> {{ __('sales_settings.ui.edit') }}
            </a>

            <a href="{{ route('offers.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-arrow-{{ ($languageIsRtl ?? true) ? 'right' : 'left' }}"></i>
                {{ __('sales_settings.ui.back') }}
            </a>
        @endsection

        @include('sales::components.page-head', [
            'title' => $offer->name ?: '#'.$offer->id,
            'icon' => 'percent',
            'sub' => __('sales_settings.offers.show_title'),
            'crumbs' => [
                __('sales_settings.ui.home') => route('dashboard'),
                __('sales_settings.hub.title') => route('sittingInvoice.index'),
                __('sales_settings.offers.title') => route('offers.index'),
                ($offer->name ?: '#'.$offer->id) => null,
            ],
        ])

        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        {{-- ══════════ إرشادُ الشاشة ══════════ --}}
        @include('sales::components.guide', [
            'key' => 'sales-offers.show',
            'title' => __('sales_settings.guide.offers.title'),
            'lead' => __('sales_settings.guide.offers.lead'),
            'open' => false,
            'blocks' => [
                __('sales_settings.guide.offers.effects.title') => [
                    __('sales_settings.guide.offers.effects.a'),
                    __('sales_settings.guide.offers.effects.b'),
                    __('sales_settings.guide.offers.effects.c'),
                ],
            ],
        ])

        {{-- ══════════ الخلاصة ══════════ --}}
        <div class="sl-stats">
            <div class="sl-stat">
                <div class="sl-stat__icon"><i class="feather icon-percent"></i></div>
                <div class="sl-stat__body">
                    <p class="sl-stat__value sl-num" style="font-size:1.25rem">
                        @if ((int) $offer->discount_type === 2)
                            {{ \App\Support\CurrencySettings::number($offer->discount_value) }}%
                        @else
                            {{ \App\Support\CurrencySettings::format((float) $offer->discount_value) }}
                        @endif
                    </p>
                    <p class="sl-stat__label">
                        {{ __('sales_settings.offers.discount') }} —
                        {{ __('sales_settings.offers.discount_types.'.((int) $offer->discount_type ?: 1)) }}
                    </p>
                </div>
            </div>

            <div class="sl-stat">
                <div class="sl-stat__icon sl-stat__icon--info"><i class="feather icon-layers"></i></div>
                <div class="sl-stat__body">
                    <p class="sl-stat__value sl-num">{{ \App\Support\CurrencySettings::number($offer->quantity, 0) }}</p>
                    <p class="sl-stat__label">{{ __('sales_settings.offers.quantity') }}</p>
                </div>
            </div>

            <div class="sl-stat">
                <div class="sl-stat__icon sl-stat__icon--{{ $offer->is_active && ! $expired && ! $pending ? 'success' : 'warning' }}">
                    <i class="feather icon-power"></i>
                </div>
                <div class="sl-stat__body">
                    <p class="sl-stat__value" style="font-size:1.05rem">
                        @if (! $offer->is_active)
                            {{ __('sales_settings.ui.inactive') }}
                        @elseif ($expired)
                            {{ __('sales_settings.offers.expired') }}
                        @elseif ($pending)
                            {{ __('sales_settings.offers.pending') }}
                        @else
                            {{ __('sales_settings.offers.running') }}
                        @endif
                    </p>
                    <p class="sl-stat__label">{{ __('sales_settings.ui.status') }}</p>
                </div>
            </div>
        </div>

        <div class="row">
            {{-- ══════════ معلومات العرض ══════════ --}}
            <div class="col-lg-6 col-12">
                <div class="card sl-table-card">
                    <div class="sl-table-head">
                        <h6 class="sl-table-head__title">
                            <i class="feather icon-info"></i> {{ __('sales_settings.offers.details') }}
                        </h6>
                    </div>

                    <div class="table-responsive">
                        <table class="table mb-0">
                            <tbody>
                                <tr>
                                    <td style="width:45%">{{ __('sales_settings.ui.name') }}</td>
                                    <td><span class="sl-media__title">{{ $offer->name ?: '#'.$offer->id }}</span></td>
                                </tr>
                                <tr>
                                    <td>{{ __('sales_settings.offers.type') }}</td>
                                    <td>{{ __('sales_settings.offers.types.'.((int) $offer->type ?: 1)) }}</td>
                                </tr>
                                <tr>
                                    <td>{{ __('sales_settings.offers.valid_from') }}</td>
                                    <td class="sl-num">
                                        {{ $from ? \App\Support\LanguageSettings::formatDate($from) : __('sales_settings.ui.none') }}
                                    </td>
                                </tr>
                                <tr>
                                    <td>{{ __('sales_settings.offers.valid_to') }}</td>
                                    <td class="sl-num">
                                        {{ $to ? \App\Support\LanguageSettings::formatDate($to) : __('sales_settings.ui.none') }}
                                    </td>
                                </tr>
                                <tr>
                                    <td>{{ __('sales_settings.ui.created_at') }}</td>
                                    <td class="sl-num">
                                        {{ $offer->created_at
                                            ? \App\Support\LanguageSettings::formatDate($offer->created_at, true)
                                            : __('sales_settings.ui.none') }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- ══════════ النطاق ══════════ --}}
            <div class="col-lg-6 col-12">
                <div class="card sl-table-card">
                    <div class="sl-table-head">
                        <h6 class="sl-table-head__title">
                            <i class="feather icon-package"></i> {{ __('sales_settings.offers.scope') }}
                        </h6>
                    </div>

                    <div class="table-responsive">
                        <table class="table mb-0">
                            <tbody>
                                <tr>
                                    <td style="width:45%">{{ __('sales_settings.offers.unit_type') }}</td>
                                    <td>{{ __('sales_settings.offers.unit_types.'.((int) $offer->unit_type ?: 1)) }}</td>
                                </tr>

                                @if ((int) $offer->unit_type === 2)
                                    <tr>
                                        <td>{{ __('sales_settings.offers.categories') }}</td>
                                        <td>
                                            <div class="sl-tags">
                                                @forelse ($offer->categories as $category)
                                                    <span class="badge badge-primary">{{ $category->name }}</span>
                                                @empty
                                                    <span class="sl-media__sub">{{ __('sales_settings.ui.none') }}</span>
                                                @endforelse
                                            </div>
                                        </td>
                                    </tr>
                                @endif

                                @if ((int) $offer->unit_type === 3)
                                    <tr>
                                        <td>{{ __('sales_settings.offers.products') }}</td>
                                        <td>
                                            <div class="sl-tags">
                                                @forelse ($offer->products as $product)
                                                    <span class="badge badge-info">{{ $product->name }}</span>
                                                @empty
                                                    <span class="sl-media__sub">{{ __('sales_settings.ui.none') }}</span>
                                                @endforelse
                                            </div>
                                        </td>
                                    </tr>
                                @endif

                                <tr>
                                    <td>{{ __('sales_settings.offers.clients') }}</td>
                                    <td>
                                        <div class="sl-tags">
                                            @forelse ($offer->clients as $client)
                                                <span class="badge badge-secondary">{{ $client->trade_name ?: $client->name }}</span>
                                            @empty
                                                {{-- بلا عميلٍ مربوط يعني «لكل العملاء» لا «لا أحد» --}}
                                                <span class="sl-state sl-state--neutral">
                                                    {{ __('sales_settings.offers.all_clients') }}
                                                </span>
                                            @endforelse
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
