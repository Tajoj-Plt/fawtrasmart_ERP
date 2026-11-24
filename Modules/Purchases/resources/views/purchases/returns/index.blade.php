{{--
    قائمة مرتجعات الشراء — أُعيد بناؤها على نمط تصميم «طلبات الشراء»
    (`ordersPurchase/index.blade.php`) توحيداً لشكل شاشات فهرسة وحدة
    المشتريات كلّها.

    لا زرّ «إضافة جديد» هنا عن قصد: مرتجع الشراء مستندٌ تابع لفاتورة، يُنشأ
    من شاشة الفاتورة وحدها («خيارات ← إنشاء مرتجع»)، فلا يُفتح له نموذج فارغ
    ولا تُنشأ فاتورةٌ مرتجعةٌ بلا أصل — الإجراء هنا رابطٌ لفهرس الفواتير بدلاً.

    منطق البحث/الترقيم/الحذف يبقى سكربتها الخاصّ بلا تغيير — فقط الغلاف
    البصري تبدّل.

    ما يأتي من الإعدادات:
      • الألوان والخط           ← «مظهر النظام» عبر متغيّرات CSS
      • العملة والكسور والفواصل ← «العملات»
      • صيغة التاريخ والاتجاه   ← إعدادات اللغة النشطة
      • الحسابات في قائمة التصفية ← «الحسابات المحاسبية»
--}}
@extends('purchases::master')

@section('title')
    {{ __('purchase_cycle.sections.purchase_return') }}
@stop

@section('content')
    @include('purchases::purchases.purchase_requests.partials.sales-theme')
    @include('components.hint-tips')
    @include('purchases::purchases.purchase_requests.partials.row-menu-fix')

    <div class="content-body sl-scope">

        @section('page-actions')
            <a href="{{ route('invoicePurchases.index') }}" class="btn btn-outline-primary">
                <i class="fas fa-file-invoice"></i> {{ __('purchase_cycle.sections.purchase_invoice') }}
            </a>

            <div id="loading-spinner" class="spinner-border spinner-border-sm text-primary d-none align-self-center" role="status" aria-hidden="true"></div>
        @endsection

        @include('purchases::purchases.purchase_requests.partials.page-head', [
            'title' => __('purchase_cycle.sections.purchase_return'),
            'icon' => 'undo',
            'crumbs' => [
                __('purchase_orders.breadcrumb.home') => route('purchases.dashboard'),
                __('purchase_cycle.sections.purchase_return') => null,
            ],
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        {{-- لوحة إرشاد الشاشة — مطويّةً افتراضياً --}}
        @include('purchases::components.guide', [
            'key' => 'purchase-returns.index',
            'title' => __('purchase_cycle.guide.return.index.title'),
            'lead' => __('purchase_cycle.guide.return.what_is'),
            'open' => false,
            'blocks' => [
                __('purchase_cycle.guide.cycle.title') => [
                    __('purchase_cycle.guide.cycle.a'),
                    __('purchase_cycle.guide.cycle.b'),
                    __('purchase_cycle.guide.cycle.c'),
                ],
                __('purchase_cycle.guide.return.source.title') => [
                    __('purchase_cycle.guide.return.source.a'),
                    __('purchase_cycle.guide.return.source.b'),
                ],
                __('purchase_cycle.guide.return.effect.title') => [
                    __('purchase_cycle.guide.return.effect.a'),
                    __('purchase_cycle.guide.return.effect.b'),
                    __('purchase_cycle.guide.return.effect.c'),
                ],
                __('purchase_cycle.guide.settings.title') => [
                    __('purchase_cycle.guide.settings.a'),
                    __('purchase_cycle.guide.settings.b'),
                    __('purchase_cycle.guide.settings.c'),
                    __('purchase_cycle.guide.settings.d'),
                ],
            ],
        ])


        {{-- ══════════ الإحصاءات ══════════ --}}
        <div class="sl-stats">
            <div class="sl-stat">
                <span class="sl-stat__icon"><i class="fas fa-undo"></i></span>
                <div class="sl-stat__body">
                    <p class="sl-stat__value">{{ \App\Support\LanguageSettings::formatNumber($stats['total'] ?? 0, 0) }}</p>
                    <p class="sl-stat__label">{{ __('purchase_cycle.sections.purchase_return') }} — {{ __('purchase_cycle.stats_hints.total') }}</p>
                </div>
            </div>

            <div class="sl-stat">
                <span class="sl-stat__icon sl-stat__icon--success"><i class="fas fa-check-circle"></i></span>
                <div class="sl-stat__body">
                    <p class="sl-stat__value">{{ \App\Support\LanguageSettings::formatNumber($stats['paid'] ?? 0, 0) }}</p>
                    <p class="sl-stat__label">{{ __('purchase_cycle.invoice.paid') }} — {{ __('purchase_cycle.stats_hints.paid') }}</p>
                </div>
            </div>

            <div class="sl-stat">
                <span class="sl-stat__icon sl-stat__icon--warning"><i class="fas fa-adjust"></i></span>
                <div class="sl-stat__body">
                    <p class="sl-stat__value">{{ \App\Support\LanguageSettings::formatNumber($stats['partial'] ?? 0, 0) }}</p>
                    <p class="sl-stat__label">{{ __('purchase_cycle.invoice.partial') }} — {{ __('purchase_cycle.stats_hints.partial') }}</p>
                </div>
            </div>

            <div class="sl-stat">
                <span class="sl-stat__icon sl-stat__icon--danger"><i class="fas fa-times-circle"></i></span>
                <div class="sl-stat__body">
                    <p class="sl-stat__value">{{ \App\Support\LanguageSettings::formatNumber($stats['unpaid'] ?? 0, 0) }}</p>
                    <p class="sl-stat__label">{{ __('purchase_cycle.invoice.unpaid') }} — {{ __('purchase_cycle.stats_hints.unpaid') }}</p>
                </div>
            </div>
        </div>

        {{-- ══════════ البحث والتصفية ══════════ --}}
        <div class="card sl-filters">
            <div class="card-body">
                <div class="sl-filters__head">
                    <h6 class="sl-filters__title">
                        <i class="fas fa-filter"></i> {{ __('purchase_cycle.search.heading') }}
                    </h6>

                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse"
                        data-bs-target="#advancedSearchForm" aria-expanded="false">
                        <i class="fas fa-sliders-h"></i> {{ __('purchase_orders.search.advanced') }}
                    </button>
                </div>

                <form class="form" id="search-form">
                    @csrf
                    <div class="row">

                        <div class="col-lg-4 col-md-6">
                            <div class="form-group">
                                <label for="supplier_id">{{ __('purchase_cycle.fields.supplier') }}</label>
                                <select name="employee_search" class="form-control select2 search-input" id="supplier_id">
                                    <option value="">{{ __('purchase_cycle.fields.supplier') }}</option>
                                    @foreach ($suppliers as $supplier)
                                        <option value="{{ $supplier->id }}">{{ $supplier->trade_name }}</option>
                                    @endforeach
                                </select>
                                <span class="po-help">{{ __('purchase_cycle.filter_hints.supplier') }}</span>
                            </div>
                        </div>

                        <div class="col-lg-4 col-md-6">
                            <div class="form-group">
                                <label for="number_invoice">{{ __('purchase_cycle.fields.code') }}</label>
                                <input type="text" class="form-control search-input" name="number_invoice"
                                    id="number_invoice" placeholder="{{ __('purchase_orders.placeholders.search_code') }}">
                                <span class="po-help">{{ __('purchase_cycle.filter_hints.code') }}</span>
                            </div>
                        </div>

                        <div class="col-lg-4 col-md-6">
                            <div class="form-group">
                                <label for="payment_status">{{ __('purchase_cycle.invoice.payment_status') }}</label>
                                <select class="form-control search-input" name="payment_status" id="payment_status">
                                    <option value="">{{ __('purchase_cycle.statuses.all') }}</option>
                                    <option value="paid">{{ __('purchase_cycle.invoice.paid') }}</option>
                                    <option value="partial">{{ __('purchase_cycle.invoice.partial') }}</option>
                                    <option value="unpaid">{{ __('purchase_cycle.invoice.unpaid') }}</option>
                                </select>
                                <span class="po-help">{{ __('purchase_cycle.filter_hints.payment_status') }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="collapse" id="advancedSearchForm">
                        <div class="row">
                            <div class="col-lg-3 col-md-6">
                                <div class="form-group">
                                    <label for="start_date_from">{{ __('purchase_cycle.invoice.date_from') }}</label>
                                    {{-- مملوءٌ سلفاً بالفترة المحاسبية الجارية، فيرى المستخدم
                                         على أي مدى يقرأ قبل أن يسأل --}}
                                    <input type="date" class="form-control search-input" name="start_date_from"
                                        id="start_date_from"
                                        value="{{ request('start_date_from', $range['start'] ?? '') }}">
                                </div>
                            </div>

                            <div class="col-lg-3 col-md-6">
                                <div class="form-group">
                                    <label for="start_date_to">{{ __('purchase_cycle.invoice.date_to') }}</label>
                                    <input type="date" class="form-control search-input" name="start_date_to"
                                        id="start_date_to"
                                        value="{{ request('start_date_to', $range['end'] ?? '') }}">
                                    <span class="po-help">{{ __('purchase_cycle.filter_hints.dates') }}</span>
                                    @if ($range['is_default'] ?? false)
                                        <span class="po-help">{{ __('purchase_cycle.filter_hints.period_default') }}</span>
                                    @endif
                                </div>
                            </div>

                            <div class="col-lg-3 col-md-6">
                                <div class="form-group">
                                    <label for="created_by">{{ __('purchase_cycle.invoice.created_by') }}</label>
                                    <select class="form-control search-input" name="created_by" id="created_by">
                                        <option value="">{{ __('purchase_cycle.invoice.created_by') }}</option>
                                        @foreach ($users as $user)
                                            <option value="{{ $user->id }}">{{ $user->name }}</option>
                                        @endforeach
                                    </select>
                                    <span class="po-help">{{ __('purchase_cycle.filter_hints.created_by') }}</span>
                                </div>
                            </div>

                            {{-- «تابع لـ» والموظّف — العمودان المختومان على المستند
                                 لحظةَ إنشائه، لا فرعُ منشئه اليوم ولا حسابُه.
                                 وكلُّ خيارٍ يحمل مساره من الجذر، واختيارُ عقدةٍ
                                 يشمل كلَّ ما تحتها. --}}
                            <div class="col-lg-3 col-md-6">
                                <div class="form-group">
                                    <label for="employee_id">{{ __('purchase_cycle.scope_filters.employee') }}</label>
                                    <select class="form-control search-input" name="employee_id" id="employee_id">
                                        <option value="">{{ __('purchase_cycle.scope_filters.all_employees') }}</option>
                                        @foreach ($employees ?? [] as $employeeOption)
                                            <option value="{{ $employeeOption->id }}"
                                                {{ request('employee_id') == $employeeOption->id ? 'selected' : '' }}>
                                                {{ $employeeOption->full_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <span class="po-help">{{ __('purchase_cycle.filter_hints.employee') }}</span>
                                </div>
                            </div>

                            <div class="col-lg-3 col-md-6">
                                <div class="form-group">
                                    <label for="receiving_status">{{ __('purchase_cycle.goods.status') }}</label>
                                    <select class="form-control search-input" name="receiving_status" id="receiving_status">
                                        <option value="">{{ __('purchase_cycle.statuses.all') }}</option>
                                        <option value="received">{{ __('purchase_cycle.goods.returned') }}</option>
                                        <option value="partially_received">{{ __('purchase_cycle.goods.partially_returned') }}</option>
                                        <option value="not_received">{{ __('purchase_cycle.goods.not_returned') }}</option>
                                    </select>
                                    <span class="po-help">{{ __('purchase_cycle.filter_hints.goods_status') }}</span>
                                </div>
                            </div>

                            <div class="col-lg-3 col-md-6">
                                <div class="form-group">
                                    {{-- الحسابات من «الحسابات المحاسبية» — كانت القائمة
                                         تُبنى من متغيّر لا يمرّره المتحكّم فتخرج فارغة --}}
                                    <label for="account_id">{{ __('purchase_cycle.invoice.account') }}</label>
                                    <select class="form-control search-input" name="account_id" id="account_id">
                                        <option value="">{{ __('purchase_cycle.invoice.account') }}</option>
                                        @foreach ($accounts ?? [] as $account)
                                            <option value="{{ $account->id }}">{{ $account->name }}</option>
                                        @endforeach
                                    </select>
                                    <span class="po-help">{{ __('purchase_cycle.filter_hints.account') }}</span>
                                </div>
                            </div>


                            <div class="col-lg-3 col-md-6">
                                <div class="form-group">
                                    <label for="grand_total_min">{{ __('purchase_cycle.fields.grand_total') }} ({{ __('purchase_cycle.invoice.date_from') }})</label>
                                    <input type="number" step="0.01" class="form-control search-input"
                                        name="grand_total_min" id="grand_total_min">
                                </div>
                            </div>

                            <div class="col-lg-3 col-md-6">
                                <div class="form-group">
                                    <label for="grand_total_max">{{ __('purchase_cycle.fields.grand_total') }} ({{ __('purchase_cycle.invoice.date_to') }})</label>
                                    <input type="number" step="0.01" class="form-control search-input"
                                        name="grand_total_max" id="grand_total_max">
                                    <span class="po-help">{{ __('purchase_cycle.filter_hints.grand_total') }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="sl-filters__foot">
                        <button type="button" id="clear-filters" class="btn btn-outline-secondary">
                            <i class="fas fa-times"></i> {{ __('purchase_orders.search.clear') }}
                        </button>
                        <button type="submit" form="search-form" id="search-btn" class="btn btn-primary">
                            <i class="fas fa-search"></i> {{ __('purchase_cycle.actions.search') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        @include('purchases::purchases.partials.list-scope')

        {{-- ══════════ النتائج ══════════ --}}
        <div class="card sl-table-card" id="reportCard">
            <div class="sl-table-head">
                <h6 class="sl-table-head__title">
                    <i class="fas fa-list"></i> {{ __('purchase_cycle.sections.purchase_return') }}
                    <span class="sl-count results-info">
                        {{ __('purchase_cycle.search.results_count', ['count' => \App\Support\LanguageSettings::formatNumber($returnsData->total(), 0)]) }}
                    </span>
                </h6>

                <div class="d-flex align-items-center gap-2">
                    <label for="perPage" class="sl-help mb-0">{{ __('purchase_cycle.invoice.per_page') }}</label>
                    <select id="perPage" class="form-control form-control-sm" style="width: auto;">
                        @foreach ([10, 25, 50, 100] as $size)
                            <option value="{{ $size }}" @selected(request('per_page', 10) == $size)>{{ $size }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="card-body">
                <div id="table-container">
                    @include('purchases::purchases.returns.partials.table', ['returnsData' => $returnsData])
                </div>
            </div>

            <div class="sl-table-foot" id="pagination-top">
                @include('purchases::purchases.returns.partials.pagination', ['returnsData' => $returnsData])
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @php
        // `@json` لا يحتمل مصفوفةً حرفيةً متعدّدة الأسطر ولا نداءً فيه فاصلة —
        // يقصّها عند أول فاصلة ويظنّها وسيطاً ثانياً. تُبنى هنا ثم تُمرَّر.
        $returnListConfig = [
            'routes' => [
                'index' => route('ReturnsInvoice.index'),
                'destroy' => route('ReturnsInvoice.destroy', ['id' => '__ID__']),
            ],
            'rtl' => \App\Support\LanguageSettings::isRtl(),
            'i18n' => [
                'deleteTitle' => __('purchase_orders.confirm.delete_title'),
                'deleteText' => __('purchase_orders.confirm.delete_text', ['code' => '']),
                'yesDelete' => __('purchase_orders.confirm.yes_delete'),
                'no' => __('purchase_orders.confirm.no'),
                'deleted' => __('purchase_cycle.messages.deleted', ['document' => __('purchase_cycle.documents.purchase_return')]),
                'deleteFailed' => __('purchase_cycle.messages.delete_failed', ['document' => __('purchase_cycle.documents.purchase_return')]),
                'loadFailed' => __('purchase_cycle.messages.pdf_failed', ['error' => '']),
                'results' => __('purchase_cycle.search.results_count', ['count' => '__COUNT__']),
                'noResults' => __('purchase_cycle.empty.filtered'),
            ],
        ];
    @endphp

    <script>
        window.returnListConfig = @json($returnListConfig);

        $(document).ready(function() {
            let searchTimeout;

            const routes = window.returnListConfig.routes;
            const strings = window.returnListConfig.i18n;

            // تهيئة Select2 لحقول البحث المنسدلة
            ['#supplier_id', '#created_by', '#branch', '#employee_id', '#account_id'].forEach(function(selector) {
                if ($(selector).length) {
                    $(selector).select2({
                        allowClear: true,
                        width: '100%',
                        dir: window.returnListConfig.rtl ? 'rtl' : 'ltr',
                    });
                }
            });

            $('#perPage').on('change', function() {
                performSearch(1);
            });

            $('.search-input').on('input change', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(function() {
                    performSearch();
                }, 500);
            });

            $('#supplier_id, #created_by, #branch, #employee_id, #account_id').on('select2:select select2:clear', function() {
                performSearch();
            });

            $('#search-form').on('submit', function(e) {
                e.preventDefault();
                performSearch();
            });

            $('#clear-filters').on('click', function() {
                $('#search-form')[0].reset();
                $('#supplier_id, #created_by, #branch, #employee_id, #account_id').val(null).trigger('change');
                performSearch();
            });

            $(document).on('click', '.pagination a', function(e) {
                e.preventDefault();
                const page = new URL($(this).attr('href'), window.location.origin).searchParams.get('page');
                performSearch(page);
            });

            function performSearch(page = 1) {
                showLoading();

                let formData = $('#search-form').serialize();
                formData += '&page=' + page;
                formData += '&per_page=' + $('#perPage').val();
                formData += '&is_ajax=1';

                $.ajax({
                    url: routes.index,
                    method: 'GET',
                    data: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    cache: false,
                    success: function(response) {
                        if (response.success) {
                            $('#table-container').html(response.data);
                            $('#pagination-top').html(response.pagination || '');
                            updateResultsInfo(response);
                            initializeEvents();
                        }
                    },
                    error: function() {
                        $('#table-container').html(
                            '<div class="alert alert-danger text-center mb-0">' + strings.loadFailed + '</div>'
                        );
                    },
                    complete: hideLoading,
                });
            }

            function showLoading() {
                $('#table-container').css('opacity', '0.6');
                $('#loading-spinner').removeClass('d-none');
                $('#search-btn').prop('disabled', true);
            }

            function hideLoading() {
                $('#table-container').css('opacity', '1');
                $('#loading-spinner').addClass('d-none');
                $('#search-btn').prop('disabled', false);
            }

            function updateResultsInfo(response) {
                $('.results-info').text(
                    response.total > 0 ?
                    strings.results.replace('__COUNT__', response.total) :
                    strings.noResults
                );
            }

            function initializeEvents() {
                $('.delete-return').off('click').on('click', function(e) {
                    e.preventDefault();
                    const returnId = $(this).data('id');

                    Swal.fire({
                        title: strings.deleteTitle,
                        text: strings.deleteText,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#dc3545',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: strings.yesDelete,
                        cancelButtonText: strings.no,
                    }).then(function(result) {
                        if (result.isConfirmed) {
                            deleteReturn(returnId);
                        }
                    });
                });

                $('#selectAll').off('change').on('change', function() {
                    $('.order-checkbox').prop('checked', $(this).prop('checked'));
                });

                $('.order-checkbox').off('change').on('change', function() {
                    const total = $('.order-checkbox').length;
                    const checked = $('.order-checkbox:checked').length;
                    $('#selectAll').prop('checked', total === checked);
                    $('#selectAll').prop('indeterminate', checked > 0 && checked < total);
                });
            }

            function deleteReturn(id) {
                $.ajax({
                    url: routes.destroy.replace('__ID__', id),
                    method: 'DELETE',
                    data: {
                        _token: $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function() {
                        Swal.fire(strings.deleted, '', 'success');
                        performSearch();
                    },
                    error: function() {
                        Swal.fire(strings.deleteFailed, '', 'error');
                    },
                });
            }

            initializeEvents();
        });
    </script>
@endsection
