{{--
    فهرس عروض أسعار الموردين — بنفس تصميم فهرس أوامر الشراء (`sl-*`، مستنسخ
    عن فواتير المبيعات) حرفياً، بطلب المستخدم توحيد تصميم شاشات الفهرس في
    دورة المشتريات كلّها على نمط واحد. انظر `ordersPurchase/index.blade.php`
    للتعليقات الكاملة على كل قسم.
--}}
@extends('purchases::master')

@section('title')
    {{ __('purchase_cycle.sections.purchase_quotation') }}
@stop

@section('content')
    @include('purchases::purchases.purchase_requests.partials.sales-theme')
    @include('components.hint-tips')
    @include('purchases::purchases.purchase_requests.partials.row-menu-fix')

    <div class="content-body sl-scope">

        @section('page-actions')
            @permission('purchases.price_quotations.create')
                <a href="{{ route('ViewPurchasePrice.create') }}" class="btn btn-primary">
                    <i class="fas fa-plus-circle"></i> {{ __('purchase_cycle.actions.create') }}
                </a>
            @endpermission

            @permission('purchases.price_quotations.export')
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
            'title' => __('purchase_cycle.sections.purchase_quotation'),
            'icon' => 'tags',
            'sub' => __('purchase_cycle.sections.purchase_quotation'),
            'crumbs' => [
                __('purchase_orders.breadcrumb.home') => route('purchases.dashboard'),
                __('purchase_cycle.sections.purchase_quotation') => null,
            ],
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        {{-- ══════════ حالة حدّ اشتراك فوترة ══════════ --}}
        @if (($subscriptionLimit['known'] ?? false) && ! ($subscriptionLimit['unlimited'] ?? true))
            @php
                $priceQuotationsUsagePercent = min(100, max(0, (int) $subscriptionLimit['percent']));
                $priceQuotationsUsageTone = $subscriptionLimit['exhausted'] ? 'danger' : ($priceQuotationsUsagePercent >= 80 ? 'warning' : 'info');
            @endphp
            <div class="alert alert-{{ $priceQuotationsUsageTone }} d-flex align-items-center justify-content-between flex-wrap gap-2 py-2 px-3 mb-3">
                <span>
                    <i class="fas fa-gauge-high me-1"></i>
                    {{ __($subscriptionLimit['exhausted'] ? 'purchase_orders.subscription.usage_line_exhausted' : 'purchase_orders.subscription.usage_line', [
                        'used' => \App\Support\LanguageSettings::formatNumber($subscriptionLimit['used'], 0),
                        'allowance' => \App\Support\LanguageSettings::formatNumber($subscriptionLimit['allowance'], 0),
                    ]) }}
                </span>
                <div class="progress" style="width: 160px; height: 8px;" role="progressbar"
                    aria-valuenow="{{ $priceQuotationsUsagePercent }}" aria-valuemin="0" aria-valuemax="100">
                    <div class="progress-bar bg-{{ $priceQuotationsUsageTone }}" style="width: {{ $priceQuotationsUsagePercent }}%"></div>
                </div>
            </div>
        @endif

        {{-- ══════════ الإحصاءات ══════════ --}}
        <div class="sl-stats">
            <div class="sl-stat">
                <span class="sl-stat__icon"><i class="fas fa-tags"></i></span>
                <div class="sl-stat__body">
                    <p class="sl-stat__value">{{ \App\Support\LanguageSettings::formatNumber($stats['total'] ?? 0, 0) }}</p>
                    <p class="sl-stat__label">{{ __('purchase_cycle.sections.purchase_quotation') }}</p>
                </div>
            </div>

            <div class="sl-stat">
                <span class="sl-stat__icon sl-stat__icon--warning"><i class="fas fa-clock"></i></span>
                <div class="sl-stat__body">
                    <p class="sl-stat__value">{{ \App\Support\LanguageSettings::formatNumber($stats['review'] ?? 0, 0) }}</p>
                    <p class="sl-stat__label">{{ __('purchase_cycle.statuses.Under Review') }}</p>
                </div>
            </div>

            <div class="sl-stat">
                <span class="sl-stat__icon sl-stat__icon--success"><i class="fas fa-check-circle"></i></span>
                <div class="sl-stat__body">
                    <p class="sl-stat__value">{{ \App\Support\LanguageSettings::formatNumber($stats['approved'] ?? 0, 0) }}</p>
                    <p class="sl-stat__label">{{ __('purchase_cycle.statuses.approval') }}</p>
                </div>
            </div>

            <div class="sl-stat">
                <span class="sl-stat__icon sl-stat__icon--danger"><i class="fas fa-times-circle"></i></span>
                <div class="sl-stat__body">
                    <p class="sl-stat__value">{{ \App\Support\LanguageSettings::formatNumber($stats['rejected'] ?? 0, 0) }}</p>
                    <p class="sl-stat__label">{{ __('purchase_cycle.statuses.disagree') }}</p>
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
                            </div>
                        </div>

                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="status">{{ __('purchase_cycle.fields.status') }}</label>
                                <select class="form-control search-input" name="status" id="status">
                                    <option value="">{{ __('purchase_cycle.statuses.all') }}</option>
                                    @foreach (['Under Review', 'approval', 'disagree', 'Convert to Order'] as $status)
                                        <option value="{{ $status }}">{{ __('purchase_cycle.statuses.'.$status) }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="collapse" id="advancedSearchForm">
                        <div class="row">
                            @foreach (['order_date_from', 'order_date_to'] as $filter)
                                <div class="col-md-3 col-sm-6">
                                    <div class="form-group">
                                        <label for="{{ $filter }}">{{ __('purchase_orders.search.'.$filter) }}</label>
                                        <input type="date" class="form-control search-input" name="{{ $filter }}" id="{{ $filter }}">
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
                    <i class="fas fa-list"></i> {{ __('purchase_cycle.sections.purchase_quotation') }}
                    <span class="sl-count" id="results-count">{{ $purchaseQuotation->total() }}</span>
                </h6>

                @permission('purchases.price_quotations.delete')
                    <button type="button" id="bulk-delete-btn" class="btn btn-sm btn-danger" disabled>
                        <i class="fas fa-trash"></i> {{ __('purchase_orders.bulk.delete') }}
                        <span id="bulk-count"></span>
                    </button>
                @endpermission
            </div>

            <div class="card-body">
                <form id="bulk-form" action="{{ route('ViewPurchasePrice.bulkDelete') }}" method="POST">
                    @csrf
                    <div id="table-container">
                        @include('purchases::purchases.view_purchase_price.partials.table', ['purchaseQuotation' => $purchaseQuotation])
                    </div>
                </form>

                {{-- حذف مفرد: نموذج مستقلّ، فوضعُ نموذج داخل نموذج غير صالح --}}
                <form id="single-delete-form" method="POST">
                    @csrf
                    @method('DELETE')
                </form>
            </div>

            <div class="sl-table-foot" id="pagination-container">
                @include('purchases::purchases.view_purchase_price.partials.pagination', ['purchaseQuotation' => $purchaseQuotation])
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
            indexUrl: @json(route('ViewPurchasePrice.index')),
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
                fileName: @json(__('purchase_cycle.sections.purchase_quotation')),
                title: @json(__('purchase_cycle.sections.purchase_quotation')),
                subTitle: @json(config('app.name')),
                numericColumns: [5],
                exportButton: '#exportExcel',
                printButton: '#printReport',
                descriptionColumn: 2,
            });
        });
    </script>
@endsection
