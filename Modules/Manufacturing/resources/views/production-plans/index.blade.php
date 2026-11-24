@extends('manufacturing::master')

@section('title', 'خطط الإنتاج')

@section('content')
    <div class="content-body">

        @include('stock::components.page-head', [
            'title' => 'خطط الإنتاج',
            'icon' => 'calendar',
            'sub' => 'خطط الإنتاج وبنودها، وما وُلِّد منها من أوامر تصنيع',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'التصنيع' => null,
                'خطط الإنتاج' => null,
            ],
            'actions' => hasPermission('stock.production_plans.create')
                ? '<a href="' . route('manufacturing.production-plans.create') . '" class="btn btn-primary">'
                    . '<i class="feather icon-plus"></i> خطة إنتاج جديدة</a>'
                : '',
        ])

        @include('layouts.alerts.error')
        @include('stock::components.form-errors')
        @include('layouts.alerts.success')

        {{-- ══════════ البحث ══════════ --}}
        <div class="card stk-filters">
            <div class="card-body">
                <div class="stk-filters__head">
                    <h6 class="stk-filters__title"><i class="feather icon-filter"></i> بحث</h6>
                </div>

                <form id="searchForm">
                    @csrf
                    <div class="row">
                        <div class="col-md-6 col-sm-12">
                            <div class="form-group">
                                <label for="searchInput">البحث</label>
                                <input type="text" id="searchInput" name="search" class="form-control"
                                       placeholder="الاسم أو الكود…">
                            </div>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="statusFilter">الحالة</label>
                                <select id="statusFilter" name="status" class="form-control">
                                    <option value="">كل الحالات</option>
                                    @foreach ($statuses as $status)
                                        <option value="{{ $status['value'] }}">{{ $status['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="employeeFilter">المسؤول</label>
                                <select id="employeeFilter" name="employee_id" class="form-control">
                                    <option value="">الجميع</option>
                                    @foreach ($employees as $employee)
                                        <option value="{{ $employee->id }}">
                                            {{ trim($employee->first_name . ' ' . ($employee->middle_name ?? '')) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        {{--
                            حقلا التاريخ في `col-md-6` و`input-group` كشاشة
                            التكاليف غير المباشرة: لحقل `type="date"` عرضٌ
                            أدنى يفرضه المتصفّح، فحقلان في ربع صفٍّ يفيضان
                            ويُقتطع أوّلُهما.
                        --}}
                        <div class="col-md-6 col-sm-12">
                            <div class="form-group">
                                {{-- تداخلُ الفترة لا احتواؤها — انظر getData --}}
                                <label>الفترة</label>
                                <div class="input-group">
                                    <input type="date" name="from_date" class="form-control" aria-label="من تاريخ">
                                    <input type="date" name="to_date" class="form-control" aria-label="إلى تاريخ">
                                </div>
                            </div>
                        </div>

                    </div>

                    <div class="row">
                        {{-- «تابع لـ» — انظر الجزء المشترك؛ الخططُ بلا مبالغ فلا مرشِّحَ عملة --}}
                        @include('manufacturing::partials.scope-filters', ['ajax' => true])
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
                    <i class="feather icon-list"></i> الخطط
                    <span class="stk-count" id="rows-count">0</span>
                </h6>

                <div class="d-flex align-items-center" style="gap:.6rem">
                    <div class="spinner-border spinner-border-sm text-primary d-none" id="loading-indicator" role="status"></div>
                    @include('stock::components.per-page', ['id' => 'perPageSelect', 'name' => 'per_page'])
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
                    <h5 class="modal-title">حذف خطة الإنتاج</h5>
                    <button type="button" class="close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="إغلاق">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="mb-1">ستُحذف <strong id="itemToDelete"></strong> ببنودها نهائياً.</p>
                    <p class="stk-empty__hint mb-0">
                        لا يمكن التراجع. والخطّةُ التي وُلِّد عن بنودها أمرٌ لا تُحذف — تُلغى.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal" data-bs-dismiss="modal">
                        إلغاء
                    </button>
                    <button type="button" id="confirmDeleteBtn" class="btn btn-danger">
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
                data: @json(route('manufacturing.production-plans.getData')),
                show: @json(route('manufacturing.production-plans.show', ':id')),
                edit: @json(route('manufacturing.production-plans.edit', ':id')),
                destroy: @json(route('manufacturing.production-plans.delete', ':id')),
            };

            // الإجراءاتُ بصلاحيات الدور — الخادمُ يحرسها ثانيةً على كل مسار
            const can = {
                show: @haspermission('stock.production_plans.show'),
                edit: @haspermission('stock.production_plans.edit'),
                destroy: @haspermission('stock.production_plans.delete'),
            };

            const token = $('meta[name="csrf-token"]').attr('content');
            const esc = value => $('<div>').text(value == null ? '' : value).html();
            const num = value => (value === null || value === undefined || value === '')
                ? '—'
                : Number(value).toLocaleString('en-US', { maximumFractionDigits: 4 });

            let currentPage = 1;
            let deleteId = null;

            function progressHtml(item) {
                const total = item.items_count || 0;
                const done = item.generated_count || 0;

                if (! total) return '<span class="stk-media__sub">لا بنود</span>';

                const percent = Math.round((done / total) * 100);

                // المولَّدُ غيرُ المكتمل: الخطّةُ تكتمل باكتمال أوامرها لا بتوليدها
                const finished = item.finished_count || 0;

                return '<div class="d-flex align-items-center" style="gap:.5rem"'
                    + ' title="وُلِّد ' + done + ' من ' + total + ' — اكتمل منها ' + finished + '">'
                    + '<div class="progress flex-grow-1" style="height:6px;min-width:70px">'
                    + '  <div class="progress-bar" role="progressbar" style="width:' + percent + '%"'
                    + '       aria-valuenow="' + percent + '" aria-valuemin="0" aria-valuemax="100"></div>'
                    + '</div>'
                    + '<span class="stk-num stk-media__sub">' + done + '/' + total + '</span>'
                    + '</div>';
            }

            function rowHtml(item) {
                const show = routes.show.replace(':id', item.id);
                const edit = routes.edit.replace(':id', item.id);
                const title = can.show
                    ? '<a class="stk-media__title" href="' + show + '">' + esc(item.name) + '</a>'
                    : '<span class="stk-media__title">' + esc(item.name) + '</span>';

                let actions = '';
                if (can.show) {
                    actions += '<a class="dropdown-item" href="' + show + '"><i class="feather icon-eye"></i> عرض</a>';
                }
                if (can.edit && item.editable) {
                    actions += '<a class="dropdown-item" href="' + edit + '"><i class="feather icon-edit-2"></i> تعديل</a>';
                }
                if (can.destroy && item.deletable) {
                    actions += '<div class="dropdown-divider"></div>'
                        + '<a class="dropdown-item text-danger delete-btn" href="#" data-id="' + item.id + '"'
                        + ' data-name="' + esc(item.name) + '"><i class="feather icon-trash-2"></i> حذف</a>';
                }
                if (! actions) {
                    actions = '<span class="dropdown-item text-muted">لا إجراءات متاحة</span>';
                }

                return ''
                    + '<tr>'
                    + '<td><div class="stk-media"><span class="stk-media__thumb"><i class="feather icon-calendar"></i></span>'
                    + '  <div class="stk-media__body">' + title
                    + '    <span class="stk-media__sub stk-num">#' + esc(item.code) + '</span>'
                    + '  </div></div></td>'
                    + '<td><span class="stk-media__sub stk-num">' + esc(item.from_date) + ' ← ' + esc(item.to_date) + '</span></td>'
                    + '<td><span class="stk-media__sub">' + esc(item.employee || '—') + '</span></td>'
                    + '<td>' + esc(item.branch_name || '—') + '</td>'
                    + '<td class="text-center stk-num">' + num(item.planned_quantity) + '</td>'
                    + '<td style="width:16%">' + progressHtml(item) + '</td>'
                    + '<td class="text-center"><span class="badge badge-' + esc(item.status_tone)
                    + ' bg-' + esc(item.status_tone) + '">' + esc(item.status_label) + '</span></td>'
                    + '<td class="text-center"><div class="dropdown">'
                    + '  <button type="button" class="btn btn-sm btn-outline-secondary"'
                    + '     data-bs-toggle="dropdown" aria-expanded="false" aria-label="خيارات">'
                    + '    <i class="feather icon-more-vertical"></i></button>'
                    + '  <div class="dropdown-menu dropdown-menu-end">' + actions + '</div>'
                    + '</div></td>'
                    + '</tr>';
            }

            /* نافذة من خمس صفحات — لا كلُّ الصفحات مهما بلغ عددها */
            function paginationHtml(pagination) {
                const current = pagination.current_page;
                const last = pagination.last_page;

                if (! last || last <= 1) return '';

                const from = Math.max(1, current - 2);
                const to = Math.min(last, current + 2);

                const page = (label, target, disabled, active) => ''
                    + '<li class="page-item ' + (disabled ? 'disabled' : '') + ' ' + (active ? 'active' : '') + '">'
                    + (disabled || active
                        ? '<span class="page-link">' + label + '</span>'
                        : '<a class="page-link" href="#" data-page="' + target + '">' + label + '</a>')
                    + '</li>';

                let html = '<div class="stk-table-foot">'
                    + '<p class="stk-table-foot__count">صفحة <span class="stk-num">' + current + '</span>'
                    + ' من <span class="stk-num">' + last + '</span></p>'
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
                        + '<div class="stk-empty__icon"><i class="feather icon-calendar"></i></div>'
                        + '<h5 class="stk-empty__title">لا خطط إنتاج</h5>'
                        + '<p class="stk-empty__hint">أنشئ خطّة تجمع ما ستنتجه في فترة، ثم ولِّد أوامرها دفعةً واحدة</p>'
                        + '</div></div>';
                }

                return '<div class="table-responsive"><table class="table table-hover mb-0"><thead><tr>'
                    + '<th style="width:24%">الخطّة</th>'
                    + '<th style="width:16%">الفترة</th>'
                    + '<th>المسؤول</th>'
                    + '<th style="width:12%">تابع لـ</th>'
                    + '<th class="text-center" style="width:12%">الكميّة المخطّطة</th>'
                    + '<th style="width:16%">الأوامر المولَّدة</th>'
                    + '<th class="text-center" style="width:10%">الحالة</th>'
                    + '<th class="text-center" style="width:8%">إجراءات</th>'
                    + '</tr></thead><tbody>'
                    + rows.map(rowHtml).join('')
                    + '</tbody></table></div>'
                    + paginationHtml(pagination);
            }

            function load(page) {
                currentPage = page || currentPage;
                $('#loading-indicator').removeClass('d-none');

                $.ajax({
                    url: routes.data,
                    method: 'GET',
                    data: $('#searchForm').serialize()
                        + '&per_page=' + ($('#perPageSelect').val() || 10)
                        + '&page=' + currentPage + '&is_ajax=1',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    cache: false,
                })
                    .done(function (response) {
                        if (response.success) $('#results-container').html(render(response));
                    })
                    .fail(function () {
                        $('#results-container').html(
                            '<div class="alert alert-danger m-2">تعذّر تحميل البيانات — حاول مرة أخرى</div>'
                        );
                    })
                    .always(() => $('#loading-indicator').addClass('d-none'));
            }

            $('#searchForm').on('submit', function (e) {
                e.preventDefault();
                load(1);
            });

            $('#searchForm').on('change input', 'input, select', function () {
                clearTimeout(window.plansTimer);
                window.plansTimer = setTimeout(() => load(1), 500);
            });

            $('#perPageSelect').on('change', () => load(1));

            $('#resetSearch').on('click', function () {
                $('#searchForm')[0].reset();
                if (window.mfgResetAffiliation) window.mfgResetAffiliation();
                load(1);
            });

            $('#results-container').on('click', '.pagination a', function (e) {
                e.preventDefault();
                load(parseInt($(this).data('page'), 10));
            });

            // التفويض إلى `document`: القائمة المنسدلة تُنقل إلى <body>
            $(document).on('click', '.delete-btn', function (e) {
                e.preventDefault();
                deleteId = $(this).data('id');
                $('#itemToDelete').text($(this).data('name'));
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
                    .done(function (response) {
                        $('#deleteModal').modal('hide');

                        if (response && response.success === false) {
                            Swal.fire({ icon: 'error', title: 'تعذّر الحذف', text: response.message });
                            return;
                        }

                        load(currentPage);
                    })
                    .fail(xhr => {
                        $('#deleteModal').modal('hide');
                        Swal.fire({
                            icon: 'error',
                            title: 'تعذّر الحذف',
                            text: (xhr.responseJSON && xhr.responseJSON.message) || '',
                        });
                    })
                    .always(() => $btn.prop('disabled', false));
            });

            load(1);
        });
    </script>
@endsection
