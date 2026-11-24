@extends('sales::master')

{{-- أسباب استبدال المنتجات.

     كانت على `online-store.css`: بطاقات `stat-card-modern` وجدول
     `modern-table` وقائمة إجراءات منسدلة تُخفي الإجراءات الثلاثة خلف
     نقاطٍ ثلاث — ونصفُ مفاتيحها من `settings.*` أي من وحدة أخرى، تظهر
     خاماً إن عُطّلت تلك الوحدة.

     الآن: لغة «الأذون المخزنية» منقولةً إلى المبيعات — بطاقة تصفية، ثم
     بطاقة جدول برأسٍ فيه العدّاد، ثم ذيلٌ فيه مُنتقي عدد السجلّات
     والترقيم. والإجراءات ظاهرة في صفّها لا مطويّة. --}}

@section('title', __('sales_settings.exchange_reasons.title'))

@section('content')
    @include('sales::partials.settings-theme')
    @include('components.hint-tips')

    @php
        $hasFilters = request()->hasAny(['search', 'status']);
    @endphp

    <div class="content-body sl-scope">

        @section('page-actions')
            <a href="{{ route('exchange-reasons.create') }}" class="btn btn-primary">
                <i class="feather icon-plus"></i> {{ __('sales_settings.exchange_reasons.new') }}
            </a>

            <a href="{{ route('sittingInvoice.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-arrow-{{ ($languageIsRtl ?? true) ? 'right' : 'left' }}"></i>
                {{ __('sales_settings.ui.back') }}
            </a>
        @endsection

        @include('sales::components.page-head', [
            'title' => __('sales_settings.exchange_reasons.title'),
            'icon' => 'repeat',
            'sub' => __('sales_settings.exchange_reasons.subtitle'),
            'crumbs' => [
                __('sales_settings.ui.home') => route('dashboard'),
                __('sales_settings.hub.title') => route('sittingInvoice.index'),
                __('sales_settings.exchange_reasons.title') => null,
            ],
        ])

        @include('layouts.alerts.success')
        @include('layouts.alerts.error')
        @include('sales::components.form-errors')

        {{-- ══════════ إرشادُ الشاشة ══════════ --}}
        @include('sales::components.guide', [
            'key' => 'sales-exchange-reasons.index',
            'open' => false,
            'title' => __('sales_settings.guide.exchange_reasons.title'),
            'lead' => __('sales_settings.guide.exchange_reasons.lead'),
            'blocks' => [
                __('sales_settings.guide.exchange_reasons.what_is.title') => [
                    __('sales_settings.guide.exchange_reasons.what_is.a'),
                    __('sales_settings.guide.exchange_reasons.what_is.b'),
                    __('sales_settings.guide.exchange_reasons.what_is.c'),
                ],
                __('sales_settings.guide.exchange_reasons.statuses.title') => [
                    __('sales_settings.guide.exchange_reasons.statuses.a'),
                    __('sales_settings.guide.exchange_reasons.statuses.b'),
                ],
                __('sales_settings.guide.exchange_reasons.stats.title') => [
                    __('sales_settings.guide.exchange_reasons.stats.a'),
                ],
                __('sales_subscription.guide.title') => [
                    __('sales_subscription.guide.a'),
                    __('sales_subscription.guide.b'),
                    __('sales_subscription.guide.c'),
                    __('sales_subscription.guide.d'),
                ],
            ],
        ])

        {{-- سقفٌ متزامن يعدّ الأسباب النشطة: تعطيلُ سببٍ قديم يفرّغ مكاناً
             لجديد بلا ترقية باقة --}}
        @include('sales::components.subscription-bar', ['limit' => $subscriptionLimit ?? null])

        {{-- ══════════ الإحصاء ══════════ --}}
        <div class="sl-stats">
            <div class="sl-stat">
                <div class="sl-stat__icon"><i class="feather icon-repeat"></i></div>
                <div class="sl-stat__body">
                    <p class="sl-stat__value sl-num">{{ \App\Support\CurrencySettings::number($stats['total'], 0) }}</p>
                    <p class="sl-stat__label">
                        {{ __('sales_settings.exchange_reasons.total') }} —
                        {{ __('sales_settings.exchange_reasons.all_reasons') }}
                    </p>
                </div>
            </div>

            <div class="sl-stat">
                <div class="sl-stat__icon sl-stat__icon--success"><i class="feather icon-check-circle"></i></div>
                <div class="sl-stat__body">
                    <p class="sl-stat__value sl-num">{{ \App\Support\CurrencySettings::number($stats['active'], 0) }}</p>
                    <p class="sl-stat__label">
                        {{ __('sales_settings.exchange_reasons.active') }} —
                        {{ __('sales_settings.exchange_reasons.shown_on_exchange') }}
                    </p>
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

                <form method="GET" action="{{ route('exchange-reasons.index') }}">
                    <div class="row">
                        <div class="col-md-8 col-sm-12">
                            <div class="form-group">
                                <label for="search">{{ __('sales_settings.ui.search') }}</label>
                                <input type="text" id="search" name="search" class="form-control"
                                       value="{{ request('search') }}"
                                       placeholder="{{ __('sales_settings.ui.search_placeholder') }}">
                                <small class="sl-help">{{ __('sales_settings.guide_hints.exchange_reasons.search') }}</small>
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
                                <small class="sl-help">{{ __('sales_settings.guide_hints.exchange_reasons.status') }}</small>
                            </div>
                        </div>
                    </div>

                    <div class="sl-filters__foot">
                        @if ($hasFilters)
                            <a href="{{ route('exchange-reasons.index') }}" class="btn btn-outline-secondary">
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
                    <i class="feather icon-repeat"></i> {{ __('sales_settings.exchange_reasons.title') }}
                </h6>
                <span class="sl-count sl-num">{{ \App\Support\CurrencySettings::number($reasons->total(), 0) }}</span>
            </div>

            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th class="text-center" style="width:78px">{{ __('sales_settings.exchange_reasons.sort_order') }}</th>
                            <th>{{ __('sales_settings.ui.name') }}</th>
                            <th>{{ __('sales_settings.ui.description') }}</th>
                            <th class="text-center" style="width:120px">{{ __('sales_settings.ui.status') }}</th>
                            <th class="text-center" style="width:130px">{{ __('sales_settings.ui.actions') }}</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($reasons as $reason)
                            <tr>
                                <td class="text-center sl-num">{{ $reason->sort_order }}</td>

                                <td>
                                    <div class="sl-media">
                                        <span class="sl-media__thumb" aria-hidden="true"><i class="feather icon-repeat"></i></span>
                                        <div class="sl-media__body">
                                            <span class="sl-media__title">{{ $reason->name }}</span>
                                        </div>
                                    </div>
                                </td>

                                <td>
                                    @if ($reason->description)
                                        {{ Str::limit($reason->description, 60) }}
                                    @else
                                        <span class="sl-media__sub">{{ __('sales_settings.ui.none') }}</span>
                                    @endif
                                </td>

                                <td class="text-center">
                                    <span class="sl-state {{ $reason->is_active ? 'sl-state--on' : 'sl-state--off' }}">
                                        {{ $reason->is_active
                                            ? __('sales_settings.ui.active')
                                            : __('sales_settings.ui.inactive') }}
                                    </span>
                                </td>

                                <td class="text-center">
                                    <div class="sl-actions">
                                        <a href="{{ route('exchange-reasons.edit', $reason->id) }}"
                                           class="btn btn-outline-primary" title="{{ __('sales_settings.ui.edit') }}"
                                           aria-label="{{ __('sales_settings.ui.edit') }}">
                                            <i class="feather icon-edit-2"></i>
                                        </a>

                                        {{-- التبديل مسار POST، فهو زرّ داخل نموذج لا رابط --}}
                                        <form action="{{ route('exchange-reasons.toggle', $reason->id) }}"
                                              method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit"
                                                    class="btn btn-outline-{{ $reason->is_active ? 'warning' : 'success' }}"
                                                    title="{{ __('sales_settings.exchange_reasons.toggle_hint') }}"
                                                    aria-label="{{ __('sales_settings.exchange_reasons.toggle_hint') }}">
                                                <i class="feather icon-{{ $reason->is_active ? 'x-circle' : 'check-circle' }}"></i>
                                            </button>
                                        </form>

                                        <form action="{{ route('exchange-reasons.destroy', $reason->id) }}"
                                              method="POST" class="sl-delete-form d-inline"
                                              data-name="{{ $reason->name }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger"
                                                    title="{{ __('sales_settings.ui.delete') }}"
                                                    aria-label="{{ __('sales_settings.ui.delete') }}">
                                                <i class="feather icon-trash-2"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">
                                    @include('sales::components.empty-state', [
                                        'icon' => 'repeat',
                                        'title' => $hasFilters
                                            ? __('sales_settings.ui.no_results')
                                            : __('sales_settings.exchange_reasons.empty'),
                                        'hint' => $hasFilters
                                            ? __('sales_settings.ui.no_results_hint')
                                            : __('sales_settings.exchange_reasons.empty_hint'),
                                        'action' => $hasFilters
                                            ? '<a href="'.route('exchange-reasons.index').'" class="btn btn-outline-secondary">'
                                                .'<i class="feather icon-x"></i> '.e(__('sales_settings.ui.filters_clear')).'</a>'
                                            : '<a href="'.route('exchange-reasons.create').'" class="btn btn-primary">'
                                                .'<i class="feather icon-plus"></i> '.e(__('sales_settings.exchange_reasons.new')).'</a>',
                                    ])
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @include('sales::components.table-foot', [
                'paginator' => $reasons,
                'sizes' => $pageSizes,
            ])
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        $(function () {
            // تأكيد الحذف — نفس نمط شاشات الأذون المخزنية
            $('.sl-delete-form').on('submit', function (e) {
                e.preventDefault();
                var form = $(this);

                Swal.fire({
                    title: @json(__('sales_settings.ui.confirm_delete_title')),
                    text: @json(__('sales_settings.exchange_reasons.confirm_delete')).replace(':name', form.data('name')),
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
