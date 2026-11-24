@extends('stock::master')

@section('title', 'الأذون المخزنية')

@section('content')
    @php
        $hasFilters = request()->hasAny([
            'branch', 'keywords', 'permission_source', 'store_house', 'status', 'currency',
            'permission_type', 'client', 'supplier', 'created_by', 'product', 'from_date', 'to_date', 'period_id',
        ]);
    @endphp

    <div class="content-body">

        @section('page-actions')
            @if (canAny(['stock.permits.create_addition', 'stock.permits.create_disbursement', 'stock.permits.create_transfer', 'stock.permits.create_opening']))
            <div class="btn-group">
                <button type="button" class="btn btn-primary dropdown-toggle"
                        data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="feather icon-plus"></i> إذن مخزني جديد
                </button>
                <div class="dropdown-menu dropdown-menu-end">
                    @permission('stock.permits.create_addition')
                    <a class="dropdown-item" href="{{ route('store_permits_management.create') }}">
                        <i class="feather icon-plus"></i> إضافة يدوية
                    </a>
                    @endpermission
                    @permission('stock.permits.create_disbursement')
                    <a class="dropdown-item" href="{{ route('store_permits_management.manual_disbursement') }}">
                        <i class="feather icon-minus"></i> صرف يدوي
                    </a>
                    @endpermission
                    @permission('stock.permits.create_transfer')
                    <a class="dropdown-item" href="{{ route('store_permits_management.manual_conversion') }}">
                        <i class="feather icon-repeat"></i> تحويل يدوي
                    </a>
                    @endpermission
                    @permission('stock.permits.create_opening')
                    <a class="dropdown-item" href="{{ route('store_permits_management.opening_balance') }}">
                        <i class="feather icon-star"></i> رصيد افتتاحي
                    </a>
                    @endpermission
                </div>
            </div>
            @endif

            <button type="button" class="btn btn-outline-secondary" id="printReport">
                <i class="feather icon-printer"></i> طباعة
            </button>

            @permission('stock.permits.export')
            <div class="btn-group">
                <button type="button" class="btn btn-outline-secondary dropdown-toggle"
                        data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="feather icon-download"></i> تصدير
                </button>
                <div class="dropdown-menu dropdown-menu-end">
                    <a class="dropdown-item" href="javascript:void(0)" id="exportExcel">
                        <i class="feather icon-grid"></i> Excel (المعروض)
                    </a>
                    <a class="dropdown-item" href="javascript:void(0)" onclick="exportPermits()">
                        <i class="feather icon-server"></i> تصدير كل النتائج
                    </a>
                </div>
            </div>
            @endpermission

            @permission('stock.permits.import')
            <button type="button" class="btn btn-outline-secondary" onclick="importPermits()">
                <i class="feather icon-upload"></i> استيراد
            </button>
            @endpermission
        @endsection

        @include('stock::components.page-head', [
            'title' => 'الأذون المخزنية',
            'icon' => 'file-text',
            'sub' => 'أذون الإضافة والصرف والتحويل وحالات اعتمادها',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'الأذون المخزنية' => null,
            ],
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        {{-- ══════════ البحث والتصفية ══════════ --}}
        <div class="card stk-filters">
            <div class="card-body">
                <div class="stk-filters__head">
                    <h6 class="stk-filters__title"><i class="feather icon-filter"></i> بحث وتصفية</h6>
                    <div class="d-flex align-items-center" style="gap:.5rem">
                        @if ($hasFilters)
                            <span class="stk-count">فلاتر مفعّلة</span>
                        @endif
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-toggle="collapse"
                                data-bs-toggle="collapse" data-target="#advancedSearchForm"
                                data-bs-target="#advancedSearchForm" aria-expanded="false">
                            <i class="feather icon-sliders"></i> بحث متقدّم
                        </button>
                    </div>
                </div>

                {{-- كان وسم <form> مكرّراً مرّتين متتاليتين، فينشأ نموذجان متداخلان --}}
                <form id="searchForm" data-search-url="{{ route('store_permits_management.index') }}">
                    @csrf
                    <div class="row">
                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="keywords">البحث</label>
                                <input type="text" id="keywords" name="keywords" class="form-control"
                                       value="{{ request('keywords') }}" placeholder="الرقم أو التفاصيل…">
                            </div>
                        </div>

                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="branch">الفرع</label>
                                <select id="branch" name="branch" class="form-control select2">
                                    <option value="">جميع الفروع</option>
                                    @foreach ($branches as $branch)
                                        <option value="{{ $branch->id }}"
                                            {{ request('branch') == $branch->id ? 'selected' : '' }}>
                                            {{ $branch->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="permission_source">مصدر الإذن</label>
                                <select id="permission_source" name="permission_source" class="form-control select2">
                                    <option value="">كل المصادر</option>
                                    @foreach ($permissionSources as $permissionSource)
                                        <option value="{{ $permissionSource->id }}"
                                            {{ request('permission_source') == $permissionSource->id ? 'selected' : '' }}>
                                            {{ $permissionSource->name }}
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
                                    <label for="store_house">المستودع</label>
                                    <select id="store_house" name="store_house" class="form-control select2">
                                        <option value="">جميع المستودعات</option>
                                        @foreach ($storeHouses as $storeHouse)
                                            <option value="{{ $storeHouse->id }}"
                                                {{ request('store_house') == $storeHouse->id ? 'selected' : '' }}>
                                                {{ $storeHouse->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label for="status">الحالة</label>
                                    <select id="status" name="status" class="form-control">
                                        <option value="">جميع الحالات</option>
                                        @foreach (['pending' => 'قيد الانتظار', 'approved' => 'موافق عليه', 'rejected' => 'مرفوض', 'processing' => 'قيد المعالجة'] as $value => $label)
                                            <option value="{{ $value }}" {{ request('status') === $value ? 'selected' : '' }}>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    {{-- العملة تبدأ على عملة النظام، و«كل العملات» تُلغي التصفية --}}
                                    <label for="currency">العملة</label>
                                    <select id="currency" name="currency" class="form-control">
                                        <option value="">كل العملات</option>
                                        @foreach ($currencies ?? [] as $currency)
                                            <option value="{{ $currency->code }}"
                                                {{ ($selectedCurrency ?? null) === $currency->code ? 'selected' : '' }}>
                                                {{ $currency->arabic_name ?: $currency->name }} ({{ $currency->code }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label for="permission_type">نوع الإذن</label>
                                    <select id="permission_type" name="permission_type" class="form-control">
                                        <option value="">كل الأنواع</option>
                                        @foreach ([1 => 'إذن إضافة', 2 => 'إذن صرف', 3 => 'تحويل يدوي'] as $value => $label)
                                            <option value="{{ $value }}" {{ request('permission_type') == $value ? 'selected' : '' }}>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            @foreach ([
                                ['client', 'العميل', $clients, 'trade_name'],
                                ['supplier', 'المورّد', $suppliers, 'trade_name'],
                                ['created_by', 'أُضيف بواسطة', $users, 'name'],
                                ['product', 'المنتج', $products, 'name'],
                            ] as [$field, $label, $options, $labelKey])
                                <div class="col-md-3 col-sm-6">
                                    <div class="form-group">
                                        <label for="{{ $field }}">{{ $label }}</label>
                                        <select id="{{ $field }}" name="{{ $field }}" class="form-control select2">
                                            <option value="">الكل</option>
                                            @foreach ($options as $option)
                                                <option value="{{ $option->id }}"
                                                    {{ request($field) == $option->id ? 'selected' : '' }}>
                                                    {{ $option->{$labelKey} }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            @endforeach

                            {{--
                                الفترة المحاسبية علاقةً: الإذنُ مختومٌ بها لحظةَ إنشائه،
                                والنطاقُ الافتراضي السنةُ المالية الجارية. ونطاقُ التاريخ
                                أدناه يبقى فلتراً حرّاً إلى جانبها.
                            --}}
                            @include('partials.accounting-period-filter', [
                                'fromName' => 'from_date',
                                'toName' => 'to_date',
                                'colClass' => 'col-md-3 col-sm-6',
                                'period' => $period ?? [],
                            ])

                            <div class="col-md-6 col-sm-12">
                                <div class="form-group">
                                    <label>نطاق التاريخ</label>
                                    <div class="input-group">
                                        <input type="date" name="from_date" class="form-control"
                                               value="{{ request('from_date') }}">
                                        <input type="date" name="to_date" class="form-control"
                                               value="{{ request('to_date') }}">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="stk-filters__foot">
                        <button type="button" id="resetSearch" class="btn btn-outline-secondary">
                            <i class="feather icon-rotate-ccw"></i> إعادة تعيين
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="feather icon-search"></i> بحث
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- ══════════ النتائج ══════════ --}}
        <div class="card stk-table-card">
            <div class="stk-table-head">
                <h6 class="stk-table-head__title">
                    <i class="feather icon-list"></i> الأذون
                    @if (method_exists($wareHousePermits, 'total'))
                        <span class="stk-count">{{ $wareHousePermits->total() }}</span>
                    @endif
                </h6>
                <div class="spinner-border spinner-border-sm text-primary d-none" id="loading-indicator" role="status"></div>
            </div>

            <div id="results-container">
                @include('stock::store_permits_management.partials.table', [
                    'wareHousePermits' => $wareHousePermits,
                ])
            </div>
        </div>
    </div>

    {{-- نافذة رفض واحدة — كانت واحدةً داخل كل صفّ --}}
    <div class="modal fade" id="rejectModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <form method="POST" id="rejectForm">
                @csrf
                <div class="modal-content text-right">
                    <div class="modal-header">
                        <h5 class="modal-title">رفض الإذن <span id="rejectNumber" class="stk-num"></span></h5>
                        <button type="button" class="close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="إغلاق">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>

                    <div class="modal-body">
                        <div class="form-group">
                            <label for="rejection_reason" class="stk-req">سبب الرفض</label>
                            <textarea id="rejection_reason" name="rejection_reason" class="form-control"
                                      rows="4" required placeholder="اكتب سبب رفض الإذن…"></textarea>
                        </div>

                        <div class="alert alert-warning mb-0">
                            <i class="feather icon-alert-triangle"></i>
                            سيُرفض الإذن نهائياً ولا يمكن التراجع عن هذا الإجراء.
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal" data-bs-dismiss="modal">
                            إلغاء
                        </button>
                        <button type="submit" class="btn btn-warning">
                            <i class="feather icon-x"></i> تأكيد الرفض
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection

@section('scripts')
    @include('stock::components.report-tools')

    <script>
        $(function () {
            stkReportTools({
                table: '#reportTable',
                fileName: 'أذون_المخزن',
                title: 'أذون المخزن',
                subTitle: @json(config('app.name')),
                numericColumns: [],
                exportButton: '#exportExcel',
                printButton: '#printReport',
                descriptionColumn: 3,
            });
        });
    </script>

    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        const permitRoutes = {
            index: @json(route('store_permits_management.index')),
            approve: @json(route('store_permits_management.approve', ['id' => ':id'])),
            reject: @json(route('store_permits_management.reject', ['id' => ':id'])),
            cancel: @json(route('store_permits_management.cancel_approval', ['id' => ':id'])),
            destroy: @json(route('store_permits_management.delete', ['id' => ':id'])),
            export: @json(route('store_permits_management.export')),
            import: @json(route('store_permits_management.import')),
        };

        const permitToken = @json(csrf_token());

        function exportPermits() {
            window.location.href = permitRoutes.export + '?' + $('#searchForm').serialize();
        }

        function importPermits() {
            const input = document.createElement('input');
            input.type = 'file';
            input.accept = '.xlsx,.xls,.csv';

            input.onchange = function (event) {
                const file = event.target.files[0];
                if (!  file) return;

                const body = new FormData();
                body.append('file', file);
                body.append('_token', permitToken);

                fetch(permitRoutes.import, { method: 'POST', body: body })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        } else {
                            Swal.fire({ icon: 'error', title: 'تعذّر الاستيراد', text: data.message || '' });
                        }
                    })
                    .catch(() => Swal.fire({ icon: 'error', title: 'تعذّر الاستيراد' }));
            };

            input.click();
        }

        $(function () {
            if ($.fn.select2) {
                $('#searchForm .select2').select2({ placeholder: 'الكل', allowClear: true, dir: 'rtl', width: '100%' });
            }

            window.loadData = function (page, pushState) {
                $('#loading-indicator').removeClass('d-none');

                const perPage = $('#perPage').length ? $('#perPage').val() : 10;
                const request = $('#searchForm').serialize() + '&page=' + (page || 1) + '&per_page=' + perPage;

                if (pushState !== false) {
                    const url = new URL(window.location.href);
                    new URLSearchParams(request).forEach((value, key) => {
                        if (value) url.searchParams.set(key, value);
                        else url.searchParams.delete(key);
                    });
                    window.history.pushState({ page: page }, '', url);
                }

                $.ajax({
                    url: permitRoutes.index,
                    method: 'GET',
                    data: request,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                })
                    .done(response => $('#results-container').html(response))
                    .fail(function () {
                        $('#results-container').html(
                            '<div class="alert alert-danger m-2">تعذّر تحميل البيانات</div>'
                        );
                    })
                    .always(() => $('#loading-indicator').addClass('d-none'));
            };

            /* إجراءات الصفوف بالتفويض: الجدول يُستبدل كاملاً عند كل تحميل،
               فربط المستمعات بعد كل استبدال (`initializeEvents`) لم يعد لازماً. */
            function confirmThen(options, run) {
                Swal.fire(Object.assign({
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'نعم',
                    cancelButtonText: 'إلغاء',
                    reverseButtons: true,
                }, options)).then(result => {
                    if (result.isConfirmed) run();
                });
            }

            function post(url, done) {
                $.post(url, { _token: permitToken })
                    .done(function (response) {
                        if (response && response.success === false) {
                            Swal.fire({ icon: 'error', title: 'تعذّر التنفيذ', text: response.message });
                            return;
                        }
                        done();
                    })
                    .fail(xhr => Swal.fire({
                        icon: 'error',
                        title: 'تعذّر التنفيذ',
                        text: (xhr.responseJSON && xhr.responseJSON.message) || '',
                    }));
            }

            // التفويض إلى `document` لا إلى حاوية: القائمة المنسدلة تُنقل
            // إلى <body> لترتسم فوق كل شيء، فيخرج زرّها من نسل الحاوية
            $(document).on('click', '.approve-btn', function (e) {
                e.preventDefault();
                const id = $(this).data('id');

                confirmThen(
                    { title: 'الموافقة على الإذن؟', text: '#' + $(this).data('number') },
                    () => post(permitRoutes.approve.replace(':id', id), () => loadData())
                );
            });

            $(document).on('click', '.cancel-approval-btn', function (e) {
                e.preventDefault();
                const id = $(this).data('id');

                confirmThen(
                    {
                        title: 'إلغاء الموافقة؟',
                        text: 'ستُرجَع الكميات للمستودع وتُعكَس القيود المحاسبية.',
                        icon: 'warning',
                    },
                    () => post(permitRoutes.cancel.replace(':id', id), () => loadData())
                );
            });

            $(document).on('click', '.delete-btn', function (e) {
                e.preventDefault();
                const id = $(this).data('id');

                confirmThen(
                    { title: 'حذف الإذن؟', text: '#' + $(this).data('number'), icon: 'warning' },
                    () => $.ajax({
                        url: permitRoutes.destroy.replace(':id', id),
                        method: 'POST',
                        data: { _method: 'DELETE', _token: permitToken },
                    })
                        .done(() => loadData())
                        .fail(() => Swal.fire({ icon: 'error', title: 'تعذّر الحذف' }))
                );
            });

            $(document).on('click', '.reject-btn', function (e) {
                e.preventDefault();

                $('#rejectNumber').text('#' + $(this).data('number'));
                $('#rejection_reason').val('');
                $('#rejectForm').attr('action', permitRoutes.reject.replace(':id', $(this).data('id')));
                $('#rejectModal').modal('show');
            });

            $('#results-container').on('click', '.pagination-link', function (e) {
                e.preventDefault();
                loadData(parseInt($(this).data('page'), 10));
            });

            $('#searchForm').on('submit', function (e) {
                e.preventDefault();
                loadData(1);
            });

            $('#searchForm').on('change input', 'input, select', function () {
                clearTimeout(window.permitsSearchTimer);
                window.permitsSearchTimer = setTimeout(() => loadData(1), 500);
            });

            $('#resetSearch').on('click', function () {
                $('#searchForm')[0].reset();
                if ($.fn.select2) $('#searchForm .select2').val('').trigger('change');
                loadData(1);
            });
        });
    </script>
@endsection
