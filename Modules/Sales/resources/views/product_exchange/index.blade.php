@extends('sales::master')

{{-- إدارة التبديلات — القائمة.

     كانت الشاشة على تخطيطٍ ثالثٍ لا يشبه شاشات المبيعات ولا شاشات
     الإعدادات: بطاقاتٌ بأنماطٍ محقونة في الوسم، وعناوينُ عربية مكتوبة في
     القالب لا تمرّ بملفّ لغة، ومبالغُ بـ`number_format(..., 2)` عاريةً من
     رمز العملة — فيقرأ المستخدم «١٢٥٠.٠٠» ولا يعلم بأيّ عملة.

     صارت على لغة `sl-` نفسها التي تعمل بها بقيّة الوحدة: ترويسةٌ بأيقونة
     وفتات مسار، ثمّ بطاقاتُ إحصاء، ثمّ بطاقةُ تصفية، ثمّ بطاقةُ جدول
     بذيلٍ فيه مُنتقي عدد السجلّات والترقيم.

     وأُضيف زرُّ التعديل — لم يكن في الشاشة لأنّ المسار نفسه لم يكن
     موجوداً. --}}

@section('title', __('sales_exchanges.title'))

@section('content')
    @include('sales::partials.settings-theme')
    @include('components.hint-tips')

    @php
        $hasFilters = request()->hasAny(['code', 'client_id', 'from_date', 'to_date', 'branch']);
        $money = fn ($v) => \App\Support\CurrencySettings::format((float) $v);
        $num = fn ($v, $d = 0) => \App\Support\CurrencySettings::number($v, $d);
        $date = fn ($v) => $v ? \App\Support\LanguageSettings::formatDate($v) : '—';
    @endphp

    <div class="content-body sl-scope">

        @section('page-actions')
            @permission('sales.exchanges.create')
                <a href="{{ route('product-exchanges.create') }}" class="btn btn-primary">
                    <i class="feather icon-plus"></i> {{ __('sales_exchanges.new') }}
                </a>
            @endpermission

            @permission('sales.exchanges.dashboard')
                <a href="{{ route('product-exchanges.dashboard') }}" class="btn btn-outline-primary">
                    <i class="feather icon-bar-chart-2"></i> {{ __('sales_exchanges.dashboard') }}
                </a>
            @endpermission

            @permission('sales.exchange_reasons.manage')
                <a href="{{ route('exchange-reasons.index') }}" class="btn btn-outline-secondary">
                    <i class="feather icon-list"></i> {{ __('sales_exchanges.reasons') }}
                </a>
            @endpermission
        @endsection

        @include('sales::components.page-head', [
            'title' => __('sales_exchanges.title'),
            'icon' => 'repeat',
            'sub' => __('sales_exchanges.subtitle'),
            'crumbs' => [
                __('sales_settings.ui.home') => route('dashboard'),
                __('sales_exchanges.title') => null,
            ],
        ])

        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        {{-- ══════════ إرشادُ الشاشة ══════════ --}}
        @include('sales::components.guide', [
            'key' => 'sales-exchanges.index',
            'open' => false,
            'title' => __('sales_exchanges.guide.index.title'),
            'lead' => __('sales_exchanges.guide.lead_index'),
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

        @include('sales::components.subscription-bar', ['limit' => $subscriptionLimit ?? null])

        {{-- الإعدادات التي تعمل بها الشاشة: العملة تُنسّق كل مبلغ، وحسابا
             المستودع والتكلفة يستقبلان قيدَي العملية، والفترة المحاسبية قد
             تردّ الحفظ والحذف --}}
        @include('sales::components.system-context', [
            'only' => ['currency', 'accounts', 'period', 'language'],
            'hint' => __('sales_exchanges.effects.hint'),
        ])

        {{-- ══════════ بطاقات الإحصاء ══════════ --}}
        <div class="sl-stats">
            <div class="sl-stat">
                <div class="sl-stat__icon"><i class="feather icon-repeat"></i></div>
                <div class="sl-stat__body">
                    <p class="sl-stat__value">{{ $num($stats['total']) }}</p>
                    <p class="sl-stat__label">{{ __('sales_exchanges.stats.total') }}</p>
                </div>
            </div>

            <div class="sl-stat">
                <div class="sl-stat__icon sl-stat__icon--info"><i class="feather icon-package"></i></div>
                <div class="sl-stat__body">
                    <p class="sl-stat__value">{{ $num($stats['items']) }}</p>
                    <p class="sl-stat__label">{{ __('sales_exchanges.stats.items') }}</p>
                </div>
            </div>

            <div class="sl-stat">
                <div class="sl-stat__icon sl-stat__icon--warning"><i class="feather icon-dollar-sign"></i></div>
                <div class="sl-stat__body">
                    <p class="sl-stat__value" style="font-size:1.25rem">{{ $money($stats['value']) }}</p>
                    <p class="sl-stat__label">{{ __('sales_exchanges.stats.value') }}</p>
                </div>
            </div>

            <div class="sl-stat">
                <div class="sl-stat__icon sl-stat__icon--success"><i class="feather icon-users"></i></div>
                <div class="sl-stat__body">
                    <p class="sl-stat__value">{{ $num($stats['clients']) }}</p>
                    <p class="sl-stat__label">{{ __('sales_exchanges.stats.clients') }}</p>
                </div>
            </div>

            <div class="sl-stat">
                <div class="sl-stat__icon sl-stat__icon--info"><i class="feather icon-calendar"></i></div>
                <div class="sl-stat__body">
                    <p class="sl-stat__value">{{ $num($stats['today']) }}</p>
                    <p class="sl-stat__label">{{ __('sales_exchanges.stats.today') }}</p>
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

                <form method="GET" action="{{ route('product-exchanges.index') }}">
                    <div class="row">
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="code">{{ __('sales_exchanges.filters.code') }}</label>
                                <input type="text" id="code" name="code" class="form-control"
                                       value="{{ request('code') }}"
                                       placeholder="{{ __('sales_exchanges.filters.code_placeholder') }}">
                            </div>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="client_id">{{ __('sales_exchanges.filters.client') }}</label>
                                <select id="client_id" name="client_id" class="form-control">
                                    <option value="">{{ __('sales_exchanges.filters.all_clients') }}</option>
                                    @foreach ($clients as $client)
                                        <option value="{{ $client->id }}"
                                            @selected((string) request('client_id') === (string) $client->id)>
                                            {{ $client->trade_name ?? $client->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="from_date">{{ __('sales_exchanges.filters.from_date') }}</label>
                                <input type="date" id="from_date" name="from_date" class="form-control"
                                       value="{{ request('from_date') }}">
                            </div>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="to_date">{{ __('sales_exchanges.filters.to_date') }}</label>
                                <input type="date" id="to_date" name="to_date" class="form-control"
                                       value="{{ request('to_date') }}">
                            </div>
                        </div>

                    </div>

                    <div class="sl-filters__foot">
                        @if ($hasFilters)
                            <a href="{{ route('product-exchanges.index') }}" class="btn btn-outline-secondary">
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
                    <i class="feather icon-repeat"></i> {{ __('sales_exchanges.title') }}
                </h6>
                <span class="sl-count">{{ $num($exchanges->total()) }}</span>
            </div>

            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('sales_exchanges.fields.code') }}</th>
                            <th>{{ __('sales_exchanges.fields.client') }}</th>
                            <th>{{ __('sales_exchanges.fields.storehouse') }}</th>
                            <th class="text-center">{{ __('sales_exchanges.items.count') }}</th>
                            <th class="text-center">{{ __('sales_exchanges.items.quantity_total') }}</th>
                            <th class="text-center">{{ __('sales_exchanges.fields.total_value') }}</th>
                            <th class="text-center">{{ __('sales_exchanges.fields.exchange_date') }}</th>
                            <th class="text-center" style="width:170px">{{ __('sales_settings.ui.actions') }}</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($exchanges as $exchange)
                            <tr>
                                <td>
                                    <div class="sl-media">
                                        <span class="sl-media__thumb" aria-hidden="true"><i class="feather icon-repeat"></i></span>
                                        <div class="sl-media__body">
                                            <a href="{{ route('product-exchanges.show', $exchange->id) }}" class="sl-media__title sl-num">
                                                {{ $exchange->code }}
                                            </a>
                                            <span class="sl-media__sub">{{ $exchange->createdByUser->name ?? '—' }}</span>
                                        </div>
                                    </div>
                                </td>

                                <td>{{ $exchange->client->trade_name ?? $exchange->client->name ?? '—' }}</td>

                                {{-- المستودعان معاً: التبديل حركةٌ بينهما، وعرضُ
                                     أحدهما وحده يُخفي نصف العملية --}}
                                <td>
                                    <span class="sl-media__title">{{ $exchange->storehouse->name ?? '—' }}</span>
                                    <span class="sl-media__sub">
                                        <i class="feather icon-arrow-{{ ($languageIsRtl ?? true) ? 'left' : 'right' }}"></i>
                                        {{ $exchange->returnStorehouse->name ?? '—' }}
                                    </span>
                                </td>

                                <td class="text-center sl-num">{{ $exchange->items->count() }}</td>
                                <td class="text-center sl-num">{{ $num($exchange->items->sum('quantity')) }}</td>
                                <td class="text-center sl-num">{{ $money($exchange->total_value) }}</td>
                                <td class="text-center sl-num">{{ $date($exchange->exchange_date) }}</td>

                                <td class="text-center">
                                    <div class="sl-actions">
                                        <a href="{{ route('product-exchanges.show', $exchange->id) }}"
                                           class="btn btn-outline-secondary" title="{{ __('sales_settings.ui.view') }}"
                                           aria-label="{{ __('sales_settings.ui.view') }}">
                                            <i class="feather icon-eye"></i>
                                        </a>

                                        {{-- زرُّ التعديل — لم يكن في الشاشة لأنّ
                                             المسار نفسه لم يكن موجوداً --}}
                                        @permission('sales.exchanges.edit')
                                            <a href="{{ route('product-exchanges.edit', $exchange->id) }}"
                                               class="btn btn-outline-primary" title="{{ __('sales_settings.ui.edit') }}"
                                               aria-label="{{ __('sales_settings.ui.edit') }}">
                                                <i class="feather icon-edit-2"></i>
                                            </a>
                                        @endpermission

                                        @permission('sales.exchanges.print')
                                            <a href="{{ route('product-exchanges.print', $exchange->id) }}"
                                               class="btn btn-outline-info" title="{{ __('sales_settings.ui.print') }}"
                                               aria-label="{{ __('sales_settings.ui.print') }}" target="_blank">
                                                <i class="feather icon-printer"></i>
                                            </a>
                                        @endpermission

                                        @permission('sales.exchanges.delete')
                                            <form action="{{ route('product-exchanges.destroy', $exchange->id) }}"
                                                  method="POST" class="sl-delete-form d-inline"
                                                  data-code="{{ $exchange->code }}">
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
                                <td colspan="8">
                                    @include('sales::components.empty-state', [
                                        'icon' => 'repeat',
                                        'title' => $hasFilters
                                            ? __('sales_settings.ui.no_results')
                                            : __('sales_exchanges.empty'),
                                        'hint' => $hasFilters
                                            ? __('sales_settings.ui.no_results_hint')
                                            : __('sales_exchanges.empty_hint'),
                                        'action' => $hasFilters
                                            ? '<a href="'.route('product-exchanges.index').'" class="btn btn-outline-secondary">'
                                                .'<i class="feather icon-x"></i> '.e(__('sales_settings.ui.filters_clear')).'</a>'
                                            : (hasPermission('sales.exchanges.create')
                                                ? '<a href="'.route('product-exchanges.create').'" class="btn btn-primary">'
                                                    .'<i class="feather icon-plus"></i> '.e(__('sales_exchanges.new')).'</a>'
                                                : ''),
                                    ])
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @include('sales::components.table-foot', [
                'paginator' => $exchanges,
                'sizes' => $pageSizes,
            ])
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        $(function () {
            // الحذف يعكس حركةً مخزنية ويحذف قيدين — التأكيد يقول ذلك صراحةً
            $('.sl-delete-form').on('submit', function (e) {
                e.preventDefault();
                var form = $(this);

                Swal.fire({
                    title: @json(__('sales_settings.ui.confirm_delete_title')),
                    text: @json(__('sales_exchanges.messages.delete_confirm')).replace(':code', form.data('code')),
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
