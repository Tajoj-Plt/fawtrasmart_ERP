{{--
    قائمة مدفوعات الموردين — أُعيد بناؤها على نمط تصميم «طلبات الشراء»
    (`ordersPurchase/index.blade.php`) توحيداً لشكل شاشات فهرسة وحدة
    المشتريات كلّها.

    لا زرَّ إنشاءٍ هنا عن قصد: الدفعة تُسجَّل من فاتورتها («خيارات ← إضافة
    دفعة»)، والدفع المجمَّع من شاشة المورّد — وكلاهما يحتاج سياقاً لا توفّره
    هذه القائمة؛ الإجراء هنا رابطٌ لفهرس الموردين بدلاً.

    منطق البحث/الترقيم/الحذف/الإلغاء يبقى سكربتها الخاصّ بلا تغيير (أسماء
    حقول البحث كما يقرأها `applyPaymentFilters()` في المتحكّم) — فقط الغلاف
    البصري تبدّل.

    ما يأتي من الإعدادات:
      • الألوان والخط           ← «مظهر النظام» عبر متغيّرات CSS
      • العملة والكسور والفواصل ← «العملات»
      • صيغة التاريخ والاتجاه   ← إعدادات اللغة النشطة
      • قوالب المطبوعة          ← «قوالب المستندات» (نوع supplier_payment)
--}}
@extends('purchases::master')

@section('title')
    {{ __('purchase_cycle.payment.section') }}
@stop

@section('content')
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @include('purchases::purchases.purchase_requests.partials.sales-theme')
    @include('components.hint-tips')
    @include('purchases::purchases.purchase_requests.partials.row-menu-fix')

    <div class="content-body sl-scope">

        @section('page-actions')
            <a href="{{ route('SupplierManagement.index') }}" class="btn btn-outline-primary">
                <i class="fas fa-truck"></i> {{ __('purchase_cycle.supplier.section') }}
            </a>

            <div id="loading-spinner" class="spinner-border spinner-border-sm text-primary d-none align-self-center" role="status" aria-hidden="true"></div>
        @endsection

        @include('purchases::purchases.purchase_requests.partials.page-head', [
            'title' => __('purchase_cycle.payment.section'),
            'icon' => 'credit-card',
            'crumbs' => [
                __('purchase_orders.breadcrumb.home') => route('purchases.dashboard'),
                __('purchase_cycle.payment.section') => null,
            ],
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        {{-- ══════════ إرشادُ الشاشة ══════════ --}}
        @include('purchases::components.guide', [
            'key' => 'purchase-payments.index',
            'open' => false,
            'title' => __('purchase_cycle.payment.guide.index_title'),
            'lead' => __('purchase_cycle.payment.guide.lead_index'),
            'blocks' => [
                __('purchase_cycle.payment.guide.effects.title') => [
                    __('purchase_cycle.payment.guide.effects.a'),
                    __('purchase_cycle.payment.guide.effects.b'),
                    __('purchase_cycle.payment.guide.effects.c'),
                ],
                __('purchase_cycle.payment.guide.blockers.title') => [
                    __('purchase_cycle.payment.guide.blockers.a'),
                    __('purchase_cycle.payment.guide.blockers.b'),
                    __('purchase_cycle.payment.guide.blockers.c'),
                ],
            ],
        ])

        {{-- ══════════ الإحصاءات ══════════ --}}
        <div class="sl-stats">
            <div class="sl-stat">
                <span class="sl-stat__icon"><i class="fas fa-credit-card"></i></span>
                <div class="sl-stat__body">
                    <p class="sl-stat__value">{{ \App\Support\LanguageSettings::formatNumber($stats['total'] ?? 0, 0) }}</p>
                    <p class="sl-stat__label">{{ __('purchase_cycle.payment.section') }}</p>
                </div>
            </div>

            <div class="sl-stat">
                <span class="sl-stat__icon sl-stat__icon--success"><i class="fas fa-check-circle"></i></span>
                <div class="sl-stat__body">
                    <p class="sl-stat__value">{{ \App\Support\LanguageSettings::formatNumber($stats['completed'] ?? 0, 0) }}</p>
                    <p class="sl-stat__label">{{ __('purchase_cycle.payment.completed') }}</p>
                </div>
            </div>

            <div class="sl-stat">
                <span class="sl-stat__icon sl-stat__icon--warning"><i class="fas fa-adjust"></i></span>
                <div class="sl-stat__body">
                    <p class="sl-stat__value">{{ \App\Support\LanguageSettings::formatNumber($stats['incomplete'] ?? 0, 0) }}</p>
                    <p class="sl-stat__label">{{ __('purchase_cycle.payment.incomplete') }}</p>
                </div>
            </div>

            <div class="sl-stat">
                <span class="sl-stat__icon sl-stat__icon--danger"><i class="fas fa-times-circle"></i></span>
                <div class="sl-stat__body">
                    <p class="sl-stat__value">{{ \App\Support\LanguageSettings::formatNumber($stats['cancelled'] ?? 0, 0) }}</p>
                    <p class="sl-stat__label">{{ __('purchase_cycle.payment.cancelled') }}</p>
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

                <form class="form" id="searchForm">
                    @csrf
                    <div class="row">

                        {{-- «تابع لـ» — الشجرةُ نفسُها في التقارير والنماذج؛ الحقلُ المخفي
                             `branch` يقرؤه `scopeToBranchNode()`، وصنفُ `search-input`
                             يربطه بمستمع إعادة التحميل --}}
                        @include('reports.partials.affiliation-tree', [
                            'treeNodes' => \Modules\Purchases\Support\HeaderPickers::treeNodes(),
                            'name' => 'branch',
                            'showExact' => false,
                            'label' => __('branches.node_filter.column'),
                            'treeInputClass' => 'search-input',
                        ])
                        <div class="col-lg-4 col-md-6">
                            <div class="form-group">
                                <label for="supplier">{{ __('purchase_cycle.fields.supplier') }}</label>
                                <select name="supplier" class="form-control search-input" id="supplier">
                                    <option value="">{{ __('purchase_cycle.fields.supplier') }}</option>
                                    @foreach ($suppliers as $supplier)
                                        <option value="{{ $supplier->id }}" @selected(request('supplier') == $supplier->id)>
                                            {{ $supplier->trade_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-lg-4 col-md-6">
                            <div class="form-group">
                                <label for="invoice_number">{{ __('purchase_cycle.payment.invoice') }}</label>
                                <input type="text" class="form-control search-input" name="invoice_number"
                                    id="invoice_number" value="{{ request('invoice_number') }}">
                            </div>
                        </div>

                        <div class="col-lg-4 col-md-6">
                            <div class="form-group">
                                <label for="payment_number">{{ __('purchase_cycle.payment.number') }}</label>
                                <input type="text" class="form-control search-input" name="payment_number"
                                    id="payment_number" value="{{ request('payment_number') }}">
                            </div>
                        </div>
                    </div>

                    <div class="collapse" id="advancedSearchForm">
                        <div class="row">
                            <div class="col-lg-3 col-md-6">
                                <div class="form-group">
                                    <label for="from_date">{{ __('purchase_cycle.invoice.date_from') }}</label>
                                    <input type="date" class="form-control search-input" name="from_date" id="from_date"
                                        value="{{ request('from_date') }}">
                                </div>
                            </div>

                            <div class="col-lg-3 col-md-6">
                                <div class="form-group">
                                    <label for="to_date">{{ __('purchase_cycle.invoice.date_to') }}</label>
                                    <input type="date" class="form-control search-input" name="to_date" id="to_date"
                                        value="{{ request('to_date') }}">
                                </div>
                            </div>

                            <div class="col-lg-3 col-md-6">
                                <div class="form-group">
                                    <label for="added_by">{{ __('purchase_cycle.invoice.created_by') }}</label>
                                    <select name="added_by" class="form-control search-input" id="added_by">
                                        <option value="">{{ __('purchase_cycle.invoice.created_by') }}</option>
                                        @foreach ($employees as $employee)
                                            <option value="{{ $employee->id }}" @selected(request('added_by') == $employee->id)>
                                                {{ $employee->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="col-lg-3 col-md-6">
                                <div class="form-group">
                                    <label for="payment_status">{{ __('purchase_cycle.payment.status') }}</label>
                                    <select name="payment_status" class="form-control search-input" id="payment_status">
                                        <option value="">{{ __('purchase_cycle.statuses.all') }}</option>
                                        <option value="confirmed" @selected(request('payment_status') === 'confirmed')>{{ __('purchase_cycle.payment.confirmed') }}</option>
                                        <option value="cancelled" @selected(request('payment_status') === 'cancelled')>{{ __('purchase_cycle.payment.cancelled') }}</option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-lg-3 col-md-6">
                                <div class="form-group">
                                    <label for="total_greater_than">{{ __('purchase_cycle.payment.amount') }} ({{ __('purchase_cycle.invoice.date_from') }})</label>
                                    <input type="number" step="0.01" class="form-control search-input"
                                        name="total_greater_than" id="total_greater_than"
                                        value="{{ request('total_greater_than') }}">
                                </div>
                            </div>

                            <div class="col-lg-3 col-md-6">
                                <div class="form-group">
                                    <label for="total_less_than">{{ __('purchase_cycle.payment.amount') }} ({{ __('purchase_cycle.invoice.date_to') }})</label>
                                    <input type="number" step="0.01" class="form-control search-input"
                                        name="total_less_than" id="total_less_than"
                                        value="{{ request('total_less_than') }}">
                                </div>
                            </div>

                            <div class="col-lg-3 col-md-6">
                                <div class="form-group">
                                    <label for="identifier">{{ __('purchase_cycle.fields.code') }}</label>
                                    <input type="text" class="form-control search-input" name="identifier"
                                        id="identifier" value="{{ request('identifier') }}">
                                </div>
                            </div>

                            <div class="col-lg-3 col-md-6">
                                <div class="form-group">
                                    <label for="transfer_id">{{ __('purchase_cycle.payment.method') }}</label>
                                    <input type="text" class="form-control search-input" name="transfer_id"
                                        id="transfer_id" value="{{ request('transfer_id') }}">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="sl-filters__foot">
                        <button type="button" id="clearBtn" class="btn btn-outline-secondary">
                            <i class="fas fa-times"></i> {{ __('purchase_orders.search.clear') }}
                        </button>
                        <button type="button" id="searchBtn" class="btn btn-primary">
                            <i class="fas fa-search"></i> {{ __('purchase_cycle.actions.search') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- ══════════ النتائج ══════════ --}}
        <div class="card sl-table-card" id="reportCard">
            <div class="sl-table-head">
                <h6 class="sl-table-head__title">
                    <i class="fas fa-list"></i> {{ __('purchase_cycle.payment.section') }}
                    <span class="sl-count results-info">
                        {{ __('purchase_cycle.search.results_count', ['count' => \App\Support\LanguageSettings::formatNumber($payments->total(), 0)]) }}
                    </span>
                </h6>

                <div class="d-flex align-items-center gap-2">
                    <label for="perPage" class="sl-help mb-0">{{ __('purchase_cycle.invoice.per_page') }}</label>
                    <select id="perPage" class="form-control form-control-sm" style="width: auto;">
                        @foreach ([10, 25, 50, 100] as $size)
                            <option value="{{ $size }}" @selected(request('per_page', 25) == $size)>{{ $size }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="card-body">
                <div id="table-container">
                    @include('purchases::purchases.supplier_payments.partials.table', ['payments' => $payments])
                </div>
            </div>

            <div class="sl-table-foot" id="pagination-top">
                @include('purchases::purchases.supplier_payments.partials.pagination', ['payments' => $payments])
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    @php
        // `@json` لا يحتمل نداءً فيه فاصلة، فتُبنى المصفوفة هنا ثم تُمرَّر
        $paymentListConfig = [
            'routes' => [
                'index' => route('PaymentSupplier.indexPurchase'),
                'destroy' => route('PaymentSupplier.destroySupplierPayment', ['id' => '__ID__']),
                'cancel' => route('PaymentSupplier.cancelSupplierPayment', ['id' => '__ID__']),
            ],
            'rtl' => \App\Support\LanguageSettings::isRtl(),
            'i18n' => [
                'deleteTitle' => __('purchase_orders.confirm.delete_title'),
                'deleteText' => __('purchase_orders.confirm.delete_text', ['code' => '']),
                'cancelTitle' => __('purchase_cycle.payment.cancelled'),
                'yesDelete' => __('purchase_orders.confirm.yes_delete'),
                'yes' => __('purchase_orders.confirm.yes'),
                'no' => __('purchase_orders.confirm.no'),
                'deleted' => __('purchase_cycle.messages.deleted', ['document' => __('purchase_cycle.payment.document')]),
                'failed' => __('purchase_cycle.messages.validation_failed'),
                'results' => __('purchase_cycle.search.results_count', ['count' => '__COUNT__']),
                'noResults' => __('purchase_cycle.empty.filtered'),
            ],
        ];
    @endphp

    <script>
        window.paymentList = @json($paymentListConfig);

        $(document).ready(function() {
            let searchTimeout;

            const routes = window.paymentList.routes;
            const strings = window.paymentList.i18n;

            ['#supplier', '#added_by', '#payment_status'].forEach(function(selector) {
                if ($(selector).length) {
                    $(selector).select2({
                        allowClear: true,
                        width: '100%',
                        dir: window.paymentList.rtl ? 'rtl' : 'ltr',
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

            $('#supplier, #added_by, #payment_status').on('select2:select select2:clear', function() {
                performSearch();
            });

            $('#searchBtn').on('click', function() {
                performSearch();
            });

            $('#searchForm').on('submit', function(e) {
                e.preventDefault();
                performSearch();
            });

            $('#clearBtn').on('click', function() {
                $('#searchForm')[0].reset();
                $('#supplier, #added_by, #payment_status').val(null).trigger('change');
                performSearch();
            });

            $(document).on('click', '.pagination a', function(e) {
                e.preventDefault();
                const page = new URL($(this).attr('href'), window.location.origin).searchParams.get('page');
                performSearch(page);
            });

            function performSearch(page = 1) {
                showLoading();

                let formData = $('#searchForm').serialize();
                formData += '&page=' + page;
                formData += '&per_page=' + $('#perPage').val();

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
                            $('#table-container').html(response.data || '');
                            $('#pagination-top').html(response.pagination || '');
                            $('.results-info').text(
                                response.total > 0 ?
                                strings.results.replace('__COUNT__', response.total) :
                                strings.noResults
                            );
                        }
                    },
                    error: function() {
                        $('#table-container').html(
                            '<div class="alert alert-danger text-center mb-0">' + strings.failed + '</div>'
                        );
                    },
                    complete: hideLoading,
                });
            }

            function showLoading() {
                $('#table-container').css('opacity', '0.6');
                $('#loading-spinner').removeClass('d-none');
                $('#searchBtn').prop('disabled', true);
            }

            function hideLoading() {
                $('#table-container').css('opacity', '1');
                $('#loading-spinner').addClass('d-none');
                $('#searchBtn').prop('disabled', false);
            }

            $(document).on('click', '.delete-payment', function(e) {
                e.preventDefault();
                confirmAndPost($(this).data('id'), routes.destroy, 'DELETE', strings.deleteTitle, strings.yesDelete);
            });

            $(document).on('click', '.cancel-payment', function(e) {
                e.preventDefault();
                confirmAndPost($(this).data('id'), routes.cancel, 'POST', strings.cancelTitle, strings.yes);
            });

            function confirmAndPost(id, route, method, title, confirmText) {
                Swal.fire({
                    title: title,
                    text: strings.deleteText,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: confirmText,
                    cancelButtonText: strings.no,
                }).then(function(result) {
                    if (!result.isConfirmed) {
                        return;
                    }

                    $.ajax({
                        url: route.replace('__ID__', id),
                        method: method === 'DELETE' ? 'POST' : method,
                        data: {
                            _token: $('meta[name="csrf-token"]').attr('content'),
                            _method: method,
                        },
                        success: function() {
                            Swal.fire(strings.deleted, '', 'success');
                            performSearch();
                        },
                        error: function() {
                            Swal.fire(strings.failed, '', 'error');
                        },
                    });
                });
            }
        });
    </script>
@endsection
