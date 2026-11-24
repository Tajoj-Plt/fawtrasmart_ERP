{{--
    قائمة الموردين — أُعيد بناؤها على نمط تصميم «طلبات الشراء»
    (`ordersPurchase/index.blade.php`) توحيداً لشكل شاشات فهرسة وحدة
    المشتريات كلّها.

    منطق البحث/الترقيم يبقى سكربتها الخاصّ بلا تغيير (شكل استجابة Ajax مختلف
    عن بقيّة الشاشات: `html`/`pagination`/`total` لا `data`/`pagination`
    /`total`/`success`؛ حاوية الجدول `#suppliers-table` لا `#table-container`؛
    الحذف عبر Modal بنموذج POST حقيقي لا رابط Ajax؛ تبديل الحالة عبر
    `window.changeStatus()` العامّة) — فقط الغلاف البصري تبدّل.

    ما يأتي من الإعدادات:
      • الألوان والخط           ← «مظهر النظام» عبر متغيّرات CSS
      • العملة والكسور والفواصل ← «العملات»
      • صيغة التاريخ والاتجاه   ← إعدادات اللغة النشطة
      • قائمة العملات في التصفية ← «العملات» (العملات المفعَّلة)
--}}
@extends('purchases::master')

@section('title')
    {{ __('purchase_cycle.supplier.section') }}
@stop

@section('content')
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @include('purchases::purchases.purchase_requests.partials.sales-theme')
    @include('components.hint-tips')
    @include('purchases::purchases.purchase_requests.partials.row-menu-fix')

    <div class="content-body sl-scope">

        @section('page-actions')
            {{-- الإجراءاتُ انتقلت إلى «بطاقة الإجراءات» تحت الإحصاءات، على
                 قياس شاشة العملاء (`client::partials.action_card`). ويبقى
                 المؤشّرُ هنا لأنّ سكربتَ البحث يقلبه بمعرّفه. --}}
            <div id="loading-spinner" class="spinner-border spinner-border-sm text-primary d-none align-self-center" role="status" aria-hidden="true"></div>
        @endsection

        @include('purchases::purchases.purchase_requests.partials.page-head', [
            'title' => __('purchase_cycle.supplier.section'),
            'icon' => 'truck',
            'crumbs' => [
                __('purchase_orders.breadcrumb.home') => route('purchases.dashboard'),
                __('purchase_cycle.supplier.section') => null,
            ],
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        {{-- لوحة إرشاد الشاشة — مطويّةً افتراضياً --}}
        @include('purchases::components.guide', [
            'key' => 'suppliers.index',
            'title' => __('purchase_cycle.guide.supplier.index.title'),
            'lead' => __('purchase_cycle.guide.supplier.what_is'),
            'open' => false,
            'blocks' => [
                __('purchase_cycle.guide.supplier.required.title') => [
                    __('purchase_cycle.guide.supplier.required.a'),
                    __('purchase_cycle.guide.supplier.required.b'),
                    __('purchase_cycle.guide.supplier.required.c'),
                ],
                __('purchase_cycle.guide.supplier.balance.title') => [
                    __('purchase_cycle.guide.supplier.balance.a'),
                    __('purchase_cycle.guide.supplier.balance.b'),
                    __('purchase_cycle.guide.supplier.balance.c'),
                    __('purchase_cycle.guide.supplier.balance.d'),
                ],
                __('purchase_cycle.guide.supplier.inactive.title') => [
                    __('purchase_cycle.guide.supplier.inactive.a'),
                    __('purchase_cycle.guide.supplier.inactive.b'),
                ],
            ],
            'facts' => [
                __('purchase_cycle.guide.supplier.facts.account'),
                __('purchase_cycle.guide.supplier.facts.balance'),
            ],
        ])

        @include('purchases::components.subscription-bar', ['limit' => $subscriptionLimit])


        {{-- ══════════ الإحصاءات ══════════ --}}
        <div class="sl-stats">
            <div class="sl-stat">
                <span class="sl-stat__icon"><i class="fas fa-truck"></i></span>
                <div class="sl-stat__body">
                    <p class="sl-stat__value" data-stat="total">{{ \App\Support\LanguageSettings::formatNumber($stats['total'] ?? 0, 0) }}</p>
                    <p class="sl-stat__label">{{ __('purchase_cycle.supplier.section') }}</p>
                </div>
            </div>

            <div class="sl-stat">
                <span class="sl-stat__icon sl-stat__icon--success"><i class="fas fa-check-circle"></i></span>
                <div class="sl-stat__body">
                    <p class="sl-stat__value" data-stat="active">{{ \App\Support\LanguageSettings::formatNumber($stats['active'] ?? 0, 0) }}</p>
                    <p class="sl-stat__label">{{ __('purchase_cycle.supplier.active') }}</p>
                </div>
            </div>

            <div class="sl-stat">
                <span class="sl-stat__icon sl-stat__icon--warning"><i class="fas fa-hourglass-half"></i></span>
                <div class="sl-stat__body">
                    <p class="sl-stat__value" data-stat="due">{{ \App\Support\LanguageSettings::formatNumber($stats['due'] ?? 0, 0) }}</p>
                    <p class="sl-stat__label">{{ __('purchase_cycle.supplier.due') }}</p>
                </div>
            </div>

            <div class="sl-stat">
                <span class="sl-stat__icon sl-stat__icon--danger"><i class="fas fa-ban"></i></span>
                <div class="sl-stat__body">
                    <p class="sl-stat__value" data-stat="inactive">{{ \App\Support\LanguageSettings::formatNumber($stats['inactive'] ?? 0, 0) }}</p>
                    <p class="sl-stat__label">{{ __('purchase_cycle.supplier.inactive') }}</p>
                </div>
            </div>
        </div>

        {{-- ══════════ بطاقة الإجراءات ══════════
             نظيرُ `client::partials.action_card`: زرٌّ أساسيٌّ ظاهر لما
             يُستعمَل كلَّ مرّة، وقائمةٌ منسدلة لما عداه — كلُّ بندٍ
             بأيقونته واسمه، لا صفٌّ من الرموز الصامتة. --}}
        <div class="card" id="actionCard">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-end align-items-center gap-2">
                    @permission('purchases.suppliers.create')
                        <a href="{{ route('SupplierManagement.create') }}" class="btn btn-primary">
                            <i class="fas fa-plus-circle"></i>
                            <span>{{ __('purchase_cycle.actions.create') }}</span>
                        </a>
                    @endpermission

                    <div class="dropdown">
                        <button class="btn btn-outline-secondary dropdown-toggle" type="button"
                            id="supplierActionsDropdown" data-bs-toggle="dropdown"
                            data-bs-display="static" aria-expanded="false">
                            <i class="fas fa-ellipsis-h"></i>
                            <span class="d-none d-md-inline">المزيد</span>
                        </button>

                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="supplierActionsDropdown">
                            <li><h6 class="dropdown-header">{{ __('purchase_cycle.supplier.section') }}</h6></li>

                            <li>
                                <a class="dropdown-item" href="{{ route('purchases.supplier-wallet.index') }}">
                                    <i class="fas fa-wallet me-2 text-primary"></i>محافظ الموردين
                                </a>
                            </li>

                            @permission('purchases.payments.view')
                                <li>
                                    <a class="dropdown-item" href="{{ route('PaymentSupplier.indexPurchase') }}">
                                        <i class="fas fa-money-bill-wave me-2 text-primary"></i>مدفوعات الموردين
                                    </a>
                                </li>
                            @endpermission

                            <li><hr class="dropdown-divider"></li>
                            <li><h6 class="dropdown-header">التقارير</h6></li>

                            <li>
                                <a class="dropdown-item" href="{{ route('ReportsPurchases.index') }}">
                                    <i class="fas fa-chart-line me-2 text-primary"></i>تقارير المشتريات
                                </a>
                            </li>
                        </ul>
                    </div>
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
                                <label for="employee_search">{{ __('purchase_cycle.fields.supplier') }}</label>
                                <input type="text" name="employee_search" class="form-control search-input"
                                    id="employee_search" value="{{ request('employee_search') }}"
                                    placeholder="{{ __('purchase_cycle.fields.supplier') }}">
                            </div>
                        </div>

                        <div class="col-lg-4 col-md-6">
                            <div class="form-group">
                                <label for="supplier_number">{{ __('purchase_cycle.supplier.number') }}</label>
                                <input type="text" name="supplier_number" class="form-control search-input"
                                    id="supplier_number" value="{{ request('supplier_number') }}"
                                    placeholder="{{ __('purchase_cycle.supplier.number') }}">
                            </div>
                        </div>

                        <div class="col-lg-4 col-md-6">
                            <div class="form-group">
                                <label for="status">{{ __('purchase_cycle.fields.status') }}</label>
                                <select name="status" class="form-control search-input" id="status">
                                    <option value="">{{ __('purchase_cycle.statuses.all') }}</option>
                                    <option value="active" @selected(request('status') === 'active')>{{ __('purchase_cycle.supplier.active') }}</option>
                                    <option value="inactive" @selected(request('status') === 'inactive')>{{ __('purchase_cycle.supplier.inactive') }}</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="collapse" id="advancedSearchForm">
                        <div class="row">
                            <div class="col-lg-3 col-md-6">
                                <div class="form-group">
                                    <label for="email">{{ __('purchase_cycle.supplier.email') }}</label>
                                    <input type="email" name="email" class="form-control search-input" id="email"
                                        value="{{ request('email') }}">
                                </div>
                            </div>

                            <div class="col-lg-3 col-md-6">
                                <div class="form-group">
                                    <label for="mobile">{{ __('purchase_cycle.supplier.mobile') }}</label>
                                    <input type="text" name="mobile" class="form-control search-input" id="mobile"
                                        value="{{ request('mobile') }}">
                                </div>
                            </div>

                            <div class="col-lg-3 col-md-6">
                                <div class="form-group">
                                    <label for="phone">{{ __('purchase_cycle.supplier.phone') }}</label>
                                    <input type="text" name="phone" class="form-control search-input" id="phone"
                                        value="{{ request('phone') }}">
                                </div>
                            </div>

                            <div class="col-lg-3 col-md-6">
                                <div class="form-group">
                                    <label for="address">{{ __('purchase_cycle.supplier.address') }}</label>
                                    <input type="text" name="address" class="form-control search-input" id="address"
                                        value="{{ request('address') }}">
                                </div>
                            </div>

                            {{-- فلتر العملة حُذف: العملة واحدةٌ من «العملات»
                                 فلا معنى للتصفية بها --}}
                            <div class="col-lg-3 col-md-6">
                                <div class="form-group">
                                    <label for="tax_number">{{ __('purchase_cycle.supplier.tax_number') }}</label>
                                    <input type="text" name="tax_number" class="form-control search-input"
                                        id="tax_number" value="{{ request('tax_number') }}">
                                </div>
                            </div>

                            <div class="col-lg-3 col-md-6">
                                <div class="form-group">
                                    <label for="commercial_registration">{{ __('purchase_cycle.supplier.commercial_registration') }}</label>
                                    <input type="text" name="commercial_registration" class="form-control search-input"
                                        id="commercial_registration" value="{{ request('commercial_registration') }}">
                                </div>
                            </div>

                            <div class="col-lg-3 col-md-6">
                                <div class="form-group">
                                    <label for="created_by">{{ __('purchase_cycle.invoice.created_by') }}</label>
                                    <select name="created_by" class="form-control search-input" id="created_by">
                                        <option value="">{{ __('purchase_cycle.invoice.created_by') }}</option>
                                        @foreach ($users as $user)
                                            <option value="{{ $user->id }}" @selected(request('created_by') == $user->id)>
                                                {{ $user->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="col-lg-3 col-md-6">
                                <div class="form-group">
                                    <label for="employee_id">الموظف المسؤول</label>
                                    <select name="employee_id" class="form-control search-input" id="employee_id">
                                        <option value="">الموظف المسؤول</option>
                                        @foreach ($employees as $employee)
                                            <option value="{{ $employee->id }}" @selected(request('employee_id') == $employee->id)>
                                                {{ $employee->full_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="col-lg-3 col-md-6">
                                <div class="form-group">
                                    <label for="postal_code">{{ __('purchase_cycle.supplier.address') }}</label>
                                    <input type="text" name="postal_code" class="form-control search-input"
                                        id="postal_code" value="{{ request('postal_code') }}">
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
                    <i class="fas fa-list"></i> {{ __('purchase_cycle.supplier.section') }}
                    <span class="sl-count results-info">
                        {{ __('purchase_cycle.search.results_count', ['count' => \App\Support\LanguageSettings::formatNumber($suppliers->total(), 0)]) }}
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
                <div id="suppliers-table">
                    @include('purchases::purchases.supplier_management.partials.suppliers_table', ['suppliers' => $suppliers])
                </div>
            </div>

            <div class="sl-table-foot" id="pagination-top">
                @include('purchases::purchases.supplier_management.partials.pagination', ['suppliers' => $suppliers])
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    @php
        use Modules\Purchases\Services\PurchaseSettings;

        // `@json` لا يحتمل نداءً فيه فاصلة، فتُبنى المصفوفة هنا ثم تُمرَّر
        $supplierListConfig = [
            'routes' => [
                'index' => route('SupplierManagement.index'),
                'status' => route('SupplierManagement.updateStatus', ['id' => '__ID__']),
            ],
            'rtl' => \App\Support\LanguageSettings::isRtl(),
            // إعداد «منع المستندات على مورد موقوف» (SupplierGuard::assertActive)
            // — يظهر تنبيهاً إضافياً عند إيقاف مورّد بدل أن يُفاجأ به المستخدم
            // لاحقاً عند محاولة حفظ فاتورة له
            'blockInactive' => PurchaseSettings::enabled('supplier_block_inactive'),
            'i18n' => [
                'results' => __('purchase_cycle.search.results_count', ['count' => '__COUNT__']),
                'noResults' => __('purchase_cycle.empty.filtered'),
                'loadFailed' => __('purchase_cycle.messages.validation_failed'),
                'confirmTitle' => __('purchase_orders.confirm.save_title'),
                'yes' => __('purchase_orders.confirm.yes'),
                'no' => __('purchase_orders.confirm.no'),
                'inactiveBlockWarning' => __('purchase_cycle.supplier.inactive_block_warning'),
            ],
        ];
    @endphp

    <script>
        window.supplierList = @json($supplierListConfig);

        $(document).ready(function() {
            let searchTimeout;

            const routes = window.supplierList.routes;
            const strings = window.supplierList.i18n;

            ['#status', '#created_by', '#employee_id'].forEach(function(selector) {
                if ($(selector).length) {
                    $(selector).select2({
                        allowClear: true,
                        width: '100%',
                        dir: window.supplierList.rtl ? 'rtl' : 'ltr',
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

            $('#status, #created_by, #employee_id').on('select2:select select2:clear', function() {
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
                $('#status, #created_by, #employee_id').val(null).trigger('change');
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
                        $('#suppliers-table').html(response.html || '');
                        $('#pagination-top').html(response.pagination || '');
                        if (typeof response.total !== 'undefined') {
                            $('.results-info').text(
                                response.total > 0 ?
                                strings.results.replace('__COUNT__', response.total) :
                                strings.noResults
                            );
                        }
                        // البطاقاتُ تتبع الجدول: التصفيةُ وتبديلُ الحالة يعيدان أرقامَها
                        if (response.stats) {
                            Object.keys(response.stats).forEach(function (key) {
                                $('[data-stat="' + key + '"]').text(Number(response.stats[key]).toLocaleString(document.documentElement.lang === 'ar' ? 'ar-EG' : 'en-US'));
                            });
                        }
                    },
                    error: function() {
                        $('#suppliers-table').html(
                            '<div class="alert alert-danger text-center mb-0">' + strings.loadFailed + '</div>'
                        );
                    },
                    complete: hideLoading,
                });
            }

            function showLoading() {
                $('#suppliers-table').css('opacity', '0.6');
                $('#loading-spinner').removeClass('d-none');
                $('#searchBtn').prop('disabled', true);
            }

            function hideLoading() {
                $('#suppliers-table').css('opacity', '1');
                $('#loading-spinner').addClass('d-none');
                $('#searchBtn').prop('disabled', false);
            }

            // شارة الحالة في الجدول تنادي هذه الدالّة، فتبقى عامّة
            window.changeStatus = function(id, status, name) {
                // إيقاف مورّد و«منع المستندات على مورد موقوف» مفعَّل: يُحذَّر
                // المستخدم هنا قبل الحفظ، لا أن يُفاجأ لاحقاً برفض فاتورة
                var showBlockWarning = status == 0 && window.supplierList.blockInactive;

                Swal.fire({
                    title: strings.confirmTitle,
                    text: showBlockWarning ? (name + ' — ' + strings.inactiveBlockWarning) : name,
                    icon: showBlockWarning ? 'warning' : 'question',
                    showCancelButton: true,
                    confirmButtonText: strings.yes,
                    cancelButtonText: strings.no,
                }).then(function(result) {
                    if (!result.isConfirmed) {
                        return;
                    }

                    $.ajax({
                        url: routes.status.replace('__ID__', id),
                        method: 'POST',
                        data: {
                            _token: $('meta[name="csrf-token"]').attr('content'),
                            status: status,
                        },
                        success: function() {
                            performSearch();
                        },
                        error: function(jqXHR) {
                            // السبب الفعلي — كمنع إيقاف مورّدٍ له رصيد —
                            // يصل في response.message، لا نص «حدث خطأ» العام
                            var message = (jqXHR.responseJSON && jqXHR.responseJSON.message) || strings.loadFailed;
                            Swal.fire(message, '', 'error');
                        },
                    });
                });
            };
        });
    </script>
@endsection
