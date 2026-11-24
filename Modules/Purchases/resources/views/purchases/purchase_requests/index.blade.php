{{--
    فهرس طلبات الشراء — نسخة مستنسخة حرفياً عن تصميم فواتير المبيعات
    (`Modules/Sales/resources/views/invoices/index.blade.php`)، بطلبٍ صريح من
    المستخدم بدل محاذاة بنيوية على نظام `po-*` القائم في بقيّة الوحدة.

    البنية: ترويسة (`page-head`) بإجراءاتها، بطاقات إحصاء (`sl-stats`)، بطاقة
    فلترة (`sl-filters`) أساسية+متقدّمة، ثم بطاقة نتائج تتحدّث بـAjax وحدها
    (`sl-table-card`) مع تحديد جماعي وحذف وترقيم صفحات، وأخيراً طباعة/تصدير
    الجدول كما هو معروض بعد الفلترة.

    منطق الـAjax والتحديد الجماعي مأخوذ من `public/assets/js/purchase-cycle-list.js`
    — نفس الملفّ المستعمل فعلاً في شاشات «طلبات عروض الأسعار» و«عروض أسعار
    المشتريات» — لا إعادة اختراع، فقط ربطٌ لم يكن موجوداً في هذه الشاشة قبل
    إعادة البناء (كانت الشاشة السابقة تكتب `search-form`/`table-container`
    بلا سكربتٍ يربطها).
--}}
@extends('purchases::master')

@section('title')
    {{ __('purchase_orders.titles.index') }}
@stop

@section('content')
    @include('purchases::purchases.purchase_requests.partials.sales-theme')
    @include('components.hint-tips')
    @include('purchases::purchases.purchase_requests.partials.row-menu-fix')

    <div class="content-body sl-scope">

        @section('page-actions')
            @permission('purchases.orders.create')
                <a href="{{ route('PurchaseRequests.create') }}" class="btn btn-primary">
                    <i class="fas fa-plus-circle"></i> {{ __('purchase_orders.actions.create') }}
                </a>
            @endpermission

            @permission('purchases.orders.export')
                <button type="button" class="btn btn-outline-secondary sl-tooltip-btn" id="printReport"
                    data-bs-toggle="tooltip" data-bs-placement="bottom"
                    title="{{ __('purchase_orders.tooltips.print') }}">
                    <i class="fas fa-print"></i> {{ __('purchase_orders.actions.print') }}
                </button>

                <button type="button" class="btn btn-outline-secondary sl-tooltip-btn" id="exportExcel"
                    data-bs-toggle="tooltip" data-bs-placement="bottom"
                    title="{{ __('purchase_orders.tooltips.export') }}">
                    <i class="fas fa-download"></i> {{ __('purchase_orders.actions.export') }}
                </button>
            @endpermission

            <div id="loading-spinner" class="spinner-border spinner-border-sm text-primary d-none align-self-center" role="status" aria-hidden="true"></div>
        @endsection

        @include('purchases::purchases.purchase_requests.partials.page-head', [
            'title' => __('purchase_orders.titles.index'),
            'icon' => 'file-invoice',
            'sub' => __('purchase_orders.section'),
            'crumbs' => [
                __('purchase_orders.breadcrumb.home') => route('purchases.dashboard'),
                __('purchase_orders.breadcrumb.index') => null,
            ],
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        {{-- لوحة إرشاد الشاشة — على قياس لوحة «فواتير المشتريات»
             (`purchases::components.guide`): تُفتح أوّل مرّة ثمّ تتذكّر طيّها --}}
        @include('purchases::components.guide', [
            'key' => 'purchase-orders.index',
            'title' => __('purchase_orders.guide.index.title'),
            'lead' => __('purchase_orders.guidance.purchase_requests_index'),
            'open' => false,
            'blocks' => [
                __('purchase_orders.guide.cycle.title') => [
                    __('purchase_orders.guide.cycle.a'),
                    __('purchase_orders.guide.cycle.b'),
                    __('purchase_orders.guide.cycle.c'),
                ],
                __('purchase_orders.guide.statuses.title') => [
                    __('purchase_orders.guide.statuses.a'),
                    __('purchase_orders.guide.statuses.b'),
                    __('purchase_orders.guide.statuses.c'),
                ],
                __('purchase_orders.guide.index.stats.title') => [
                    __('purchase_orders.guide.index.stats.a'),
                    __('purchase_orders.guide.index.stats.b'),
                ],
                __('purchase_orders.guide.index.subscription.title') => [
                    __('purchase_orders.guide.index.subscription.a'),
                    __('purchase_orders.guide.index.subscription.b'),
                ],
                __('purchase_orders.guide.settings.title') => [
                    __('purchase_orders.guide.settings.a'),
                    __('purchase_orders.guide.settings.b'),
                    __('purchase_orders.guide.settings.c'),
                ],
            ],
        ])

        {{-- ══════════ حالة حدّ اشتراك فوترة ══════════
             تظهر فقط حين تكون الميزة معروفة لدى فوترة (subscriptionLimit.known)؛
             والاشتراك غير المربوط أو ميزةٌ لم تُعرَّف بعد في كتالوج فوترة كلاهما
             known === false، فلا شيء يُعرَض — انظر تعليق UsageReporter::catalog() --}}
        @include('purchases::components.subscription-bar', ['limit' => $subscriptionLimit])

        {{-- ══════════ الإحصاءات ══════════ --}}
        <div class="sl-stats">
            <div class="sl-stat">
                <span class="sl-stat__icon"><i class="fas fa-file-invoice"></i></span>
                <div class="sl-stat__body">
                    <p class="sl-stat__value">{{ \App\Support\LanguageSettings::formatNumber($stats['total'] ?? 0, 0) }}</p>
                    <p class="sl-stat__label">{{ __('purchase_orders.section') }} — {{ __('purchase_orders.stats_hints.total') }}</p>
                </div>
            </div>

            <div class="sl-stat">
                <span class="sl-stat__icon sl-stat__icon--warning"><i class="fas fa-clock"></i></span>
                <div class="sl-stat__body">
                    <p class="sl-stat__value">{{ \App\Support\LanguageSettings::formatNumber($stats['review'] ?? 0, 0) }}</p>
                    <p class="sl-stat__label">{{ __('purchase_orders.statuses.Under Review') }} — {{ __('purchase_orders.stats_hints.review') }}</p>
                </div>
            </div>

            <div class="sl-stat">
                <span class="sl-stat__icon sl-stat__icon--success"><i class="fas fa-check-circle"></i></span>
                <div class="sl-stat__body">
                    <p class="sl-stat__value">{{ \App\Support\LanguageSettings::formatNumber($stats['approved'] ?? 0, 0) }}</p>
                    <p class="sl-stat__label">{{ __('purchase_orders.statuses.approval') }} — {{ __('purchase_orders.stats_hints.approved') }}</p>
                </div>
            </div>

            <div class="sl-stat">
                <span class="sl-stat__icon sl-stat__icon--danger"><i class="fas fa-times-circle"></i></span>
                <div class="sl-stat__body">
                    <p class="sl-stat__value">{{ \App\Support\LanguageSettings::formatNumber($stats['rejected'] ?? 0, 0) }}</p>
                    <p class="sl-stat__label">{{ __('purchase_orders.statuses.disagree') }} — {{ __('purchase_orders.stats_hints.rejected') }}</p>
                </div>
            </div>
        </div>

        {{-- ══════════ البحث والتصفية ══════════ --}}
        <div class="card sl-filters">
            <div class="card-body">
                <div class="sl-filters__head">
                    <h6 class="sl-filters__title">
                        <i class="fas fa-filter"></i> {{ __('purchase_orders.search.heading') }}
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
                                <label for="employee_id">{{ __('purchase_orders.fields.employee') }}</label>
                                <select name="employee_id" class="form-control search-input js-list-search" id="employee_id">
                                    <option value="">{{ __('purchase_orders.placeholders.select_employee') }}</option>
                                    @foreach ($employees as $employee)
                                        <option value="{{ $employee->id }}">{{ $employee->name }}</option>
                                    @endforeach
                                </select>
                                <span class="po-help">{{ __('purchase_orders.filter_hints.employee') }}</span>
                            </div>
                        </div>

                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="code">{{ __('purchase_orders.fields.code') }}</label>
                                <input type="text" class="form-control search-input" name="code" id="code"
                                    placeholder="{{ __('purchase_orders.placeholders.search_code') }}">
                                <span class="po-help">{{ __('purchase_orders.filter_hints.code') }}</span>
                            </div>
                        </div>

                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="status">{{ __('purchase_orders.fields.status') }}</label>
                                <select class="form-control search-input" name="status" id="status">
                                    <option value="">{{ __('purchase_orders.statuses.all') }}</option>
                                    @foreach (['Under Review', 'approval', 'disagree', 'Convert to Quotation'] as $status)
                                        <option value="{{ $status }}">{{ __('purchase_orders.statuses.'.$status) }}</option>
                                    @endforeach
                                </select>
                                <span class="po-help">{{ __('purchase_orders.filter_hints.status') }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="collapse" id="advancedSearchForm">
                        <div class="row">
                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label for="title">{{ __('purchase_orders.fields.title') }}</label>
                                    <input type="text" class="form-control search-input" name="title" id="title">
                                    <span class="po-help">{{ __('purchase_orders.filter_hints.title') }}</span>
                                </div>
                            </div>

                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label for="follow_status">{{ __('purchase_orders.fields.follow_status') }}</label>
                                    <select name="follow_status" class="form-control search-input" id="follow_status">
                                        <option value="">{{ __('purchase_orders.statuses.all') }}</option>
                                        @foreach (['Under Review', 'approval', 'disagree', 'Convert to Quotation'] as $status)
                                            <option value="{{ $status }}">{{ __('purchase_orders.statuses.'.$status) }}</option>
                                        @endforeach
                                    </select>
                                    <span class="po-help">{{ __('purchase_orders.filter_hints.follow_status') }}</span>
                                </div>
                            </div>

                            @php
                                $dateFilters = ['order_date_from', 'order_date_to', 'due_date_from', 'due_date_to'];
                            @endphp

                            @foreach ($dateFilters as $filter)
                                <div class="col-md-3 col-sm-6">
                                    <div class="form-group">
                                        <label for="{{ $filter }}">{{ __('purchase_orders.search.'.$filter) }}</label>
                                        <input type="date" class="form-control search-input" name="{{ $filter }}" id="{{ $filter }}">
                                        @if ($filter === 'due_date_to')
                                            <span class="po-help">{{ __('purchase_orders.filter_hints.dates') }}</span>
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
                    <i class="fas fa-list"></i> {{ __('purchase_orders.section') }}
                    <span class="sl-count" id="results-count">{{ $purchaseOrders->total() }}</span>
                </h6>

                @permission('purchases.orders.delete')
                    <button type="button" id="bulk-delete-btn" class="btn btn-sm btn-danger" disabled>
                        <i class="fas fa-trash"></i> {{ __('purchase_orders.bulk.delete') }}
                        <span id="bulk-count"></span>
                    </button>
                @endpermission
            </div>

            <div class="card-body">
                <form id="bulk-form" action="{{ route('PurchaseRequests.bulkDelete') }}" method="POST">
                    @csrf
                    <div id="table-container">
                        @include('purchases::purchases.purchase_requests.partials.table', ['purchaseOrders' => $purchaseOrders])
                    </div>
                </form>

                {{-- حذف مفرد: نموذج مستقلّ، فوضعُ نموذج داخل نموذج غير صالح --}}
                <form id="single-delete-form" method="POST">
                    @csrf
                    @method('DELETE')
                </form>
            </div>

            <div class="sl-table-foot" id="pagination-container">
                @include('purchases::purchases.purchase_requests.partials.pagination', ['purchaseOrders' => $purchaseOrders])
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    @include('purchases::purchases.purchase_requests.partials.report-tools')

    @php
        $poListStrings = [
            'results' => __('purchase_orders.search.results_count', ['count' => '__COUNT__']),
            'deleteTitle' => __('purchase_orders.confirm.delete_title'),
            'deleteText' => __('purchase_orders.confirm.delete_text', ['code' => '__CODE__']),
            'yesDelete' => __('purchase_orders.confirm.yes_delete'),
            'no' => __('purchase_orders.confirm.no'),
            'bulkText' => __('purchase_orders.bulk.confirm_text', ['count' => '__COUNT__']),
        ];
    @endphp

    <script>
        window.purchaseListConfig = {
            indexUrl: @json(route('PurchaseRequests.index')),
            i18n: @json($poListStrings),
        };
    </script>
    <script src="{{ asset('assets/js/purchase-cycle-list.js') }}"></script>

    {{-- تلميحات الأزرار (Bootstrap Tooltip) — لا تُهيَّأ تلقائياً في Bootstrap 5،
         فتُفعَّل هنا صراحةً؛ ومحروسة بفحص وجود `bootstrap` كي لا تكسر الصفحة
         إن غاب السكربت من قالبٍ آخر --}}
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
                fileName: @json(__('purchase_orders.titles.index')),
                title: @json(__('purchase_orders.titles.index')),
                subTitle: @json(config('app.name')),
                numericColumns: [4],
                exportButton: '#exportExcel',
                printButton: '#printReport',
                descriptionColumn: 3,
            });
        });
    </script>
@endsection
