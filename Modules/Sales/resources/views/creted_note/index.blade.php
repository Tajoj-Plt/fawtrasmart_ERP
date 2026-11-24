{{--
    إدارة الإشعارات الدائنة.

    أُعيد بناء الشاشة على قياس شاشة فواتير المبيعات
    (`sales::invoices.index`) — وهي بدورها على قياس «الأذون المخزنية»:
    ترويسة بالإجراءات، بطاقة فلترة ببحث أساسي ومتقدّم، بطاقات إحصاء،
    وبطاقة نتائج تُستبدَل وحدها بـAjax، وتصديرٌ وطباعةٌ يقرآن الجدول
    كما هو معروض.

    ما تأتي به الإعدادات:
      • العملة والكسور   ← «العملات»
      • نطاق التاريخ     ← «الفترات المحاسبية» (الفترة الجارية افتراضاً)
      • اللغة والاتجاه   ← «اللغات»
      • الألوان والخط    ← «مظهر النظام» عبر `--app-*`
      • القوالب          ← «قوالب المبيعات» في قائمة المطبوعات

    ما أُزيل من الفلاتر: `due_date` و`source` و`custom_field`
    و`shipping_option` و`post_shift` و`order_source` — ستّة حقول تُرسَل
    إلى أعمدة لا وجود لها في `credit_notifications`، فأيّ واحد منها
    يُسقط الشاشة بخطأ SQL.
--}}

@extends('sales::master')

@section('title', __('sales_credit_note.index_title'))

@section('content')
    @include('sales::partials.settings-theme')
    @include('components.hint-tips')
    @include('sales::partials.table-menu')

    @php
        $hasFilters = request()->hasAny([
            'client_id', 'credit_number', 'invoice_id', 'status', 'branch',
            'total_from', 'total_to', 'from_date', 'to_date', 'created_by', 'sales_person_user', 'item_search',
        ]);

        // «بحث متقدّم» يُفتح وحده حين يكون فيه فلترٌ مضبوط
        $advancedActive = request()->filled('total_from') || request()->filled('total_to')
            || request()->filled('created_by') || request()->filled('sales_person_user')
            || request()->filled('item_search');

        $statuses = [
            1 => __('sales_credit_note.status.draft'),
            2 => __('sales_credit_note.status.pending'),
            3 => __('sales_credit_note.status.approved'),
            5 => __('sales_credit_note.status.cancelled'),
        ];
    @endphp

    <div class="content-body sl-scope">

        @section('page-actions')
            @permission('sales.credit_notes.create')
                <a href="{{ route('CreditNotes.create') }}" class="btn btn-primary">
                    <i class="feather icon-plus"></i> {{ __('sales_credit_note.new') }}
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
            'title' => __('sales_credit_note.index_title'),
            'icon' => 'plus-circle',
            'sub' => __('sales_credit_note.index_subtitle'),
            'crumbs' => [
                __('sales_settings.ui.home') => route('dashboard'),
                __('sales_credit_note.plural') => null,
            ],
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')
        @include('layouts.alerts.warning')
        @include('sales::components.form-errors')

        {{-- ══════════ إرشادُ الشاشة ══════════
             قسما «ما الإشعار» و«إلى أين يذهب المبلغ» يفصلان الإشعارَ الدائن
             عن المرتجع — وهو أكثرُ ما يُخلَط في هذه الشاشة. --}}
        @include('sales::components.guide', [
            'key' => 'sales-credit-notes.index',
            'open' => false,
            'title' => __('sales_credit_note.guide.index.title'),
            'lead' => __('sales_credit_note.guidance.index'),
            'blocks' => [
                __('sales_credit_note.guide.what_is.title') => [
                    __('sales_credit_note.guide.what_is.a'),
                    __('sales_credit_note.guide.what_is.b'),
                    __('sales_credit_note.guide.what_is.c'),
                ],
                __('sales_credit_note.guide.money_flow.title') => [
                    __('sales_credit_note.guide.money_flow.a'),
                    __('sales_credit_note.guide.money_flow.b'),
                    __('sales_credit_note.guide.money_flow.c'),
                ],
                __('sales_credit_note.guide.statuses.title') => [
                    __('sales_credit_note.guide.statuses.a'),
                    __('sales_credit_note.guide.statuses.b'),
                    __('sales_credit_note.guide.statuses.c'),
                ],
                __('sales_credit_note.guide.stats.title') => [
                    __('sales_credit_note.guide.stats.a'),
                    __('sales_credit_note.guide.stats.b'),
                ],
                __('sales_subscription.guide.title') => [
                    __('sales_subscription.guide.a'),
                    __('sales_subscription.guide.b'),
                    __('sales_subscription.guide.c'),
                    __('sales_subscription.guide.d'),
                ],
                __('sales_credit_note.guide.settings.title') => [
                    __('sales_credit_note.guide.settings.a'),
                    __('sales_credit_note.guide.settings.b'),
                    __('sales_credit_note.guide.settings.c'),
                    __('sales_credit_note.guide.settings.d'),
                ],
            ],
        ])

        @include('sales::components.subscription-bar', ['limit' => $subscriptionLimit ?? null])

        {{-- ══════════ الإحصاءات ══════════ --}}
        <div class="sl-stats">
            <div class="sl-stat">
                <span class="sl-stat__icon sl-stat__icon--danger"><i class="feather icon-plus-circle"></i></span>
                <div class="sl-stat__body">
                    <p class="sl-stat__value" id="credits-total-stat">
                        {{ \App\Support\CurrencySettings::format($creditsTotal ?? 0) }}
                    </p>
                    <p class="sl-stat__label">{{ __('sales_credit_note.stats.total') }} — {{ __('sales_credit_note.stats_hints.total') }}</p>
                </div>
            </div>

            <div class="sl-stat">
                <span class="sl-stat__icon"><i class="feather icon-file-text"></i></span>
                <div class="sl-stat__body">
                    <p class="sl-stat__value" id="credits-count-stat">{{ $creditsCount ?? 0 }}</p>
                    <p class="sl-stat__label">{{ __('sales_credit_note.stats.count') }} — {{ __('sales_credit_note.stats_hints.count') }}</p>
                </div>
            </div>

            <div class="sl-stat">
                <span class="sl-stat__icon sl-stat__icon--info"><i class="feather icon-calendar"></i></span>
                <div class="sl-stat__body">
                    <p class="sl-stat__value sl-num" style="font-size:1.05rem">
                        {{ $range['start'] ?? '' }} — {{ $range['end'] ?? '' }}
                    </p>
                    <p class="sl-stat__label">{{ __('sales_invoice.filters.date_range') }} — {{ __('sales_credit_note.stats_hints.range') }}</p>
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

                <form id="searchForm" data-search-url="{{ route('CreditNotes.index') }}">
                    @csrf

                    <div class="row">
                        <div class="col-lg-3 col-md-6">
                            <div class="form-group">
                                <label for="credit_number">{{ __('sales_credit_note.filters.number') }}</label>
                                <input type="text" id="credit_number" name="credit_number" class="form-control"
                                       value="{{ request('credit_number') }}"
                                       placeholder="{{ __('sales_invoice.filters.keywords_placeholder') }}">
                                <small class="sl-help">{{ __('sales_credit_note.filter_hints.number') }}</small>
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
                                <small class="sl-help">{{ __('sales_credit_note.filter_hints.client') }}</small>
                            </div>
                        </div>

                        <div class="col-lg-2 col-md-6">
                            <div class="form-group">
                                <label for="status">{{ __('sales_invoice.table.status') }}</label>
                                <select id="status" name="status" class="form-control">
                                    <option value="">{{ __('sales_invoice.filters.all_payment_statuses') }}</option>
                                    @foreach ($statuses as $value => $label)
                                        <option value="{{ $value }}"
                                            {{ (string) request('status') === (string) $value ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                                <small class="sl-help">{{ __('sales_credit_note.filter_hints.status') }}</small>
                            </div>
                        </div>

                        {{-- نطاق التاريخ في الصفّ الظاهر: يصفّي كلَّ زيارةٍ بالفترة الجارية،
                             وكان مطويّاً في «بحث متقدّم» --}}
                        <div class="col-lg-4 col-md-6">
                            <div class="form-group">
                                <label for="from_date">{{ __('sales_invoice.filters.date_range') }}</label>
                                <div class="input-group">
                                    <input type="date" id="from_date" name="from_date" class="form-control"
                                           value="{{ request('from_date', $range['start'] ?? '') }}">
                                    <input type="date" name="to_date" class="form-control"
                                           value="{{ request('to_date', $range['end'] ?? '') }}">
                                </div>
                                <small class="sl-help">{{ __('sales_credit_note.filter_hints.date_range') }}</small>
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
                                </div>
                            </div>

                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label for="created_by">{{ __('sales_invoice.filters.added_by') }}</label>
                                    <select id="created_by" name="created_by" class="form-control select2">
                                        <option value="">{{ __('sales_invoice.filters.all') }}</option>
                                        @foreach ($users as $u)
                                            <option value="{{ $u->id }}"
                                                {{ request('created_by') == $u->id ? 'selected' : '' }}>
                                                {{ $u->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            {{-- «الموظف المسؤول» غير «أضيفت بواسطة»: الأول عمود
                                 `employee_id` المختوم على الإشعار، والثاني حساب
                                 من أدخله. والمحاسب يُدخل عن المندوب. --}}
                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label for="sales_person_user">{{ __('sales_invoice.filters.sales_person') }}</label>
                                    <select id="sales_person_user" name="sales_person_user" class="form-control select2">
                                        <option value="">{{ __('sales_invoice.filters.all') }}</option>
                                        @foreach ($employees_sales_person ?? [] as $employee)
                                            <option value="{{ $employee->id }}"
                                                {{ request('sales_person_user') == $employee->id ? 'selected' : '' }}>
                                                {{ $employee->full_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <small class="sl-help">{{ __('sales_invoice.filter_hints.sales_person') }}</small>
                                </div>
                            </div>

                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label for="item_search">{{ __('sales_credit_note.filters.item') }}</label>
                                    <input type="text" id="item_search" name="item_search" class="form-control"
                                           value="{{ request('item_search') }}">
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
                    <i class="feather icon-list"></i> {{ __('sales_credit_note.plural') }}
                    <span class="sl-count">{{ $credits->total() }}</span>
                </h6>
                <div class="spinner-border spinner-border-sm text-primary d-none" id="loading-indicator" role="status"></div>
            </div>

            <div id="results-container">
                @include('sales::creted_note.partials.table', [
                    'credits' => $credits,
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
                fileName: @json(__('sales_credit_note.index_title')),
                title: @json(__('sales_credit_note.index_title')),
                subTitle: @json(config('app.name')),
                // الإجمالي هو العمود السادس (رقم، عميل، فرع، الفاتورة الأصلية، تاريخ، إجمالي)
                numericColumns: [5],
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
        const creditRoutes = {
            index: @json(route('CreditNotes.index')),
            cancel: @json(route('CreditNotes.cancel', ['id' => ':id'])),
        };

        const creditToken = @json(csrf_token());

        $(function () {
            if ($.fn.select2) {
                $('#searchForm .select2').select2({
                    placeholder: @json(__('sales_invoice.filters.all')),
                    allowClear: true,
                    dir: @json(\App\Support\LanguageSettings::direction()),
                    width: '100%',
                });
            }

            /* التصفية والترقيم والمسح وزرّ الرجوع — `sales-index-filters.js` */
            SalesIndexFilters.bind({
                url: creditRoutes.index,
                failText: @json(__('sales_settings.ui.load_failed')),
                onData: function (response) {
                    $('#credits-total-stat').text(response.credits_total);
                    $('#credits-count-stat').text(response.credits_count);
                },
            });

            /* الإلغاء لا الحذف: الإشعار الدائن مستندٌ محاسبيّ صدر وله قيد،
               فيُلغى بقيدٍ عاكس ويبقى في السجلّ */
            $('#results-container').on('click', '.cancel-credit', function (e) {
                e.preventDefault();

                const id = $(this).data('id');
                const code = $(this).data('code');

                Swal.fire({
                    icon: 'warning',
                    title: @json(__('sales_credit_note.actions.cancel')),
                    text: '#' + code + ' — ' + @json(__('sales_credit_note.cancel_hint')),
                    showCancelButton: true,
                    confirmButtonText: @json(__('sales_credit_note.actions.cancel')),
                    cancelButtonText: @json(__('sales_settings.ui.cancel')),
                    reverseButtons: true,
                }).then(function (result) {
                    if (!result.isConfirmed) return;

                    $.post(creditRoutes.cancel.replace(':id', id), { _token: creditToken })
                        .done(() => loadData())
                        .fail(xhr => Swal.fire({
                            icon: 'error',
                            title: @json(__('sales_credit_note.actions.cancel')),
                            text: (xhr.responseJSON && xhr.responseJSON.message) || '',
                        }));
                });
            });

        });
    </script>
@endsection
