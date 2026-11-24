{{--
    فهرس طلبات عروض الأسعار — بنفس تصميم فهرس أوامر الشراء (`sl-*`، مستنسخ
    عن فواتير المبيعات) حرفياً، بطلب المستخدم توحيد تصميم شاشات الفهرس في
    دورة المشتريات كلّها على نمط واحد.

    البنية والسكربتات مطابقة لِـ `ordersPurchase/index.blade.php` — انظره
    للتعليقات الكاملة على كل قسم؛ هنا فقط ما يخصّ هذا القسم من فروق.
--}}
@extends('purchases::master')

@section('title')
    {{ __('purchase_cycle.sections.quotation_request') }}
@stop

@section('content')
    @include('purchases::purchases.purchase_requests.partials.sales-theme')
    @include('components.hint-tips')
    @include('purchases::purchases.purchase_requests.partials.row-menu-fix')

    <div class="content-body sl-scope">

        @section('page-actions')
            @permission('purchases.quotations.create')
                <a href="{{ route('Quotations.create') }}" class="btn btn-primary">
                    <i class="fas fa-plus-circle"></i> {{ __('purchase_cycle.actions.create') }}
                </a>
            @endpermission

            @permission('purchases.quotations.export')
                <button type="button" class="btn btn-outline-secondary sl-tooltip-btn" id="printReport"
                    data-bs-toggle="tooltip" data-bs-placement="bottom"
                    title="{{ __('purchase_cycle.tooltips.print_preview') }}">
                    <i class="fas fa-print"></i> {{ __('purchase_cycle.actions.print') }}
                </button>

                <button type="button" class="btn btn-outline-secondary sl-tooltip-btn" id="exportExcel"
                    data-bs-toggle="tooltip" data-bs-placement="bottom"
                    title="{{ __('purchase_cycle.tooltips.export') }}">
                    <i class="fas fa-download"></i> {{ __('purchase_cycle.actions.export') }}
                </button>
            @endpermission

            <div id="loading-spinner" class="spinner-border spinner-border-sm text-primary d-none align-self-center" role="status" aria-hidden="true"></div>
        @endsection

        @include('purchases::purchases.purchase_requests.partials.page-head', [
            'title' => __('purchase_cycle.sections.quotation_request'),
            'icon' => 'file-signature',
            'sub' => __('purchase_cycle.sections.quotation_request'),
            'crumbs' => [
                __('purchase_orders.breadcrumb.home') => route('purchases.dashboard'),
                __('purchase_cycle.sections.quotation_request') => null,
            ],
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        {{-- لوحة إرشاد الشاشة — على قياس لوحة «أوامر الشراء» --}}
        @include('purchases::components.guide', [
            'key' => 'purchase-quotation-requests.index',
            'title' => __('purchase_cycle.guide.quotation_request.index.title'),
            'lead' => __('purchase_orders.guidance.quotations_index'),
            'open' => false,
            'blocks' => [
                __('purchase_cycle.guide.quotation_request.cycle.title') => [
                    __('purchase_cycle.guide.quotation_request.cycle.a'),
                    __('purchase_cycle.guide.quotation_request.cycle.b'),
                    __('purchase_cycle.guide.quotation_request.cycle.c'),
                ],
                __('purchase_cycle.guide.quotation_request.statuses.title') => [
                    __('purchase_cycle.guide.quotation_request.statuses.a'),
                    __('purchase_cycle.guide.quotation_request.statuses.b'),
                    __('purchase_cycle.guide.quotation_request.statuses.c'),
                ],
                __('purchase_cycle.guide.quotation_request.index.stats.title') => [
                    __('purchase_cycle.guide.quotation_request.index.stats.a'),
                    __('purchase_cycle.guide.quotation_request.index.stats.b'),
                ],
                __('purchase_cycle.guide.quotation_request.index.subscription.title') => [
                    __('purchase_cycle.guide.quotation_request.index.subscription.a'),
                    __('purchase_cycle.guide.quotation_request.index.subscription.b'),
                ],
                __('purchase_cycle.guide.settings.title') => [
                    __('purchase_cycle.guide.settings.a'),
                    __('purchase_cycle.guide.settings.b'),
                    __('purchase_cycle.guide.settings.c'),
                ],
            ],
            'facts' => [
                __('purchase_cycle.guide.quotation_request.facts.no_prices'),
                __('purchase_cycle.guide.quotation_request.facts.no_ledger'),
                __('purchase_cycle.guide.quotation_request.facts.final'),
            ],
        ])

        {{-- ══════════ حالة حدّ اشتراك فوترة ══════════ --}}
        @include('purchases::components.subscription-bar', ['limit' => $subscriptionLimit])

        {{-- ══════════ الإحصاءات ══════════ --}}
        <div class="sl-stats">
            <div class="sl-stat">
                <span class="sl-stat__icon"><i class="fas fa-file-signature"></i></span>
                <div class="sl-stat__body">
                    <p class="sl-stat__value">{{ \App\Support\LanguageSettings::formatNumber($stats['total'] ?? 0, 0) }}</p>
                    <p class="sl-stat__label">{{ __('purchase_cycle.sections.quotation_request') }} — {{ __('purchase_cycle.stats_hints.quotation_request.total') }}</p>
                </div>
            </div>

            <div class="sl-stat">
                <span class="sl-stat__icon sl-stat__icon--warning"><i class="fas fa-clock"></i></span>
                <div class="sl-stat__body">
                    <p class="sl-stat__value">{{ \App\Support\LanguageSettings::formatNumber($stats['review'] ?? 0, 0) }}</p>
                    <p class="sl-stat__label">{{ __('purchase_cycle.statuses.Under Review') }} — {{ __('purchase_cycle.stats_hints.quotation_request.review') }}</p>
                </div>
            </div>

            <div class="sl-stat">
                <span class="sl-stat__icon sl-stat__icon--success"><i class="fas fa-check-circle"></i></span>
                <div class="sl-stat__body">
                    <p class="sl-stat__value">{{ \App\Support\LanguageSettings::formatNumber($stats['approved'] ?? 0, 0) }}</p>
                    <p class="sl-stat__label">{{ __('purchase_cycle.statuses.approval') }} — {{ __('purchase_cycle.stats_hints.quotation_request.approved') }}</p>
                </div>
            </div>

            <div class="sl-stat">
                <span class="sl-stat__icon sl-stat__icon--danger"><i class="fas fa-times-circle"></i></span>
                <div class="sl-stat__body">
                    <p class="sl-stat__value">{{ \App\Support\LanguageSettings::formatNumber($stats['rejected'] ?? 0, 0) }}</p>
                    <p class="sl-stat__label">{{ __('purchase_cycle.statuses.disagree') }} — {{ __('purchase_cycle.stats_hints.quotation_request.rejected') }}</p>
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
                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="code">{{ __('purchase_cycle.fields.code') }}</label>
                                <input type="text" class="form-control search-input" name="code" id="code"
                                    placeholder="{{ __('purchase_orders.placeholders.search_code') }}">
                                <span class="po-help">{{ __('purchase_cycle.filter_hints.quotation_request.code') }}</span>
                            </div>
                        </div>

                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="supplier_id">{{ __('purchase_cycle.fields.supplier') }}</label>
                                <select name="supplier_id" class="form-control search-input js-list-search" id="supplier_id">
                                    <option value="">{{ __('purchase_cycle.fields.suppliers') }}</option>
                                    @foreach ($suppliers as $supplier)
                                        <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                                    @endforeach
                                </select>
                                <span class="po-help">{{ __('purchase_cycle.filter_hints.quotation_request.supplier') }}</span>
                            </div>
                        </div>

                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="status">{{ __('purchase_cycle.fields.status') }}</label>
                                <select class="form-control search-input" name="status" id="status">
                                    <option value="">{{ __('purchase_cycle.statuses.all') }}</option>
                                    @foreach (['Under Review', 'approval', 'disagree'] as $status)
                                        <option value="{{ $status }}">{{ __('purchase_cycle.statuses.'.$status) }}</option>
                                    @endforeach
                                </select>
                                <span class="po-help">{{ __('purchase_cycle.filter_hints.quotation_request.status') }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="collapse" id="advancedSearchForm">
                        <div class="row">
                            @foreach (['order_date_from', 'order_date_to', 'due_date_from', 'due_date_to'] as $filter)
                                <div class="col-md-3 col-sm-6">
                                    <div class="form-group">
                                        <label for="{{ $filter }}">{{ __('purchase_orders.search.'.$filter) }}</label>
                                        <input type="date" class="form-control search-input" name="{{ $filter }}" id="{{ $filter }}">
                                        @if ($filter === 'due_date_to')
                                            <span class="po-help">{{ __('purchase_cycle.filter_hints.quotation_request.dates') }}</span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="sl-filters__foot">
                        <button type="button" id="resetSearch" class="btn btn-outline-secondary">
                            <i class="fas fa-redo"></i> {{ __('purchase_orders.search.clear') }}
                        </button>
                        <button type="button" id="search-btn" class="btn btn-primary">
                            <i class="fas fa-search"></i> {{ __('purchase_orders.search.submit') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- ══════════ النتائج ══════════ --}}
        <div class="card sl-table-card" id="reportCard">
            <div class="sl-table-head">
                <h6 class="sl-table-head__title">
                    <i class="fas fa-list"></i> {{ __('purchase_cycle.sections.quotation_request') }}
                    <span class="sl-count" id="results-count">{{ $purchaseQuotation->total() }}</span>
                </h6>

                @permission('purchases.quotations.delete')
                    <button type="button" id="bulk-delete-btn" class="btn btn-sm btn-danger" disabled>
                        <i class="fas fa-trash"></i> {{ __('purchase_orders.bulk.delete') }}
                        <span id="bulk-count"></span>
                    </button>
                @endpermission
            </div>

            <div class="card-body">
                <form id="bulk-form" action="{{ route('Quotations.bulkDelete') }}" method="POST">
                    @csrf
                    <div id="table-container">
                        @include('purchases::purchases.quotations.partials.table', ['purchaseQuotation' => $purchaseQuotation])
                    </div>
                </form>

                {{-- حذف مفرد: نموذج مستقلّ، فوضعُ نموذج داخل نموذج غير صالح --}}
                <form id="single-delete-form" method="POST">
                    @csrf
                    @method('DELETE')
                </form>
            </div>

            <div class="sl-table-foot" id="pagination-container">
                @include('purchases::purchases.quotations.partials.pagination', ['purchaseQuotation' => $purchaseQuotation])
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    @include('purchases::purchases.purchase_requests.partials.report-tools')

    @php
        $poListStrings = [
            'results' => __('purchase_cycle.search.results_count', ['count' => '__COUNT__']),
            'deleteTitle' => __('purchase_orders.confirm.delete_title'),
            'deleteText' => __('purchase_orders.confirm.delete_text', ['code' => '__CODE__']),
            'yesDelete' => __('purchase_orders.confirm.yes_delete'),
            'no' => __('purchase_orders.confirm.no'),
            'bulkText' => __('purchase_orders.bulk.confirm_text', ['count' => '__COUNT__']),
        ];
    @endphp

    <script>
        window.purchaseListConfig = {
            indexUrl: @json(route('Quotations.index')),
            i18n: @json($poListStrings),
        };
    </script>
    <script src="{{ asset('assets/js/purchase-cycle-list.js') }}"></script>

    <script>
        if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
            document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
                new bootstrap.Tooltip(el);
            });
        }
    </script>

    <script>
        $(function () {
            slReportTools({
                table: '#reportTable',
                fileName: @json(__('purchase_cycle.sections.quotation_request')),
                title: @json(__('purchase_cycle.sections.quotation_request')),
                subTitle: @json(config('app.name')),
                numericColumns: [4],
                exportButton: '#exportExcel',
                printButton: '#printReport',
                descriptionColumn: 2,
            });
        });
    </script>
@endsection
