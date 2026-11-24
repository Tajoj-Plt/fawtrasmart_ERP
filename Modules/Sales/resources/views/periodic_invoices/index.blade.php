{{--
    إدارة الفواتير الدورية.

    أُعيد بناء الشاشة على قياس شاشة فواتير المبيعات
    (`sales::invoices.index`): ترويسة بالإجراءات، بطاقة فلترة ببحث أساسي
    ومتقدّم، بطاقات إحصاء، وبطاقة نتائج تُستبدَل وحدها بـAjax، وتصديرٌ
    وطباعةٌ يقرآن الجدول كما هو معروض.

    ما تأتي به الإعدادات:
      • العملة والكسور   ← «العملات»
      • نطاق التاريخ     ← «الفترات المحاسبية» (الفترة الجارية افتراضاً)
      • اللغة والاتجاه   ← «اللغات»
      • الألوان والخط    ← «مظهر النظام» عبر `--app-*`
--}}

@extends('sales::master')

@section('title', __('sales_periodic.index_title'))

@section('content')
    @include('sales::partials.settings-theme')
    @include('components.hint-tips')
    {{-- قائمة إجراءات الصفّ تُنقل إلى <body> فلا يقصّها `.table-responsive` —
         كبقيّة شاشات المبيعات --}}
    @include('sales::partials.table-menu')

    @php
        $hasFilters = request()->hasAny([
            'invoice_number', 'name_subscription', 'client_id', 'repeat_type', 'is_active',
            'branch', 'min_total', 'max_total', 'from_date', 'to_date', 'created_by',
        ]);
    @endphp

    <div class="content-body sl-scope">

        @section('page-actions')
            @permission('sales.periodic_invoices.create')
                <a href="{{ route('periodic_invoices.create') }}" class="btn btn-primary">
                    <i class="feather icon-plus"></i> {{ __('sales_periodic.new') }}
                </a>
            @endpermission

            <button type="button" class="btn btn-outline-secondary" id="printReport">
                <i class="feather icon-printer"></i> {{ __('sales_settings.ui.print') }}
            </button>

            <button type="button" class="btn btn-outline-secondary" id="exportExcel">
                <i class="feather icon-download"></i> {{ __('sales_invoice.actions.export_visible') }}
            </button>
        @endsection

        @include('sales::components.page-head', [
            'title' => __('sales_periodic.index_title'),
            'icon' => 'repeat',
            'sub' => __('sales_periodic.index_subtitle'),
            'crumbs' => [
                __('sales_settings.ui.home') => route('dashboard'),
                __('sales_periodic.plural') => null,
            ],
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')
        @include('sales::components.form-errors')

        {{-- ══════════ إرشادُ الشاشة ══════════ --}}
        @include('sales::components.guide', [
            'key' => 'sales-periodic.index',
            'open' => false,
            'title' => __('sales_periodic.guide.index.title'),
            'lead' => __('sales_periodic.guide.lead_index'),
            'blocks' => [
                __('sales_periodic.guide.what_is.title') => [
                    __('sales_periodic.guide.what_is.a'),
                    __('sales_periodic.guide.what_is.b'),
                    __('sales_periodic.guide.what_is.c'),
                ],
                __('sales_periodic.guide.effects.title') => [
                    __('sales_periodic.guide.effects.a'),
                    __('sales_periodic.guide.effects.b'),
                    __('sales_periodic.guide.effects.c'),
                ],
                __('sales_periodic.guide.statuses.title') => [
                    __('sales_periodic.guide.statuses.a'),
                    __('sales_periodic.guide.statuses.b'),
                ],
                __('sales_periodic.guide.settings.title') => [
                    __('sales_periodic.guide.settings.a'),
                    __('sales_periodic.guide.settings.b'),
                ],
            ],
        ])

        @include('sales::components.subscription-bar', ['limit' => $subscriptionLimit ?? null])

        {{-- ══════════ الإحصاءات ══════════ --}}
        <div class="sl-stats">
            <div class="sl-stat">
                <span class="sl-stat__icon sl-stat__icon--success"><i class="feather icon-repeat"></i></span>
                <div class="sl-stat__body">
                    <p class="sl-stat__value" id="periodic-total-stat">
                        {{ \App\Support\CurrencySettings::format($periodicTotal ?? 0) }}
                    </p>
                    <p class="sl-stat__label">{{ __('sales_periodic.stats.total') }}</p>
                </div>
            </div>

            <div class="sl-stat">
                <span class="sl-stat__icon"><i class="feather icon-hash"></i></span>
                <div class="sl-stat__body">
                    <p class="sl-stat__value" id="periodic-count-stat">{{ $periodicCount ?? 0 }}</p>
                    <p class="sl-stat__label">{{ __('sales_periodic.stats.count') }}</p>
                </div>
            </div>

            <div class="sl-stat">
                <span class="sl-stat__icon sl-stat__icon--info"><i class="feather icon-check-circle"></i></span>
                <div class="sl-stat__body">
                    <p class="sl-stat__value" id="periodic-active-stat">{{ $activeCount ?? 0 }}</p>
                    <p class="sl-stat__label">{{ __('sales_periodic.stats.active') }}</p>
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

                    <div class="d-flex align-items-center" style="gap:.5rem">
                        @if ($hasFilters)
                            <span class="sl-count">{{ __('sales_settings.ui.filters_active') }}</span>
                        @endif

                        <button type="button" class="btn btn-sm btn-outline-secondary" data-toggle="collapse"
                                data-bs-toggle="collapse" data-target="#advancedSearchForm"
                                data-bs-target="#advancedSearchForm" aria-expanded="false">
                            <i class="feather icon-sliders"></i> {{ __('sales_settings.ui.advanced') }}
                        </button>
                    </div>
                </div>

                <form id="searchForm" data-search-url="{{ route('periodic_invoices.index') }}">
                    @csrf

                    <div class="row">
                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="name_subscription">{{ __('sales_periodic.filters.subscription') }}</label>
                                <input type="text" id="name_subscription" name="name_subscription" class="form-control"
                                       value="{{ request('name_subscription') }}">
                            </div>
                        </div>

                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="client_id">{{ __('sales_invoice.filters.client') }}</label>
                                <select id="client_id" name="client_id" class="form-control select2">
                                    <option value="">{{ __('sales_invoice.filters.all_clients') }}</option>
                                    @foreach ($clients as $client)
                                        <option value="{{ $client->id }}"
                                            {{ request('client_id') == $client->id ? 'selected' : '' }}>
                                            {{ $client->trade_name }} ({{ $client->code }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="repeat_type">{{ __('sales_periodic.filters.repeat_type') }}</label>
                                <select id="repeat_type" name="repeat_type" class="form-control">
                                    <option value="">{{ __('sales_periodic.filters.all_repeat_types') }}</option>
                                    @foreach (__('sales_periodic.repeat_types') as $value => $label)
                                        <option value="{{ $value }}"
                                            {{ (string) request('repeat_type') === (string) $value ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="collapse" id="advancedSearchForm">
                        <div class="row">
                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label for="invoice_number">{{ __('sales_periodic.filters.number') }}</label>
                                    <input type="text" id="invoice_number" name="invoice_number" class="form-control"
                                           value="{{ request('invoice_number') }}"
                                           placeholder="{{ __('sales_invoice.filters.keywords_placeholder') }}">
                                </div>
                            </div>

                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label for="is_active">{{ __('sales_periodic.filters.state') }}</label>
                                    <select id="is_active" name="is_active" class="form-control">
                                        <option value="">{{ __('sales_periodic.filters.all_states') }}</option>
                                        <option value="1" {{ request('is_active') === '1' ? 'selected' : '' }}>
                                            {{ __('sales_periodic.state.active') }}
                                        </option>
                                        <option value="0" {{ request('is_active') === '0' ? 'selected' : '' }}>
                                            {{ __('sales_periodic.state.paused') }}
                                        </option>
                                    </select>
                                </div>
                            </div>

                            {{-- فلتر العملة حُذف: العملة واحدةٌ من «العملات» فلا معنى
                                 للتصفية بها — والمتحكّم يفلتر على الافتراضية ويُدخِل
                                 معها الصفوفَ بلا عمود عملة. --}}
                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label for="min_total">{{ __('sales_periodic.filters.min_total') }}</label>
                                    <input type="number" step="any" min="0" id="min_total" name="min_total"
                                           class="form-control" value="{{ request('min_total') }}">
                                </div>
                            </div>

                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label for="max_total">{{ __('sales_periodic.filters.max_total') }}</label>
                                    <input type="number" step="any" min="0" id="max_total" name="max_total"
                                           class="form-control" value="{{ request('max_total') }}">
                                </div>
                            </div>

                            <div class="col-md-6 col-sm-12">
                                <div class="form-group">
                                    <label>{{ __('sales_periodic.filters.date_range') }}</label>
                                    <div class="input-group">
                                        <input type="date" name="from_date" class="form-control"
                                               value="{{ request('from_date', $range['start'] ?? '') }}">
                                        <input type="date" name="to_date" class="form-control"
                                               value="{{ request('to_date', $range['end'] ?? '') }}">
                                    </div>
                                    @if (($range['is_default'] ?? false))
                                        <small class="sl-help">{{ __('sales_invoice.filters.period_default') }}</small>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="sl-filters__foot">
                        <button type="button" id="resetSearch" class="btn btn-outline-secondary">
                            <i class="feather icon-rotate-ccw"></i> {{ __('sales_settings.ui.filters_clear') }}
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="feather icon-search"></i> {{ __('sales_settings.ui.search') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- ══════════ النتائج ══════════ --}}
        <div class="card sl-table-card">
            <div class="sl-table-head">
                <h6 class="sl-table-head__title">
                    <i class="feather icon-list"></i> {{ __('sales_periodic.plural') }}
                    <span class="sl-count">{{ $periodicInvoices->total() }}</span>
                </h6>
                <div class="spinner-border spinner-border-sm text-primary d-none" id="loading-indicator" role="status"></div>
            </div>

            <div id="results-container">
                @include('sales::periodic_invoices.partials.table', [
                    'periodicInvoices' => $periodicInvoices,
                    'documentTemplates' => $documentTemplates ?? collect(),
                ])
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    @include('sales::components.report-tools')

    <script>
        $(function () {
            slReportTools({
                table: '#reportTable',
                fileName: @json(__('sales_periodic.index_title')),
                title: @json(__('sales_periodic.index_title')),
                subTitle: @json(config('app.name')),
                numericColumns: [4],
                exportButton: '#exportExcel',
                printButton: '#printReport',
                descriptionColumn: 1,
            });
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        const periodicRoutes = {
            index: @json(route('periodic_invoices.index')),
            destroy: @json(route('periodic_invoices.destroy', ['id' => ':id'])),
        };

        const periodicToken = @json(csrf_token());

        $(function () {
            if ($.fn.select2) {
                $('#searchForm .select2').select2({
                    placeholder: @json(__('sales_invoice.filters.all')),
                    allowClear: true,
                    dir: @json(\App\Support\LanguageSettings::direction()),
                    width: '100%',
                });
            }

            window.loadData = function (page, pushState) {
                $('#loading-indicator').removeClass('d-none');
                $('#results-container').css('opacity', '.55');

                const perPage = $('#perPage').length ? $('#perPage').val() : 10;
                const request = $('#searchForm').serialize() + '&page=' + (page || 1) + '&per_page=' + perPage;

                if (pushState !== false) {
                    const url = new URL(window.location.href);
                    new URLSearchParams(request).forEach((value, key) => {
                        if (key === '_token') return;
                        if (value) url.searchParams.set(key, value);
                        else url.searchParams.delete(key);
                    });
                    window.history.pushState({ page: page }, '', url);
                }

                $.ajax({
                    url: periodicRoutes.index,
                    method: 'GET',
                    data: request,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    cache: false,
                })
                    .done(function (response) {
                        if (!response || !response.success) return;

                        $('#results-container').html(response.data);
                        $('.sl-table-head__title .sl-count').text(response.total);
                        $('#periodic-total-stat').text(response.periodic_total);
                        $('#periodic-count-stat').text(response.periodic_count);
                        $('#periodic-active-stat').text(response.active_count);
                    })
                    .fail(function () {
                        $('#results-container').html(
                            '<div class="alert alert-danger m-2">' + @json(__('sales_settings.ui.load_failed')) + '</div>'
                        );
                    })
                    .always(function () {
                        $('#loading-indicator').addClass('d-none');
                        $('#results-container').css('opacity', '1');
                    });
            };

            $('#results-container').on('click', '.pagination-link', function (e) {
                e.preventDefault();
                loadData(parseInt($(this).data('page'), 10));
            });

            $('#results-container').on('click', '.periodic-delete', function (e) {
                e.preventDefault();

                const id = $(this).data('id');
                const number = $(this).data('number');

                Swal.fire({
                    icon: 'warning',
                    title: @json(__('sales_settings.ui.delete')),
                    text: '#' + number,
                    showCancelButton: true,
                    confirmButtonText: @json(__('sales_settings.ui.delete')),
                    cancelButtonText: @json(__('sales_settings.ui.cancel')),
                    reverseButtons: true,
                }).then(function (result) {
                    if (!result.isConfirmed) return;

                    $.ajax({
                        url: periodicRoutes.destroy.replace(':id', id),
                        method: 'POST',
                        data: { _method: 'DELETE', _token: periodicToken },
                    })
                        .done(() => loadData())
                        .fail(xhr => Swal.fire({
                            icon: 'error',
                            title: @json(__('sales_settings.ui.delete')),
                            text: (xhr.responseJSON && xhr.responseJSON.message) || '',
                        }));
                });
            });

            $('#searchForm').on('submit', function (e) {
                e.preventDefault();
                loadData(1);
            });

            $('#searchForm').on('change input', 'input, select', function () {
                clearTimeout(window.periodicSearchTimer);
                window.periodicSearchTimer = setTimeout(() => loadData(1), 500);
            });

            $('#resetSearch').on('click', function () {
                $('#searchForm')[0].reset();
                if ($.fn.select2) $('#searchForm .select2').val('').trigger('change');
                loadData(1);
            });
        });
    </script>
@endsection
