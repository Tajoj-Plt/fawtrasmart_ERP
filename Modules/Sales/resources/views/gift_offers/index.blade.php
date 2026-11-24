@extends('sales::master')

{{-- عروض الهدايا — «اشترِ كذا فتأخذ كذا».

     كان القالب يمدّد `master` (قالب الجذر) لا `sales::master`، فيخرج بلا
     شريط المبيعات الجانبي وبلا ترويستها. وكان نصّه كلّه عربيّاً مكتوباً في
     الوسم لا مفاتيحَ ترجمة، وجدولُه `table-bordered` عارياً بلا ترقيم ولا
     بحث ولا حالة فارغة، وزرّ «حذف» فيه `confirm('هل أنت متأكد؟')` — وهو
     لا يحذف أصلاً بل يُعطّل.

     الآن: لغة «الأذون المخزنية»، ومفاتيح `sales_settings`، وزرّ التعطيل
     باسم فعله. --}}

@section('title', __('sales_settings.gift_offers.title'))

@section('content')
    @include('sales::partials.settings-theme')
    @include('components.hint-tips')

    @php
        $hasFilters = request()->hasAny(['search', 'status']);
        $today = now()->toDateString();
    @endphp

    <div class="content-body sl-scope">

        @section('page-actions')
            @permission('sales.gift_offers.create')
                <a href="{{ route('gift-offers.create') }}" class="btn btn-primary">
                    <i class="feather icon-plus"></i> {{ __('sales_settings.gift_offers.new') }}
                </a>
            @endpermission

            <a href="{{ route('sittingInvoice.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-arrow-{{ ($languageIsRtl ?? true) ? 'right' : 'left' }}"></i>
                {{ __('sales_settings.ui.back') }}
            </a>
        @endsection

        @include('sales::components.page-head', [
            'title' => __('sales_settings.gift_offers.title'),
            'icon' => 'gift',
            'sub' => __('sales_settings.gift_offers.subtitle'),
            'crumbs' => [
                __('sales_settings.ui.home') => route('dashboard'),
                __('sales_settings.hub.title') => route('sittingInvoice.index'),
                __('sales_settings.gift_offers.title') => null,
            ],
        ])

        @include('layouts.alerts.success')
        @include('layouts.alerts.error')
        @include('sales::components.form-errors')

        {{-- ══════════ إرشادُ الشاشة ══════════ --}}
        @include('sales::components.guide', [
            'key' => 'sales-gift-offers.index',
            'open' => false,
            'title' => __('sales_settings.guide.gift_offers.title'),
            'lead' => __('sales_settings.guide.gift_offers.lead'),
            'blocks' => [
                __('sales_settings.guide.gift_offers.what_is.title') => [
                    __('sales_settings.guide.gift_offers.what_is.a'),
                    __('sales_settings.guide.gift_offers.what_is.b'),
                    __('sales_settings.guide.gift_offers.what_is.c'),
                ],
                __('sales_settings.guide.gift_offers.statuses.title') => [
                    __('sales_settings.guide.gift_offers.statuses.a'),
                    __('sales_settings.guide.gift_offers.statuses.b'),
                ],
                __('sales_settings.guide.gift_offers.stats.title') => [
                    __('sales_settings.guide.gift_offers.stats.a'),
                ],
                __('sales_subscription.guide.title') => [
                    __('sales_subscription.guide.a'),
                    __('sales_subscription.guide.b'),
                    __('sales_subscription.guide.c'),
                    __('sales_subscription.guide.d'),
                ],
            ],
        ])

        {{-- «عروض الهدايا» سقفٌ متزامن: الرقم يهبط من نفسه حين ينتهي
             تاريخُ عرضٍ أو يُعطَّل، فلا يستلزم بلوغُه ترقيةَ باقةٍ دائماً --}}
        @include('sales::components.subscription-bar', ['limit' => $subscriptionLimit ?? null])

        {{-- ══════════ الإحصاء ══════════ --}}
        <div class="sl-stats">
            <div class="sl-stat">
                <div class="sl-stat__icon"><i class="feather icon-gift"></i></div>
                <div class="sl-stat__body">
                    <p class="sl-stat__value sl-num">{{ \App\Support\CurrencySettings::number($stats['total'], 0) }}</p>
                    <p class="sl-stat__label">{{ __('sales_settings.gift_offers.total') }} — {{ __('sales_settings.guide_hints.gift_offers.total') }}</p>
                </div>
            </div>

            <div class="sl-stat">
                <div class="sl-stat__icon sl-stat__icon--success"><i class="feather icon-check-circle"></i></div>
                <div class="sl-stat__body">
                    <p class="sl-stat__value sl-num">{{ \App\Support\CurrencySettings::number($stats['active'], 0) }}</p>
                    <p class="sl-stat__label">{{ __('sales_settings.gift_offers.active') }} — {{ __('sales_settings.guide_hints.gift_offers.active') }}</p>
                </div>
            </div>

            {{-- «سارٍ اليوم» ليس «مفعَّل»: عرضٌ مفعَّل انتهى تاريخه لا يُطبَّق،
                 وعدّه في «المفعَّل» يجعل الشاشة تَعِد بما لا يقع --}}
            <div class="sl-stat">
                <div class="sl-stat__icon sl-stat__icon--info"><i class="feather icon-play-circle"></i></div>
                <div class="sl-stat__body">
                    <p class="sl-stat__value sl-num">{{ \App\Support\CurrencySettings::number($stats['running'], 0) }}</p>
                    <p class="sl-stat__label">{{ __('sales_settings.gift_offers.running') }} — {{ __('sales_settings.guide_hints.gift_offers.running') }}</p>
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

                <form method="GET" action="{{ route('gift-offers.index') }}">
                    <div class="row">
                        <div class="col-md-8 col-sm-12">
                            <div class="form-group">
                                <label for="search">{{ __('sales_settings.ui.search') }}</label>
                                <input type="text" id="search" name="search" class="form-control"
                                       value="{{ request('search') }}"
                                       placeholder="{{ __('sales_settings.gift_offers.search_placeholder') }}">
                                <small class="sl-help">{{ __('sales_settings.guide_hints.gift_offers.search') }}</small>
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
                                <small class="sl-help">{{ __('sales_settings.guide_hints.gift_offers.status') }}</small>
                            </div>
                        </div>
                    </div>

                    <div class="sl-filters__foot">
                        @if ($hasFilters)
                            <a href="{{ route('gift-offers.index') }}" class="btn btn-outline-secondary">
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
                    <i class="feather icon-gift"></i> {{ __('sales_settings.gift_offers.title') }}
                </h6>
                <span class="sl-count sl-num">{{ \App\Support\CurrencySettings::number($offers->total(), 0) }}</span>
            </div>

            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('sales_settings.ui.name') }}</th>
                            <th>{{ __('sales_settings.gift_offers.buy') }}</th>
                            <th>{{ __('sales_settings.gift_offers.get') }}</th>
                            <th class="text-center">{{ __('sales_settings.gift_offers.period') }}</th>
                            <th class="text-center" style="width:120px">{{ __('sales_settings.ui.status') }}</th>
                            <th class="text-center" style="width:130px">{{ __('sales_settings.ui.actions') }}</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($offers as $offer)
                            @php
                                $expired = $offer->end_date && $offer->end_date < $today;
                                $pending = $offer->start_date && $offer->start_date > $today;
                            @endphp

                            <tr>
                                <td>
                                    <div class="sl-media">
                                        <span class="sl-media__thumb" aria-hidden="true"><i class="feather icon-gift"></i></span>
                                        <div class="sl-media__body">
                                            {{-- الاسم اختياري في القاعدة: الرقم بديلٌ يُميّز الصفّ
                                                 بدل شرطةٍ لا تدلّ على شيء --}}
                                            <span class="sl-media__title">{{ $offer->name ?: '#'.$offer->id }}</span>
                                            <span class="sl-media__sub">
                                                {{ $offer->is_for_all_clients
                                                    ? __('sales_settings.gift_offers.all_clients')
                                                    : __('sales_settings.gift_offers.some_clients') }}
                                            </span>
                                        </div>
                                    </div>
                                </td>

                                <td>
                                    <span class="sl-media__title">{{ $offer->targetProduct->name ?? __('sales_settings.ui.none') }}</span>
                                    <span class="sl-media__sub sl-num">
                                        {{ __('sales_settings.gift_offers.min_quantity') }}:
                                        {{ \App\Support\CurrencySettings::number($offer->min_quantity, 0) }}
                                    </span>
                                </td>

                                <td>
                                    <span class="sl-media__title">{{ $offer->giftProduct->name ?? __('sales_settings.ui.none') }}</span>
                                    <span class="sl-media__sub sl-num">
                                        {{ __('sales_settings.gift_offers.gift_quantity') }}:
                                        {{ \App\Support\CurrencySettings::number($offer->gift_quantity, 0) }}
                                    </span>
                                </td>

                                {{-- التواريخ بتنسيق اللغة لا بما خرج من القاعدة خاماً --}}
                                <td class="text-center">
                                    @if ($offer->start_date || $offer->end_date)
                                        <span class="sl-num" style="font-size:.8rem">
                                            {{ $offer->start_date ? \App\Support\LanguageSettings::formatDate($offer->start_date) : '…' }}
                                            —
                                            {{ $offer->end_date ? \App\Support\LanguageSettings::formatDate($offer->end_date) : '…' }}
                                        </span>
                                    @else
                                        <span class="sl-state sl-state--neutral">{{ __('sales_settings.gift_offers.no_period') }}</span>
                                    @endif
                                </td>

                                <td class="text-center">
                                    @if (! $offer->is_active)
                                        <span class="sl-state sl-state--off">{{ __('sales_settings.ui.inactive') }}</span>
                                    @elseif ($expired)
                                        <span class="badge badge-warning">{{ __('sales_settings.gift_offers.expired') }}</span>
                                    @elseif ($pending)
                                        <span class="badge badge-info">{{ __('sales_settings.gift_offers.pending') }}</span>
                                    @else
                                        <span class="sl-state sl-state--on">{{ __('sales_settings.gift_offers.running') }}</span>
                                    @endif
                                </td>

                                <td class="text-center">
                                    <div class="sl-actions">
                                        @permission('sales.gift_offers.edit')
                                            <a href="{{ route('gift-offers.edit', $offer->id) }}"
                                               class="btn btn-outline-primary" title="{{ __('sales_settings.ui.edit') }}"
                                               aria-label="{{ __('sales_settings.ui.edit') }}">
                                                <i class="feather icon-edit-2"></i>
                                            </a>

                                            {{-- التبديل مسار POST، فهو زرّ داخل نموذج لا رابط --}}
                                            <form action="{{ route('gift-offers.status', $offer->id) }}"
                                                  method="POST" class="d-inline">
                                                @csrf
                                                <button type="submit"
                                                        class="btn btn-outline-{{ $offer->is_active ? 'warning' : 'success' }}"
                                                        title="{{ __('sales_settings.gift_offers.toggle_hint') }}"
                                                        aria-label="{{ __('sales_settings.gift_offers.toggle_hint') }}">
                                                    <i class="feather icon-{{ $offer->is_active ? 'x-circle' : 'check-circle' }}"></i>
                                                </button>
                                            </form>
                                        @endpermission

                                        {{-- المسار DELETE والفعل تعطيل — والزرّ باسم فعله لا
                                             باسم مساره: كان مكتوباً عليه «حذف» وهو لا يحذف --}}
                                        @permission('sales.gift_offers.delete')
                                            <form action="{{ route('gift-offers.destroy', $offer->id) }}"
                                                  method="POST" class="sl-disable-form d-inline"
                                                  data-name="{{ $offer->name ?: '#'.$offer->id }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger"
                                                        @disabled(! $offer->is_active)
                                                        title="{{ __('sales_settings.gift_offers.disable') }}"
                                                        aria-label="{{ __('sales_settings.gift_offers.disable') }}">
                                                    <i class="feather icon-slash"></i>
                                                </button>
                                            </form>
                                        @endpermission
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    @include('sales::components.empty-state', [
                                        'icon' => 'gift',
                                        'title' => $hasFilters
                                            ? __('sales_settings.ui.no_results')
                                            : __('sales_settings.gift_offers.empty'),
                                        'hint' => $hasFilters
                                            ? __('sales_settings.ui.no_results_hint')
                                            : __('sales_settings.gift_offers.empty_hint'),
                                        'action' => $hasFilters
                                            ? '<a href="'.route('gift-offers.index').'" class="btn btn-outline-secondary">'
                                                .'<i class="feather icon-x"></i> '.e(__('sales_settings.ui.filters_clear')).'</a>'
                                            : (hasPermission('sales.gift_offers.create')
                                                ? '<a href="'.route('gift-offers.create').'" class="btn btn-primary">'
                                                    .'<i class="feather icon-plus"></i> '.e(__('sales_settings.gift_offers.new')).'</a>'
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
            $('.sl-disable-form').on('submit', function (e) {
                e.preventDefault();
                var form = $(this);

                Swal.fire({
                    title: @json(__('sales_settings.gift_offers.disable')),
                    text: @json(__('sales_settings.gift_offers.confirm_disable')).replace(':name', form.data('name')),
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
