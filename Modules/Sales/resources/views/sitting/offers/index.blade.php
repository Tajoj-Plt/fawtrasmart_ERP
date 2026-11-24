@extends('sales::master')

{{-- عروض الحسم.

     كانت الشاشة تمدّد `master` (قالب الجذر) لا `sales::master`، وتعرض
     تبويبين: «الهدايا» و«العروض» — أي أنّها تُكرّر شاشة عروض الهدايا
     كاملةً بجدولها وأزرارها. جدولان لقائمتين لكلٍّ منهما شاشتُها يعني
     أربعة مواضع تُصان لأجل قائمتين، وتناقضاً محقّقاً: زرّ «إيقاف» هنا
     كان `confirm()` وهناك `Swal`.

     الآن: هذه الشاشة لعروض الحسم وحدها، وبطاقةٌ في رأسها تشير إلى شاشة
     الهدايا لمن جاء يطلبها. --}}

@section('title', __('sales_settings.offers.title'))

@section('content')
    @include('sales::partials.settings-theme')
    @include('components.hint-tips')

    @php
        $hasFilters = request()->hasAny(['search', 'status']);
        $today = now()->toDateString();
    @endphp

    <div class="content-body sl-scope">

        @section('page-actions')
            @permission('sales.offers.create')
                <a href="{{ route('offers.create') }}" class="btn btn-primary">
                    <i class="feather icon-plus"></i> {{ __('sales_settings.offers.new') }}
                </a>
            @endpermission

            @permission('sales.gift_offers.view')
                <a href="{{ route('gift-offers.index') }}" class="btn btn-outline-primary">
                    <i class="feather icon-gift"></i> {{ __('sales_settings.gift_offers.title') }}
                </a>
            @endpermission

            <a href="{{ route('sittingInvoice.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-arrow-{{ ($languageIsRtl ?? true) ? 'right' : 'left' }}"></i>
                {{ __('sales_settings.ui.back') }}
            </a>
        @endsection

        @include('sales::components.page-head', [
            'title' => __('sales_settings.offers.title'),
            'icon' => 'percent',
            'sub' => __('sales_settings.offers.subtitle'),
            'crumbs' => [
                __('sales_settings.ui.home') => route('dashboard'),
                __('sales_settings.hub.title') => route('sittingInvoice.index'),
                __('sales_settings.offers.title') => null,
            ],
        ])

        @include('layouts.alerts.success')
        @include('layouts.alerts.error')
        @include('sales::components.form-errors')

        {{-- ══════════ إرشادُ الشاشة ══════════ --}}
        @include('sales::components.guide', [
            'key' => 'sales-offers.index',
            'open' => false,
            'title' => __('sales_settings.guide.offers.title'),
            'lead' => __('sales_settings.guide.offers.lead'),
            'blocks' => [
                __('sales_settings.guide.offers.fields.title') => [
                    __('sales_settings.guide.offers.fields.a'),
                    __('sales_settings.guide.offers.fields.b'),
                    __('sales_settings.guide.offers.fields.c'),
                    __('sales_settings.guide.offers.fields.d'),
                ],
                __('sales_settings.guide.offers.effects.title') => [
                    __('sales_settings.guide.offers.effects.a'),
                    __('sales_settings.guide.offers.effects.b'),
                    __('sales_settings.guide.offers.effects.c'),
                ],
            ],
        ])

        {{-- ══════════ الإحصاء ══════════ --}}
        <div class="sl-stats">
            <div class="sl-stat">
                <div class="sl-stat__icon"><i class="feather icon-percent"></i></div>
                <div class="sl-stat__body">
                    <p class="sl-stat__value sl-num">{{ \App\Support\CurrencySettings::number($stats['total'], 0) }}</p>
                    <p class="sl-stat__label">{{ __('sales_settings.offers.total') }}</p>
                </div>
            </div>

            <div class="sl-stat">
                <div class="sl-stat__icon sl-stat__icon--success"><i class="feather icon-check-circle"></i></div>
                <div class="sl-stat__body">
                    <p class="sl-stat__value sl-num">{{ \App\Support\CurrencySettings::number($stats['active'], 0) }}</p>
                    <p class="sl-stat__label">{{ __('sales_settings.ui.active') }}</p>
                </div>
            </div>

            <div class="sl-stat">
                <div class="sl-stat__icon sl-stat__icon--info"><i class="feather icon-play-circle"></i></div>
                <div class="sl-stat__body">
                    <p class="sl-stat__value sl-num">{{ \App\Support\CurrencySettings::number($stats['running'], 0) }}</p>
                    <p class="sl-stat__label">{{ __('sales_settings.offers.running') }}</p>
                </div>
            </div>
        </div>

        {{-- ══════════ البحث والتصفية ══════════ --}}
        <div class="card sl-filters">
            <div class="card-body">
                <div class="sl-filters__head">
                    <h6 class="sl-filters__title">
                        <i class="feather icon-filter"></i> {{ __('sales_settings.ui.filters') }}
                    </h6>

                    @if ($hasFilters)
                        <span class="sl-count">{{ __('sales_settings.ui.filters_active') }}</span>
                    @endif
                </div>

                <form method="GET" action="{{ route('offers.index') }}">
                    <div class="row">
                        <div class="col-md-8 col-sm-12">
                            <div class="form-group">
                                <label for="search">{{ __('sales_settings.ui.search') }}</label>
                                <input type="text" id="search" name="search" class="form-control"
                                       value="{{ request('search') }}"
                                       placeholder="{{ __('sales_settings.ui.search_placeholder') }}">
                            </div>
                        </div>

                        <div class="col-md-4 col-sm-12">
                            <div class="form-group">
                                <label for="status">{{ __('sales_settings.ui.status') }}</label>
                                <select id="status" name="status" class="form-control">
                                    <option value="">{{ __('sales_settings.ui.all_statuses') }}</option>
                                    <option value="active" @selected(request('status') === 'active')>
                                        {{ __('sales_settings.ui.active') }}
                                    </option>
                                    <option value="inactive" @selected(request('status') === 'inactive')>
                                        {{ __('sales_settings.ui.inactive') }}
                                    </option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="sl-filters__foot">
                        @if ($hasFilters)
                            <a href="{{ route('offers.index') }}" class="btn btn-outline-secondary">
                                <i class="feather icon-x"></i> {{ __('sales_settings.ui.filters_clear') }}
                            </a>
                        @endif

                        <button type="submit" class="btn btn-primary">
                            <i class="feather icon-search"></i> {{ __('sales_settings.ui.filters_apply') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- ══════════ الجدول ══════════ --}}
        <div class="card sl-table-card">
            <div class="sl-table-head">
                <h6 class="sl-table-head__title">
                    <i class="feather icon-percent"></i> {{ __('sales_settings.offers.title') }}
                </h6>
                <span class="sl-count sl-num">{{ \App\Support\CurrencySettings::number($offers->total(), 0) }}</span>
            </div>

            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('sales_settings.ui.name') }}</th>
                            <th class="text-center">{{ __('sales_settings.offers.type') }}</th>
                            <th class="text-center">{{ __('sales_settings.offers.discount') }}</th>
                            <th class="text-center">{{ __('sales_settings.offers.scope') }}</th>
                            <th class="text-center">{{ __('sales_settings.offers.period') }}</th>
                            <th class="text-center" style="width:120px">{{ __('sales_settings.ui.status') }}</th>
                            <th class="text-center" style="width:130px">{{ __('sales_settings.ui.actions') }}</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($offers as $offer)
                            @php
                                $from = $offer->valid_from?->toDateString();
                                $to = $offer->valid_to?->toDateString();
                                $expired = $to && $to < $today;
                                $pending = $from && $from > $today;
                            @endphp

                            <tr>
                                <td>
                                    <div class="sl-media">
                                        <span class="sl-media__thumb" aria-hidden="true"><i class="feather icon-percent"></i></span>
                                        <div class="sl-media__body">
                                            <span class="sl-media__title">{{ $offer->name ?: '#'.$offer->id }}</span>
                                            <span class="sl-media__sub">
                                                {{ $offer->clients_count > 0
                                                    ? __('sales_settings.offers.some_clients', ['count' => $offer->clients_count])
                                                    : __('sales_settings.offers.all_clients') }}
                                            </span>
                                        </div>
                                    </div>
                                </td>

                                <td class="text-center">
                                    <span class="badge badge-secondary">
                                        {{ __('sales_settings.offers.types.'.((int) $offer->type ?: 1)) }}
                                    </span>
                                </td>

                                {{-- المبلغ بعملة النظام والنسبة بعلامتها: كان الرقم
                                     يُعرَض عارياً فلا يُعرف أهو ريالٌ أم نسبة --}}
                                <td class="text-center sl-num">
                                    @if ((int) $offer->discount_type === 2)
                                        {{ \App\Support\CurrencySettings::number($offer->discount_value) }}%
                                    @else
                                        {{ \App\Support\CurrencySettings::format((float) $offer->discount_value) }}
                                    @endif
                                </td>

                                <td class="text-center">
                                    <span class="sl-state sl-state--neutral">
                                        {{ __('sales_settings.offers.unit_types.'.((int) $offer->unit_type ?: 1)) }}
                                    </span>
                                </td>

                                <td class="text-center">
                                    @if ($from || $to)
                                        <span class="sl-num" style="font-size:.8rem">
                                            {{ $from ? \App\Support\LanguageSettings::formatDate($offer->valid_from) : '…' }}
                                            —
                                            {{ $to ? \App\Support\LanguageSettings::formatDate($offer->valid_to) : '…' }}
                                        </span>
                                    @else
                                        <span class="sl-state sl-state--neutral">{{ __('sales_settings.offers.no_period') }}</span>
                                    @endif
                                </td>

                                <td class="text-center">
                                    @if (! $offer->is_active)
                                        <span class="sl-state sl-state--off">{{ __('sales_settings.ui.inactive') }}</span>
                                    @elseif ($expired)
                                        <span class="badge badge-warning">{{ __('sales_settings.offers.expired') }}</span>
                                    @elseif ($pending)
                                        <span class="badge badge-info">{{ __('sales_settings.offers.pending') }}</span>
                                    @else
                                        <span class="sl-state sl-state--on">{{ __('sales_settings.offers.running') }}</span>
                                    @endif
                                </td>

                                <td class="text-center">
                                    <div class="sl-actions">
                                        <a href="{{ route('offers.show', $offer->id) }}"
                                           class="btn btn-outline-secondary" title="{{ __('sales_settings.ui.view') }}"
                                           aria-label="{{ __('sales_settings.ui.view') }}">
                                            <i class="feather icon-eye"></i>
                                        </a>

                                        @permission('sales.offers.edit')
                                            <a href="{{ route('offers.edit', $offer->id) }}"
                                               class="btn btn-outline-primary" title="{{ __('sales_settings.ui.edit') }}"
                                               aria-label="{{ __('sales_settings.ui.edit') }}">
                                                <i class="feather icon-edit-2"></i>
                                            </a>
                                        @endpermission

                                        @permission('sales.offers.delete')
                                            <form action="{{ route('offers.destroy', $offer->id) }}"
                                                  method="POST" class="sl-delete-form d-inline"
                                                  data-name="{{ $offer->name ?: '#'.$offer->id }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger"
                                                        title="{{ __('sales_settings.ui.delete') }}"
                                                        aria-label="{{ __('sales_settings.ui.delete') }}">
                                                    <i class="feather icon-trash-2"></i>
                                                </button>
                                            </form>
                                        @endpermission
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">
                                    @include('sales::components.empty-state', [
                                        'icon' => 'percent',
                                        'title' => $hasFilters
                                            ? __('sales_settings.ui.no_results')
                                            : __('sales_settings.offers.empty'),
                                        'hint' => $hasFilters
                                            ? __('sales_settings.ui.no_results_hint')
                                            : __('sales_settings.offers.empty_hint'),
                                        'action' => $hasFilters
                                            ? '<a href="'.route('offers.index').'" class="btn btn-outline-secondary">'
                                                .'<i class="feather icon-x"></i> '.e(__('sales_settings.ui.filters_clear')).'</a>'
                                            : (hasPermission('sales.offers.create')
                                                ? '<a href="'.route('offers.create').'" class="btn btn-primary">'
                                                    .'<i class="feather icon-plus"></i> '.e(__('sales_settings.offers.new')).'</a>'
                                                : ''),
                                    ])
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @include('sales::components.table-foot', [
                'paginator' => $offers,
                'sizes' => $pageSizes,
            ])
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        $(function () {
            $('.sl-delete-form').on('submit', function (e) {
                e.preventDefault();
                var form = $(this);

                Swal.fire({
                    title: @json(__('sales_settings.ui.confirm_delete_title')),
                    text: @json(__('sales_settings.offers.confirm_delete')).replace(':name', form.data('name')),
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: @json(__('sales_settings.ui.confirm_yes')),
                    cancelButtonText: @json(__('sales_settings.ui.cancel')),
                    reverseButtons: true
                }).then(function (result) {
                    if (result.isConfirmed) {
                        form.off('submit').submit();
                    }
                });
            });
        });
    </script>
@endsection
