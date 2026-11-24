{{--
    إدارة فواتير المبيعات.

    أُعيد بناء الشاشة على قياس «الأذون المخزنية»
    (`stock::store_permits_management.index`): ترويسةٌ فيها الإجراءات،
    ثمّ بطاقة فلترة ببحثٍ أساسي ومتقدّم، ثمّ بطاقة نتائج تُستبدَل وحدها
    بـAjax، وتصديرٌ وطباعةٌ يقرآن الجدول كما هو معروض.

    ما تأتي به الإعدادات:
      • العملة والكسور   ← «العملات» (كل مبلغ في الشاشة والفلتر)
      • نطاق التاريخ     ← «الفترات المحاسبية» (الفترة الجارية افتراضاً)
      • اللغة والاتجاه   ← «اللغات» عبر `LanguageSettings`
      • الألوان والخط    ← «مظهر النظام» عبر `--app-*` التي يقوم عليها `sl-*`
      • القوالب          ← «قوالب المبيعات» في قائمة المطبوعات لكل صفّ

    ما أُزيل:
      • `<style>` من ثمانين سطراً يعيد تعريف الأزرار والجداول للجوّال
        وحدها، وقد صار في نظام `sl-*`.
      • التحميل المزدوج: الشاشة كانت تُرسَل بجدولٍ فارغ ثم تطلبه بنداء
        Ajax بعد 100ms، فيرى المستخدم دوّامةً قبل كل شيء.
      • `#resetSearch` معرَّفاً مرّتين في الصفحة الواحدة.
--}}

@extends('sales::master')

@section('title', __('sales_invoice.index_title'))

@section('content')
    @include('sales::partials.settings-theme')
    @include('components.hint-tips')
    @include('sales::partials.table-menu')

    @php
        $hasFilters = request()->hasAny([
            'client_id', 'invoice_number', 'payment_status', 'branch',
            'total_from', 'total_to', 'from_date', 'to_date',
            'added_by_employee', 'sales_person_user',
        ]);

        // «بحث متقدّم» يُفتح وحده حين يكون فيه فلترٌ مضبوط
        $advancedActive = request()->filled('total_from') || request()->filled('total_to')
            || request()->filled('added_by_employee') || request()->filled('sales_person_user');

        $paymentStatuses = [
            1 => __('sales_invoice.status.paid'),
            2 => __('sales_invoice.status.partial'),
            3 => __('sales_invoice.status.unpaid'),
            4 => __('sales_invoice.status.received'),
        ];
    @endphp

    <div class="content-body sl-scope">

        @section('page-actions')
            @permission('sales.invoices.create')
                <a href="{{ route('invoices.create') }}" class="btn btn-primary">
                    <i class="feather icon-plus"></i> {{ __('sales_invoice.new_invoice') }}
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
            'title' => __('sales_invoice.index_title'),
            'icon' => 'file-text',
            'sub' => __('sales_invoice.index_subtitle'),
            'crumbs' => [
                __('sales_settings.ui.home') => route('dashboard'),
                __('sales_invoice.invoices') => null,
            ],
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')
        @include('sales::components.form-errors')

        {{-- ══════════ إرشادُ الشاشة ══════════
             على قياس لوحة «فواتير المشتريات» — تُفتح أوّل زيارة ثمّ تتذكّر
             طيّها لهذه الشاشة وحدها. النصّ كلُّه من ملفّ اللغة لا من هنا. --}}
        @include('sales::components.guide', [
            'key' => 'sales-invoices.index',
            'open' => false,
            'title' => __('sales_invoice.guide.index.title'),
            'lead' => __('sales_invoice.guidance.index'),
            'blocks' => [
                __('sales_invoice.guide.what_is.title') => [
                    __('sales_invoice.guide.what_is.a'),
                    __('sales_invoice.guide.what_is.b'),
                    __('sales_invoice.guide.what_is.c'),
                ],
                __('sales_invoice.guide.money_flow.title') => [
                    __('sales_invoice.guide.money_flow.a'),
                    __('sales_invoice.guide.money_flow.b'),
                    __('sales_invoice.guide.money_flow.c'),
                ],
                __('sales_invoice.guide.statuses.title') => [
                    __('sales_invoice.guide.statuses.a'),
                    __('sales_invoice.guide.statuses.b'),
                    __('sales_invoice.guide.statuses.c'),
                ],
                __('sales_invoice.guide.stats.title') => [
                    __('sales_invoice.guide.stats.a'),
                    __('sales_invoice.guide.stats.b'),
                ],
                __('sales_subscription.guide.title') => [
                    __('sales_subscription.guide.a'),
                    __('sales_subscription.guide.b'),
                    __('sales_subscription.guide.c'),
                    __('sales_subscription.guide.d'),
                ],
                __('sales_invoice.guide.settings.title') => [
                    __('sales_invoice.guide.settings.a'),
                    __('sales_invoice.guide.settings.b'),
                    __('sales_invoice.guide.settings.c'),
                    __('sales_invoice.guide.settings.d'),
                ],
            ],
        ])

        {{-- حدُّ باقة فوترة لفواتير المبيعات — يختفي إذا كانت بلا حدّ أو
             كان النظام غير مربوطٍ باشتراك --}}
        @include('sales::components.subscription-bar', ['limit' => $subscriptionLimit ?? null])

        {{-- ══════════ الإحصاءات ══════════ --}}
        <div class="sl-stats">
            <div class="sl-stat">
                <span class="sl-stat__icon sl-stat__icon--success"><i class="feather icon-trending-up"></i></span>
                <div class="sl-stat__body">
                    <p class="sl-stat__value" id="total-sales-stat">
                        {{ \App\Support\CurrencySettings::format($totalSales ?? 0) }}
                    </p>
                    <p class="sl-stat__label">
                        {{ __('sales_invoice.stats.sales_cost') }}
                        — <span id="sales-count-stat">{{ __('sales_invoice.stats.invoices_count', ['count' => $salesInvoiceCount ?? 0]) }}</span>
                        {{-- التلميح داخل السطر لا تحته: `.sl-help` كتلةٌ
                             كاملة، ووضعُها في `<p>` البطاقة يكسر ارتفاعها --}}
                        — {{ __('sales_invoice.stats_hints.sales') }}
                    </p>
                </div>
            </div>

            <div class="sl-stat">
                <span class="sl-stat__icon sl-stat__icon--danger"><i class="feather icon-corner-up-left"></i></span>
                <div class="sl-stat__body">
                    <p class="sl-stat__value" id="total-returns-stat">
                        {{ \App\Support\CurrencySettings::format($totalReturns ?? 0) }}
                    </p>
                    <p class="sl-stat__label">
                        {{ __('sales_invoice.stats.returns_cost') }}
                        — <span id="returns-count-stat">{{ __('sales_invoice.stats.returns_count', ['count' => $returnInvoiceCount ?? 0]) }}</span>
                        — {{ __('sales_invoice.stats_hints.returns') }}
                    </p>
                </div>
            </div>

            <div class="sl-stat">
                <span class="sl-stat__icon"><i class="feather icon-bar-chart-2"></i></span>
                <div class="sl-stat__body">
                    <p class="sl-stat__value" id="net-sales-stat">
                        {{ \App\Support\CurrencySettings::format($netSales ?? 0) }}
                    </p>
                    <p class="sl-stat__label">{{ __('sales_invoice.stats.net_cost') }} — {{ __('sales_invoice.stats_hints.net') }}</p>
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

                        {{-- `data-sl-toggle` لا `data-toggle`: القالب يحمّل Bootstrap 4 و5،
                             فيفتح أحدُهما اللوحة ويغلقها الآخر --}}
                        <button type="button" class="btn btn-sm btn-outline-secondary"
                                data-sl-toggle="#advancedSearchForm"
                                aria-expanded="{{ $advancedActive ? 'true' : 'false' }}">
                            <i class="feather icon-sliders"></i> {{ __('sales_settings.ui.advanced') }}
                        </button>
                    </div>
                </div>

                <form id="searchForm" data-search-url="{{ route('invoices.index') }}">
                    @csrf

                    <div class="row">
                        <div class="col-lg-3 col-md-6">
                            <div class="form-group">
                                <label for="invoice_number">{{ __('sales_invoice.filters.keywords') }}</label>
                                <input type="text" id="invoice_number" name="invoice_number" class="form-control"
                                       value="{{ request('invoice_number') }}"
                                       placeholder="{{ __('sales_invoice.filters.keywords_placeholder') }}">
                                <small class="sl-help">{{ __('sales_invoice.filter_hints.keywords') }}</small>
                            </div>
                        </div>

                        <div class="col-lg-3 col-md-6">
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
                                <small class="sl-help">{{ __('sales_invoice.filter_hints.client') }}</small>
                            </div>
                        </div>

                        <div class="col-lg-2 col-md-6">
                            <div class="form-group">
                                <label for="payment_status">{{ __('sales_invoice.filters.payment_status') }}</label>
                                <select id="payment_status" name="payment_status" class="form-control">
                                    <option value="">{{ __('sales_invoice.filters.all_payment_statuses') }}</option>
                                    @foreach ($paymentStatuses as $value => $label)
                                        <option value="{{ $value }}"
                                            {{ (string) request('payment_status') === (string) $value ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                                <small class="sl-help">{{ __('sales_invoice.filter_hints.payment_status') }}</small>
                            </div>
                        </div>

                        {{-- نطاق التاريخ في الصفّ الظاهر: هو أكثرُ الفلاتر استعمالاً، ويصفّي
                             كلَّ زيارةٍ بالفترة الجارية — وكان مطويّاً في «بحث متقدّم» --}}
                        <div class="col-lg-4 col-md-6">
                            <div class="form-group">
                                <label for="from_date">{{ __('sales_invoice.filters.date_range') }}</label>
                                <div class="input-group">
                                    <input type="date" id="from_date" name="from_date" class="form-control"
                                           value="{{ request('from_date', $range['start'] ?? '') }}">
                                    <input type="date" name="to_date" class="form-control"
                                           value="{{ request('to_date', $range['end'] ?? '') }}">
                                </div>
                                <small class="sl-help">{{ __('sales_invoice.filter_hints.date_range') }}</small>
                                @if (($range['is_default'] ?? false))
                                    <small class="sl-help">{{ __('sales_invoice.filters.period_default') }}</small>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="collapse {{ $advancedActive ? 'show' : '' }}" id="advancedSearchForm">
                        <div class="row">
                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label for="total_from">{{ __('sales_invoice.filters.total_from') }}</label>
                                    <input type="number" step="any" min="0" id="total_from" name="total_from"
                                           class="form-control" value="{{ request('total_from') }}">
                                </div>
                            </div>

                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label for="total_to">{{ __('sales_invoice.filters.total_to') }}</label>
                                    <input type="number" step="any" min="0" id="total_to" name="total_to"
                                           class="form-control" value="{{ request('total_to') }}">
                                    <small class="sl-help">{{ __('sales_invoice.filter_hints.totals') }}</small>
                                </div>
                            </div>

                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label for="added_by_employee">{{ __('sales_invoice.filters.added_by') }}</label>
                                    <select id="added_by_employee" name="added_by_employee" class="form-control select2">
                                        <option value="">{{ __('sales_invoice.filters.all') }}</option>
                                        @foreach ($employees as $employee)
                                            <option value="{{ $employee->id }}"
                                                {{ request('added_by_employee') == $employee->id ? 'selected' : '' }}>
                                                {{ $employee->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <small class="sl-help">{{ __('sales_invoice.filter_hints.added_by') }}</small>
                                </div>
                            </div>

                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label for="sales_person_user">{{ __('sales_invoice.filters.sales_person') }}</label>
                                    <select id="sales_person_user" name="sales_person_user" class="form-control select2">
                                        <option value="">{{ __('sales_invoice.filters.all') }}</option>
                                        @foreach ($employees_sales_person as $employee)
                                            <option value="{{ $employee->id }}"
                                                {{ request('sales_person_user') == $employee->id ? 'selected' : '' }}>
                                                {{ $employee->full_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <small class="sl-help">{{ __('sales_invoice.filter_hints.sales_person') }}</small>
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
                    <i class="feather icon-list"></i> {{ __('sales_invoice.invoices') }}
                    @if (method_exists($invoices, 'total'))
                        <span class="sl-count">{{ $invoices->total() }}</span>
                    @endif
                </h6>
                <div class="spinner-border spinner-border-sm text-primary d-none" id="loading-indicator" role="status"></div>
            </div>

            <div id="results-container">
                @include('sales::invoices.partials.table', [
                    'invoices' => $invoices,
                    'returnedIds' => $returnedIds ?? [],
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
                fileName: @json(__('sales_invoice.index_title')),
                title: @json(__('sales_invoice.index_title')),
                subTitle: @json(config('app.name')),
                // الإجمالي هو العمود الخامس (رقم، عميل، فرع، تاريخ، إجمالي) — كان [3] أي التاريخ
                numericColumns: [4],
                exportButton: '#exportExcel',
                printButton: '#printReport',
                descriptionColumn: 1,
            });
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    @php $slFiltersVersion = @filemtime(public_path('assets/js/sales-index-filters.js')) ?: time(); @endphp
    <script src="{{ asset('assets/js/sales-index-filters.js') }}?v={{ $slFiltersVersion }}"></script>
    <script>
        const invoiceRoutes = {
            index: @json(route('invoices.index')),
            destroy: @json(route('invoices.destroy', ['id' => ':id'])),
        };

        const invoiceToken = @json(csrf_token());

        $(function () {
            if ($.fn.select2) {
                $('#searchForm .select2').select2({
                    placeholder: @json(__('sales_invoice.filters.all')),
                    allowClear: true,
                    dir: @json(\App\Support\LanguageSettings::direction()),
                    width: '100%',
                });
            }

            /* التصفية والترقيم والمسح وزرّ الرجوع — `sales-index-filters.js`،
               سلوكٌ واحد للشاشات الخمس. ويبقى `loadData()` باسمه لمن يناديه. */
            SalesIndexFilters.bind({
                url: invoiceRoutes.index,
                failText: @json(__('sales_settings.ui.load_failed')),
                onData: function (response) {
                    $('#total-sales-stat').text(response.total_sales);
                    $('#total-returns-stat').text(response.total_returns);
                    $('#net-sales-stat').text(response.net_sales);
                    $('#sales-count-stat').text(
                        @json(__('sales_invoice.stats.invoices_count', ['count' => ':n'])).replace(':n', response.sales_count)
                    );
                    $('#returns-count-stat').text(
                        @json(__('sales_invoice.stats.returns_count', ['count' => ':n'])).replace(':n', response.returns_count)
                    );
                },
            });

            /* إجراءات الصفوف بالتفويض: الجدول يُستبدل كاملاً عند كل تحميل،
               فربط المستمعات بعد كل استبدال لم يعد لازماً */

            $('#results-container').on('click', '.delete-invoice', function (e) {
                e.preventDefault();

                const id = $(this).data('id');
                const code = $(this).data('code');

                Swal.fire({
                    icon: 'warning',
                    title: @json(__('sales_invoice.actions.delete')),
                    text: '#' + code,
                    showCancelButton: true,
                    confirmButtonText: @json(__('sales_settings.ui.delete')),
                    cancelButtonText: @json(__('sales_settings.ui.cancel')),
                    reverseButtons: true,
                }).then(function (result) {
                    if (!result.isConfirmed) return;

                    $.ajax({
                        url: invoiceRoutes.destroy.replace(':id', id),
                        method: 'POST',
                        data: { _method: 'DELETE', _token: invoiceToken },
                    })
                        .done(() => loadData())
                        .fail(xhr => Swal.fire({
                            icon: 'error',
                            title: @json(__('sales_settings.ui.delete')),
                            text: (xhr.responseJSON && xhr.responseJSON.message) || '',
                        }));
                });
            });

        });
    </script>
@endsection
