@extends('sales::master')

{{--
    قائمة اتفاقيات التقسيط.

    ══════════════════ ما كان ══════════════════

    • **الشاشة تسقط بـ500 بمجرّد وجود اتفاقيةٍ واحدة** — `index()` ينادي
      `$installment->invoice->installments()` و`invoice()` كانت `hasMany`
      فتُرجع `Collection` لا `Invoice`.

    • `{{ $installment->status }}` يطبع الرقم الخام (1/2/3) للمستخدم.

    • `id="dropdownMenuButton303"` **داخل الحلقة** — معرّفٌ مكرَّر في كلّ
      صفّ، و`aria-labelledby` يشير إليه.

    • `<li>` بلا `<ul>` داخل `.dropdown-menu`.

    • ترقيمٌ معلَّق داخل تعليق Blade على المتغيّر `$shifts` — من شاشة
      الورديّات، لا وجود له هنا. ولا ترقيمَ في الشاشة أصلاً.

    • نافذةُ حذفٍ كاملة معلَّقة، وفيها `<a href="…destroy">` رابطَ GET
      لمسار DELETE.

    • وسمَا `div` زائدان بعد إغلاق الشرط، خارج أيّ كتلة مفتوحة.

    • فلترُ الحالة يرسل `الكل` نصّاً عربياً قيمةً للخيار الأول.
--}}

@section('title', __('sales_installments.title'))

@section('content')
    @include('sales::partials.settings-theme')
    @include('components.hint-tips')

    @php
        $filterKeys = ['search', 'client_id', 'status', 'collection', 'currency', 'from_date', 'to_date'];
        $hasFilters = collect($filterKeys)->contains(fn ($k) => request()->filled($k));
    @endphp

    <div class="content-body sl-scope">

        @section('page-actions')
            @permission('sales.installments.view')
                <a href="{{ route('installments.agreement_installments') }}" class="btn btn-outline-primary">
                    <i class="feather icon-list"></i> {{ __('sales_installments.actions.schedule') }}
                </a>
            @endpermission

            @if (Route::has('invoices.index'))
                @permission('sales.invoices.view')
                    <a href="{{ route('invoices.index') }}" class="btn btn-outline-secondary">
                        <i class="feather icon-file-text"></i> {{ __('sales_installments.fields.invoice') }}
                    </a>
                @endpermission
            @endif
        @endsection

        @include('sales::components.page-head', [
            'title' => __('sales_installments.title'),
            'icon' => 'credit-card',
            'sub' => __('sales_installments.subtitle'),
            'crumbs' => [
                __('sales_settings.ui.home') => route('dashboard'),
                __('sales_installments.title') => null,
            ],
        ])

        @include('sales::components.system-context', [
            'only' => ['currency', 'payments', 'period', 'accounts'],
        ])

        @include('layouts.alerts.success')
        @include('layouts.alerts.error')
        @include('sales::components.form-errors')

        @include('sales::components.guide', [
            'key' => 'installments.index',
            'open' => false,
            'title' => __('sales_installments.guide.index.title'),
            'lead' => __('sales_installments.guide.what_is'),
            'blocks' => [
                __('sales_installments.guide.levels.title') => [
                    __('sales_installments.guide.levels.a'),
                    __('sales_installments.guide.levels.b'),
                    __('sales_installments.guide.levels.c'),
                ],
                __('sales_installments.guide.money_flow.title') => [
                    __('sales_installments.guide.money_flow.a'),
                    __('sales_installments.guide.money_flow.b'),
                    __('sales_installments.guide.money_flow.c'),
                    __('sales_installments.guide.money_flow.d'),
                    __('sales_installments.guide.money_flow.e'),
                ],
                __('sales_installments.guide.statuses.title') => [
                    __('sales_installments.guide.statuses.a'),
                    __('sales_installments.guide.statuses.b'),
                    __('sales_installments.guide.statuses.c'),
                ],
                __('sales_installments.guide.index.create.title') => [
                    __('sales_installments.guide.index.create.a'),
                    __('sales_installments.guide.index.create.b'),
                ],
                __('sales_installments.guide.index.stats.title') => [
                    __('sales_installments.guide.index.stats.a'),
                    __('sales_installments.guide.index.stats.b'),
                ],
            ],
        ])

        {{-- ══════════ الإحصاء ══════════ --}}
        <div class="sl-stats">
            @foreach ([
                ['total', 'credit-card', ''],
                ['active', 'play-circle', 'sl-stat__icon--info'],
                ['overdue', 'alert-triangle', 'sl-stat__icon--danger'],
                ['financed', 'dollar-sign', 'sl-stat__icon--warning'],
                ['collected', 'check-circle', 'sl-stat__icon--success'],
                ['outstanding', 'clock', 'sl-stat__icon--warning'],
            ] as [$key, $icon, $tone])
                <div class="sl-stat">
                    <div class="sl-stat__icon {{ $tone }}"><i class="feather icon-{{ $icon }}"></i></div>
                    <div class="sl-stat__body">
                        <p class="sl-stat__value sl-num">
                            {{ is_numeric($stats[$key])
                                ? \App\Support\CurrencySettings::number($stats[$key], 0)
                                : $stats[$key] }}
                        </p>
                        <p class="sl-stat__label">
                            {{ __('sales_installments.stats.'.$key) }} —
                            {{ __('sales_installments.stats.'.$key.'_hint') }}
                        </p>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- ══════════ التصفية ══════════ --}}
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

                <form method="GET" action="{{ route('installments.index') }}">
                    <div class="row">
                        <div class="col-md-6 col-12">
                            <div class="form-group">
                                <label for="search">{{ __('sales_settings.ui.search') }}</label>
                                <input type="text" id="search" name="search" class="form-control"
                                       value="{{ request('search') }}"
                                       placeholder="{{ __('sales_installments.filters.search_placeholder') }}">
                                <span class="sl-help">{{ __('sales_installments.filter_hints.search') }}</span>
                            </div>
                        </div>

                        <div class="col-md-3 col-sm-6 col-12">
                            <div class="form-group">
                                <label for="client_id">{{ __('sales_installments.fields.client') }}</label>
                                <select id="client_id" name="client_id" class="form-control">
                                    <option value="">{{ __('sales_installments.filters.all_clients') }}</option>
                                    @foreach ($clients as $client)
                                        <option value="{{ $client->id }}" @selected(request('client_id') == $client->id)>
                                            {{ $client->trade_name }}
                                        </option>
                                    @endforeach
                                </select>
                                <span class="sl-help">{{ __('sales_installments.filter_hints.client') }}</span>
                            </div>
                        </div>

                        <div class="col-md-3 col-sm-6 col-12">
                            <div class="form-group">
                                <label for="status">{{ __('sales_installments.fields.status') }}</label>
                                {{-- القيمة الفارغة تعني «الكلّ» — لا نصّاً
                                     عربياً يُرسَل قيمةً كما كان --}}
                                <select id="status" name="status" class="form-control">
                                    <option value="">{{ __('sales_installments.status.all') }}</option>
                                    @foreach ($statuses as $status)
                                        <option value="{{ $status }}" @selected(request('status') === $status)>
                                            {{ __('sales_installments.status.'.$status) }}
                                        </option>
                                    @endforeach
                                </select>
                                <span class="sl-help">{{ __('sales_installments.filter_hints.status') }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 col-sm-6 col-12">
                            <div class="form-group">
                                <label for="collection">{{ __('sales_installments.filters.collection') }}</label>
                                <select id="collection" name="collection" class="form-control">
                                    <option value="">{{ __('sales_installments.filters.all_collection') }}</option>
                                    <option value="overdue" @selected(request('collection') === 'overdue')>
                                        {{ __('sales_installments.filters.overdue') }}
                                    </option>
                                    <option value="due_soon" @selected(request('collection') === 'due_soon')>
                                        {{ __('sales_installments.filters.due_soon') }}
                                    </option>
                                </select>
                                <span class="sl-help">{{ __('sales_installments.filter_hints.collection') }}</span>
                            </div>
                        </div>

                        <div class="col-md-4 col-sm-6 col-12">
                            <div class="form-group">
                                <label for="from_date">{{ __('sales_installments.filters.from_date') }}</label>
                                <input type="date" id="from_date" name="from_date" class="form-control"
                                       value="{{ request('from_date') }}">
                                <span class="sl-help">{{ __('sales_installments.filter_hints.dates') }}</span>
                            </div>
                        </div>

                        <div class="col-md-4 col-12">
                            <div class="form-group">
                                <label for="to_date">{{ __('sales_installments.filters.to_date') }}</label>
                                <input type="date" id="to_date" name="to_date" class="form-control"
                                       value="{{ request('to_date') }}">
                                <span class="sl-help">{{ __('sales_installments.filter_hints.dates') }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="sl-filters__foot">
                        @if ($hasFilters)
                            <a href="{{ route('installments.index') }}" class="btn btn-outline-secondary">
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
                    <i class="feather icon-credit-card"></i> {{ __('sales_installments.title') }}
                </h6>
                <span class="sl-count sl-num">{{ \App\Support\CurrencySettings::number($agreements->total(), 0) }}</span>
            </div>

            @include('sales::installments.partials.table', [
                'agreements' => $agreements,
                'hasFilters' => $hasFilters,
            ])

            @include('sales::components.table-foot', [
                'paginator' => $agreements,
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
                    text: @json(__('sales_installments.messages.confirm_delete'))
                        .replace(':code', form.data('code')),
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
