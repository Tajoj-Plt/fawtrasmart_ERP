{{--
    فهرس طلبات أوامر الشراء — أُعيد بناؤه على نمط تصميم «طلبات الشراء»
    (`ordersPurchase/index.blade.php`، المبني بدوره على تصميم فهرس فواتير
    المبيعات) توحيداً لشكل شاشات فهرسة وحدة المشتريات كلّها.

    لا مسار حذف جماعي لهذا القسم (`OrdersRequests` لا تملك bulkDelete)، فلا
    خانات تحديد ولا زر حذف جماعي هنا رغم أن الجدول أُنشئ أصلاً بهما ضمن
    اتفاق `public/assets/js/purchase-cycle-list.js` — العنصران المفقودان
    محميّان بفحص وجود في السكربت فلا يكسران شيئاً.
--}}
@extends('purchases::master')

@section('title')
    {{ __('purchase_cycle.sections.purchase_order_request') }}
@stop

@section('content')
    @include('purchases::purchases.purchase_requests.partials.sales-theme')
    @include('components.hint-tips')
    @include('purchases::purchases.purchase_requests.partials.row-menu-fix')

    <div class="content-body sl-scope">

        @section('page-actions')
            @permission('purchases.order_requests.create')
                <a href="{{ route('PurchaseOrders.create') }}" class="btn btn-primary">
                    <i class="fas fa-plus-circle"></i> {{ __('purchase_cycle.actions.create') }}
                </a>
            @endpermission

            <div id="loading-spinner" class="spinner-border spinner-border-sm text-primary d-none align-self-center" role="status" aria-hidden="true"></div>
        @endsection

        @include('purchases::purchases.purchase_requests.partials.page-head', [
            'title' => __('purchase_cycle.sections.purchase_order_request'),
            'icon' => 'clipboard-list',
            'crumbs' => [
                __('purchase_orders.breadcrumb.home') => route('purchases.dashboard'),
                __('purchase_cycle.sections.purchase_order_request') => null,
            ],
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        {{-- لوحة إرشاد الشاشة --}}
        @include('purchases::components.guide', [
            'key' => 'purchase-order-requests.index',
            'title' => __('purchase_cycle.guide.order_request.index.title'),
            'lead' => __('purchase_orders.guidance.purchase_orders_index'),
            'open' => false,
            'blocks' => [
                __('purchase_cycle.guide.order_request.cycle.title') => [
                    __('purchase_cycle.guide.order_request.cycle.a'),
                    __('purchase_cycle.guide.order_request.cycle.b'),
                    __('purchase_cycle.guide.order_request.cycle.c'),
                ],
                __('purchase_cycle.guide.order_request.statuses.title') => [
                    __('purchase_cycle.guide.order_request.statuses.a'),
                    __('purchase_cycle.guide.order_request.statuses.b'),
                    __('purchase_cycle.guide.order_request.statuses.c'),
                ],
                __('purchase_cycle.guide.order_request.index.stats.title') => [
                    __('purchase_cycle.guide.order_request.index.stats.a'),
                    __('purchase_cycle.guide.order_request.index.stats.b'),
                ],
                __('purchase_cycle.guide.settings.title') => [
                    __('purchase_cycle.guide.settings.a'),
                    __('purchase_cycle.guide.settings.b'),
                    __('purchase_cycle.guide.settings.c'),
                ],
            ],
            'facts' => [
                __('purchase_cycle.guide.order_request.facts.no_ledger_yet'),
                __('purchase_cycle.guide.order_request.facts.to_invoice'),
                __('purchase_cycle.guide.order_request.facts.final'),
            ],
        ])

        {{-- ══════════ الإحصاءات ══════════ --}}
        <div class="sl-stats">
            <div class="sl-stat">
                <span class="sl-stat__icon"><i class="fas fa-clipboard-list"></i></span>
                <div class="sl-stat__body">
                    <p class="sl-stat__value">{{ \App\Support\LanguageSettings::formatNumber($stats['total'] ?? 0, 0) }}</p>
                    <p class="sl-stat__label">{{ __('purchase_cycle.sections.purchase_order_request') }} — {{ __('purchase_cycle.stats_hints.order_request.total') }}</p>
                </div>
            </div>

            <div class="sl-stat">
                <span class="sl-stat__icon sl-stat__icon--warning"><i class="fas fa-clock"></i></span>
                <div class="sl-stat__body">
                    <p class="sl-stat__value">{{ \App\Support\LanguageSettings::formatNumber($stats['review'] ?? 0, 0) }}</p>
                    <p class="sl-stat__label">{{ __('purchase_cycle.statuses.Under Review') }} — {{ __('purchase_cycle.stats_hints.order_request.review') }}</p>
                </div>
            </div>

            <div class="sl-stat">
                <span class="sl-stat__icon sl-stat__icon--success"><i class="fas fa-check-circle"></i></span>
                <div class="sl-stat__body">
                    <p class="sl-stat__value">{{ \App\Support\LanguageSettings::formatNumber($stats['approved'] ?? 0, 0) }}</p>
                    <p class="sl-stat__label">{{ __('purchase_cycle.statuses.approval') }} — {{ __('purchase_cycle.stats_hints.order_request.approved') }}</p>
                </div>
            </div>

            <div class="sl-stat">
                <span class="sl-stat__icon sl-stat__icon--danger"><i class="fas fa-times-circle"></i></span>
                <div class="sl-stat__body">
                    <p class="sl-stat__value">{{ \App\Support\LanguageSettings::formatNumber($stats['rejected'] ?? 0, 0) }}</p>
                    <p class="sl-stat__label">{{ __('purchase_cycle.statuses.disagree') }} — {{ __('purchase_cycle.stats_hints.order_request.rejected') }}</p>
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
                                <label for="number_invoice">{{ __('purchase_cycle.fields.code') }}</label>
                                <input type="text" class="form-control search-input" name="number_invoice" id="number_invoice"
                                    placeholder="{{ __('purchase_orders.placeholders.search_code') }}">
                                <span class="po-help">{{ __('purchase_cycle.filter_hints.order_request.code') }}</span>
                            </div>
                        </div>

                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="employee_search">{{ __('purchase_cycle.fields.supplier') }}</label>
                                <select name="employee_search" class="form-control search-input js-list-search" id="employee_search">
                                    <option value="">{{ __('purchase_cycle.fields.suppliers') }}</option>
                                    @foreach ($suppliers as $supplier)
                                        <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                                    @endforeach
                                </select>
                                <span class="po-help">{{ __('purchase_cycle.filter_hints.order_request.supplier') }}</span>
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
                                <span class="po-help">{{ __('purchase_cycle.filter_hints.order_request.status') }}</span>
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
                                            <span class="po-help">{{ __('purchase_cycle.filter_hints.order_request.dates') }}</span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="sl-filters__foot">
                        <button type="button" id="resetSearch" class="btn btn-outline-secondary">
                            <i class="fas fa-redo"></i> {{ __('purchase_cycle.actions.reset') }}
                        </button>
                        <button type="button" id="search-btn" class="btn btn-primary">
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
                    <i class="fas fa-list"></i> {{ __('purchase_cycle.sections.purchase_order_request') }}
                    <span class="sl-count" id="results-count">{{ \App\Support\LanguageSettings::formatNumber($purchaseOrdersRequests->total(), 0) }}</span>
                </h6>
            </div>

            <div class="card-body">
                <div id="table-container">
                    @include('purchases::purchases.purchase_orders.partials.table', ['purchaseOrdersRequests' => $purchaseOrdersRequests])
                </div>

                {{-- الحذف المفرد بنموذج مستقلّ: نموذج داخل نموذج غير صالح في HTML --}}
                <form id="single-delete-form" method="POST">
                    @csrf
                    @method('DELETE')
                </form>
            </div>

            <div class="sl-table-foot" id="pagination-container">
                @include('purchases::purchases.purchase_orders.partials.pagination', ['purchaseOrdersRequests' => $purchaseOrdersRequests])
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    @php
        // `@json` تقصّ المصفوفة الحرفية متعدّدة الأسطر عند أول فاصلة
        $poListStrings = [
            'results' => __('purchase_cycle.search.results_count', ['count' => '__COUNT__']),
            'deleteTitle' => __('purchase_orders.confirm.delete_title'),
            'deleteText' => __('purchase_orders.confirm.delete_text', ['code' => '__CODE__']),
            'yesDelete' => __('purchase_orders.confirm.yes_delete'),
            'no' => __('purchase_orders.confirm.no'),
        ];
    @endphp

    <script>
        window.purchaseListConfig = {
            indexUrl: @json(route('PurchaseOrders.index')),
            i18n: @json($poListStrings),
        };
    </script>
    <script src="{{ asset('assets/js/purchase-cycle-list.js') }}"></script>
@endsection
