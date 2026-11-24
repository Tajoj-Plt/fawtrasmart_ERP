@extends('manufacturing::master')

@section('title', 'التكاليف غير المباشرة')

@section('content')
    <div class="content-body">

        @section('page-actions')
            <a href="{{ route('manufacturing.indirectcosts.create') }}" class="btn btn-primary">
                <i class="feather icon-plus"></i> تكلفة جديدة
            </a>
            {{--
                أُخفي زرّ «تصدير» — المرحلة ١.

                الدالّة `IndirectCostsController@export` لا تُصدّر شيئاً: تُرجع
                رسالةً بعدد السجلات ورابطاً إلى `…indirectcosts.downloadExport`
                — وهو مسارٌ **غير مسجَّل في ملف المسارات إطلاقاً**، فترمي
                `route()` استثناء RouteNotFoundException ويسقط الطلب بخطأ 500.

                والتصدير ميزةٌ مبرمَجة في المرحلة ١٢ لكل تقارير التصنيع دفعةً
                واحدة. فإخفاء الزرّ الآن أصدق من زرٍّ يَعِد ولا يفي.
            --}}
        @endsection

        @include('stock::components.page-head', [
            'title' => 'التكاليف غير المباشرة',
            'icon' => 'pie-chart',
            'sub' => 'التكاليف التي تُوزَّع على أوامر التصنيع بالكمية أو بالتكلفة',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'التصنيع' => null,
                'التكاليف غير المباشرة' => null,
            ],
        ])

        @include('layouts.alerts.error')
        @include('stock::components.form-errors')
        @include('layouts.alerts.success')

        {{-- ══════════ المؤشّرات ══════════ --}}
        <div class="stk-metrics">
            @foreach ([
                ['totalCosts', 'hash', 'عدد السجلّات'],
                ['totalAmount', 'dollar-sign', 'إجمالي المبالغ'],
                ['avgAmount', 'bar-chart-2', 'متوسط المبلغ'],
                ['thisMonth', 'calendar', 'هذا الشهر'],
            ] as [$id, $icon, $label])
                <div class="stk-metric">
                    <span class="stk-metric__icon"><i class="feather icon-{{ $icon }}"></i></span>
                    <span class="stk-metric__value stk-num" id="{{ $id }}">0</span>
                    <span class="stk-metric__label">{{ $label }}</span>
                </div>
            @endforeach
        </div>

        {{-- ══════════ البحث والتصفية ══════════
             على قياس فلاتر «فواتير المبيعات»: الصفُّ الظاهر للحساب والأمر
             ونوع التوزيع والفترة، ثمّ «تابع لـ»، والباقي في «بحث متقدّم». --}}
        <div class="card stk-filters">
            <div class="card-body">
                <div class="stk-filters__head">
                    <h6 class="stk-filters__title"><i class="feather icon-filter"></i> بحث وتصفية</h6>
                    {{-- `data-mfg-toggle` لا `data-toggle`: القالب يحمّل Bootstrap 4 و5،
                         فيفتح أحدُهما اللوحة ويغلقها الآخر --}}
                    <button type="button" class="btn btn-sm btn-outline-secondary"
                            data-mfg-toggle="#advancedSearchForm" aria-expanded="false">
                        <i class="feather icon-sliders"></i> بحث متقدّم
                    </button>
                </div>

                <form id="filterForm">
                    <div class="row">
                        <div class="col-lg-3 col-md-6">
                            <div class="form-group">
                                <label for="account_id">حساب المصروف</label>
                                <select id="account_id" name="account_id" class="form-control select2">
                                    <option value="">جميع الحسابات</option>
                                    @foreach ($accounts as $account)
                                        <option value="{{ $account->id }}">{{ $account->code ? $account->code . ' — ' : '' }}{{ $account->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-lg-3 col-md-6">
                            <div class="form-group">
                                <label for="manufacturing_order_id">أمر التصنيع</label>
                                <select id="manufacturing_order_id" name="manufacturing_order_id" class="form-control select2">
                                    <option value="">جميع الأوامر</option>
                                    @foreach ($manufacturing_orders as $order)
                                        <option value="{{ $order->id }}">{{ $order->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-lg-2 col-md-6">
                            <div class="form-group">
                                <label for="based_on">نوع التوزيع</label>
                                <select id="based_on" name="based_on" class="form-control">
                                    <option value="">جميع الأنواع</option>
                                    <option value="1">بحسب الكمية</option>
                                    <option value="2">بحسب التكلفة</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-lg-4 col-md-6">
                            <div class="form-group">
                                <label>فترة التوزيع</label>
                                <div class="input-group">
                                    <input type="date" name="date_from_start" class="form-control" aria-label="من تاريخ">
                                    <input type="date" name="date_to_end" class="form-control" aria-label="إلى تاريخ">
                                </div>
                                <small class="stk-help">التوزيعات التي تقع فترتُها ضمن هذا النطاق</small>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        {{-- «تابع لـ» — انظر الجزء المشترك --}}
                        @include('manufacturing::partials.scope-filters', ['ajax' => true])
                    </div>

                    <div class="collapse" id="advancedSearchForm">
                        <div class="row">
                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label for="product_id">المنتج</label>
                                    <select id="product_id" name="product_id" class="form-control select2">
                                        <option value="">جميع المنتجات</option>
                                        @foreach ($products as $product)
                                            <option value="{{ $product->id }}">{{ $product->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-5 col-sm-12">
                                <div class="form-group">
                                    <label>نطاق المبلغ</label>
                                    <div class="input-group">
                                        <input type="number" step="0.01" name="total_min"
                                               class="form-control stk-num" placeholder="من">
                                        <input type="number" step="0.01" name="total_max"
                                               class="form-control stk-num" placeholder="إلى">
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-2 col-sm-6">
                                <div class="form-group">
                                    <label for="sort_field">ترتيب حسب</label>
                                    <select id="sort_field" name="sort_field" class="form-control">
                                        @foreach (['created_at' => 'تاريخ الإنشاء', 'total' => 'المبلغ', 'from_date' => 'التاريخ من', 'to_date' => 'التاريخ إلى'] as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-2 col-sm-6">
                                <div class="form-group">
                                    <label for="sort_direction">الاتجاه</label>
                                    <select id="sort_direction" name="sort_direction" class="form-control">
                                        <option value="desc">تنازلي</option>
                                        <option value="asc">تصاعدي</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="stk-filters__foot">
                        <button type="button" id="resetSearch" class="btn btn-outline-secondary">
                            <i class="feather icon-rotate-ccw"></i> مسح
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
                    <i class="feather icon-list"></i> السجلّات
                    <span class="stk-count" id="rows-count">0</span>
                </h6>

                <div class="d-flex align-items-center" style="gap:.6rem">
                    <div class="spinner-border spinner-border-sm text-primary d-none" id="loading-indicator" role="status"></div>
                    @include('stock::components.per-page', ['id' => 'perPageSelect'])
                </div>
            </div>

            <div id="results-container"></div>
        </div>
    </div>

    {{-- ══════════ نافذة الحذف ══════════ --}}
    <div class="modal fade" id="deleteModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content text-right">
                <div class="modal-header">
                    <h5 class="modal-title">حذف التكلفة غير المباشرة</h5>
                    <button type="button" class="close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="إغلاق">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <dl class="stk-def">
                        <div class="stk-def__row">
                            <dt>الحساب</dt>
                            <dd id="deleteAccountName"></dd>
                        </div>
                        <div class="stk-def__row">
                            <dt>المبلغ</dt>
                            <dd class="stk-num" id="deleteAmount"></dd>
                        </div>
                    </dl>
                    <p class="stk-empty__hint mb-0 mt-2">لا يمكن التراجع عن هذا الإجراء.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal" data-bs-dismiss="modal">
                        إلغاء
                    </button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteBtn">
                        <i class="feather icon-trash-2"></i> حذف نهائي
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        $(function () {
            const routes = {
                data: @json(route('manufacturing.indirectcosts.getData')),
                stats: @json(route('manufacturing.indirectcosts.getStats')),
                export: @json(route('manufacturing.indirectcosts.export')),
                show: @json(route('manufacturing.indirectcosts.show', ':id')),
                edit: @json(route('manufacturing.indirectcosts.edit', ':id')),
                destroy: @json(route('manufacturing.indirectcosts.destroy', ':id')),
            };

            const token = $('meta[name="csrf-token"]').attr('content');
            const symbol = () => (window.AppCurrency ? window.AppCurrency.symbol : '');

            const esc = value => $('<div>').text(value == null ? '' : value).html();
            const money = value => Number(value || 0).toLocaleString('en-US', {
                minimumFractionDigits: 2, maximumFractionDigits: 2,
            });
            const date = value => (value ? String(value).slice(0, 10) : '—');

            let currentPage = 1;
            let currentFilters = {};
            let deleteId = null;

            if ($.fn.select2) {
                $('#filterForm .select2').select2({ placeholder: 'الكل', allowClear: true, dir: 'rtl', width: '100%' });
            }

            function rowHtml(item) {
                const byQuantity = Number(item.based_on) === 1;
                const account = item.account || {};

                return ''
                    + '<tr>'
                    + '<td><div class="stk-media__body">'
                    + '  <a class="stk-media__title" href="' + routes.show.replace(':id', item.id) + '">'
                    + esc(account.name) + '</a>'
                    + '  <span class="stk-media__sub stk-num">#' + esc(account.code || '—') + '</span>'
                    + '</div></td>'

                    + '<td class="text-center"><span class="badge badge-light-' + (byQuantity ? 'primary' : 'success') + '">'
                    + (byQuantity ? 'حسب الكمية' : 'حسب التكلفة') + '</span></td>'

                    + '<td class="text-center"><span class="stk-num d-block">' + date(item.from_date) + '</span>'
                    + '  <span class="stk-media__sub stk-num">' + date(item.to_date) + '</span></td>'

                    + '<td>' + esc(item.branch_name || '—') + '</td>'

                    + '<td class="text-center"><span class="stk-num d-block">' + (item.entries_count || 0) + ' قيد</span>'
                    + '  <span class="stk-media__sub stk-num">' + (item.orders_count || 0) + ' أمر</span></td>'

                    + '<td class="text-center stk-num">' + Number(Number(item.unit_rate || 0).toFixed(6)) + '</td>'

                    + '<td class="text-center stk-num stk-balance">' + money(item.total) + ' ' + esc(symbol()) + '</td>'

                    + '<td class="text-center stk-num">' + date(item.created_at) + '</td>'

                    + '<td class="text-center"><div class="dropdown">'
                    + '  <button type="button" class="btn btn-sm btn-outline-secondary"'
                    + '     data-bs-toggle="dropdown" aria-expanded="false" aria-label="خيارات">'
                    + '    <i class="feather icon-more-vertical"></i></button>'
                    + '  <div class="dropdown-menu dropdown-menu-end">'
                    + '    <a class="dropdown-item" href="' + routes.show.replace(':id', item.id) + '">'
                    + '      <i class="feather icon-eye"></i> عرض</a>'
                    + '    <a class="dropdown-item" href="' + routes.edit.replace(':id', item.id) + '">'
                    + '      <i class="feather icon-edit-2"></i> تعديل</a>'
                    + '    <div class="dropdown-divider"></div>'
                    + '    <button type="button" class="dropdown-item text-danger delete-btn"'
                    + '      data-id="' + item.id + '" data-account="' + esc(account.name) + '"'
                    + '      data-amount="' + money(item.total) + '">'
                    + '      <i class="feather icon-trash-2"></i> حذف</button>'
                    + '  </div>'
                    + '</div></td>'
                    + '</tr>';
            }

            /* نافذة من خمس صفحات حول الحالية */
            function paginationHtml(pagination) {
                const current = pagination.current_page;
                const last = pagination.last_page;

                if (! last || last <= 1) {
                    return '<div class="stk-table-foot"><p class="stk-table-foot__count">'
                        + 'عرض <span class="stk-num">' + (pagination.total || 0) + '</span> سجلاً</p></div>';
                }

                const from = Math.max(1, current - 2);
                const to = Math.min(last, current + 2);

                const page = (label, target, disabled, active) => ''
                    + '<li class="page-item ' + (disabled ? 'disabled' : '') + ' ' + (active ? 'active' : '') + '">'
                    + (disabled || active
                        ? '<span class="page-link">' + label + '</span>'
                        : '<a class="page-link" href="#" data-page="' + target + '">' + label + '</a>')
                    + '</li>';

                let html = '<div class="stk-table-foot">'
                    + '<p class="stk-table-foot__count">عرض <span class="stk-num">' + (pagination.from || 0) + '</span>–'
                    + '<span class="stk-num">' + (pagination.to || 0) + '</span> من '
                    + '<span class="stk-num">' + (pagination.total || 0) + '</span></p>'
                    + '<nav><ul class="pagination mb-0">';

                html += page('<i class="feather icon-chevrons-right"></i>', 1, current === 1, false);
                html += page('<i class="feather icon-chevron-right"></i>', current - 1, current === 1, false);

                if (from > 1) html += '<li class="page-item disabled"><span class="page-link">…</span></li>';
                for (let i = from; i <= to; i++) html += page(i, i, false, i === current);
                if (to < last) html += '<li class="page-item disabled"><span class="page-link">…</span></li>';

                html += page('<i class="feather icon-chevron-left"></i>', current + 1, current === last, false);
                html += page('<i class="feather icon-chevrons-left"></i>', last, current === last, false);

                return html + '</ul></nav></div>';
            }

            function render(response) {
                const rows = response.data || [];
                const pagination = response.pagination || {};

                $('#rows-count').text(pagination.total || rows.length);

                if (! rows.length) {
                    return '<div class="card-body"><div class="stk-empty">'
                        + '<div class="stk-empty__icon"><i class="feather icon-pie-chart"></i></div>'
                        + '<h5 class="stk-empty__title">لا سجلّات</h5>'
                        + '<p class="stk-empty__hint">لا شيء يطابق معايير البحث الحالية</p>'
                        + '</div></div>';
                }

                return '<div class="table-responsive"><table class="table table-hover mb-0"><thead><tr>'
                    + '<th>الحساب</th>'
                    + '<th class="text-center" style="width:13%">نوع التوزيع</th>'
                    + '<th class="text-center" style="width:14%">فترة التكلفة</th>'
                    + '<th style="width:12%">تابع لـ</th>'
                    + '<th class="text-center" style="width:10%">القيود / الأوامر</th>'
                    + '<th class="text-center" style="width:10%">نصيب الوحدة</th>'
                    + '<th class="text-center" style="width:16%">إجمالي التكلفة</th>'
                    + '<th class="text-center" style="width:12%">أُنشئ في</th>'
                    + '<th class="text-center" style="width:8%">إجراءات</th>'
                    + '</tr></thead><tbody>'
                    + rows.map(rowHtml).join('')
                    + '</tbody></table></div>'
                    + paginationHtml(pagination);
            }

            function load(page) {
                currentPage = page || currentPage;
                $('#loading-indicator').removeClass('d-none');

                const filters = {};
                new FormData($('#filterForm')[0]).forEach((value, key) => {
                    if (value) filters[key] = value;
                });

                filters.page = currentPage;
                filters.per_page = $('#perPageSelect').val() || 10;
                currentFilters = filters;

                $.ajax({
                    url: routes.data,
                    method: 'GET',
                    data: Object.assign({ is_ajax: 1 }, filters),
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    cache: false,
                })
                    .done(function (response) {
                        if (response.success) {
                            $('#results-container').html(render(response));
                        } else {
                            $('#results-container').html(
                                '<div class="alert alert-danger m-2">تعذّر تحميل البيانات</div>'
                            );
                        }
                    })
                    .fail(function () {
                        $('#results-container').html(
                            '<div class="alert alert-danger m-2">تعذّر الاتصال بالخادم</div>'
                        );
                    })
                    .always(() => $('#loading-indicator').addClass('d-none'));
            }

            function loadStats() {
                $.getJSON(routes.stats).done(function (response) {
                    if (! response.success) return;

                    const stats = response.stats;
                    $('#totalCosts').text(Number(stats.total_costs || 0).toLocaleString('en-US'));
                    $('#totalAmount').text(money(stats.total_amount) + ' ' + symbol());
                    $('#avgAmount').text(money(stats.avg_amount) + ' ' + symbol());
                    $('#thisMonth').text(money(stats.this_month) + ' ' + symbol());
                });
            }

            $('#filterForm').on('submit', function (e) {
                e.preventDefault();
                load(1);
            });

            $('#filterForm').on('change input', 'input, select', function () {
                clearTimeout(window.indirectCostsTimer);
                window.indirectCostsTimer = setTimeout(() => load(1), 500);
            });

            $('#resetSearch').on('click', function () {
                $('#filterForm')[0].reset();
                if (window.mfgResetAffiliation) window.mfgResetAffiliation();
                if ($.fn.select2) $('#filterForm .select2').val('').trigger('change');
                load(1);
            });

            $('#perPageSelect').on('change', () => load(1));

            $('#results-container').on('click', '.pagination a', function (e) {
                e.preventDefault();
                load(parseInt($(this).data('page'), 10));
            });

            // التفويض إلى `document` لا إلى حاوية: القائمة المنسدلة تُنقل
            // إلى <body> لترتسم فوق كل شيء، فيخرج زرّها من نسل الحاوية
            $(document).on('click', '.delete-btn', function () {
                deleteId = $(this).data('id');
                $('#deleteAccountName').text($(this).data('account'));
                $('#deleteAmount').text($(this).data('amount') + ' ' + symbol());
                $('#deleteModal').modal('show');
            });

            $('#confirmDeleteBtn').on('click', function () {
                if (! deleteId) return;

                const $btn = $(this).prop('disabled', true);

                $.ajax({
                    url: routes.destroy.replace(':id', deleteId),
                    method: 'POST',
                    data: { _method: 'DELETE', _token: token },
                })
                    .done(function () {
                        $('#deleteModal').modal('hide');
                        load(currentPage);
                        loadStats();
                    })
                    .fail(() => Swal.fire({ icon: 'error', title: 'تعذّر الحذف' }))
                    .always(() => $btn.prop('disabled', false));
            });

            $('#exportBtn').on('click', function () {
                Swal.fire({
                    title: 'تصدير البيانات؟',
                    text: 'ستُصدَّر النتائج المطابقة للفلاتر الحالية.',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'نعم، صدّر',
                    cancelButtonText: 'إلغاء',
                    reverseButtons: true,
                }).then(result => {
                    if (! result.isConfirmed) return;

                    $.post(routes.export, Object.assign({ _token: token }, currentFilters))
                        .done(response => {
                            if (response && response.success) {
                                Swal.fire({ icon: 'success', title: 'جارٍ التصدير', timer: 1800, showConfirmButton: false });
                                if (response.url) window.location.href = response.url;
                            } else {
                                Swal.fire({ icon: 'error', title: 'تعذّر التصدير' });
                            }
                        })
                        .fail(() => Swal.fire({ icon: 'error', title: 'تعذّر التصدير' }));
                });
            });

            load(1);
            loadStats();
        });
    </script>
@endsection
