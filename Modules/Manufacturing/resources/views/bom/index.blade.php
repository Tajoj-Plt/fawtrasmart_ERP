@extends('manufacturing::master')

@section('title', 'قوائم مواد الإنتاج')

@section('content')
    <div class="content-body">

        @include('stock::components.page-head', [
            'title' => 'قوائم مواد الإنتاج',
            'icon' => 'layers',
            'sub' => 'مكوّنات كل منتج مصنَّع وكمّيّاتها وتكلفتها',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'التصنيع' => null,
                'قوائم المواد' => null,
            ],
            'actions' => '<a href="' . route('BOM.create') . '" class="btn btn-primary">'
                . '<i class="feather icon-plus"></i> قائمة مواد جديدة</a>',
        ])

        @include('layouts.alerts.success')
        @include('layouts.alerts.error')
        @include('stock::components.form-errors')

        {{-- ══════════ البحث والتصفية ══════════ --}}
        <div class="card stk-filters">
            <div class="card-body">
                <div class="stk-filters__head">
                    <h6 class="stk-filters__title"><i class="feather icon-filter"></i> بحث وتصفية</h6>
                </div>

                <form id="searchForm">
                    @csrf
                    <div class="row">
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="search">اسم القائمة أو رقمها</label>
                                <input type="text" id="search" name="search" class="form-control"
                                       placeholder="الاسم أو الكود…">
                            </div>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="product_id">المنتج</label>
                                <select id="product_id" name="product_id" class="form-control select2">
                                    <option value="">كل المنتجات</option>
                                    @foreach ($products as $product)
                                        <option value="{{ $product->id }}">{{ $product->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="status">الحالة</label>
                                <select id="status" name="status" class="form-control">
                                    <option value="">الكل</option>
                                    <option value="1">نشط</option>
                                    <option value="0">غير نشط</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="operation_id">عملية الإنتاج</label>
                                <select id="operation_id" name="operation_id" class="form-control select2">
                                    <option value="">كل العمليات</option>
                                    @foreach ($operations as $operation)
                                        <option value="{{ $operation->id }}">{{ $operation->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        {{-- «تابع لـ» — انظر الجزء المشترك --}}
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
                    <i class="feather icon-list"></i> القوائم
                    <span class="stk-count" id="rows-count">0</span>
                </h6>
                <div class="spinner-border spinner-border-sm text-primary d-none" id="loading-indicator" role="status"></div>
            </div>

            <div id="results-container"></div>
        </div>
    </div>

    {{-- ══════════ نافذة الحذف ══════════ --}}
    <div class="modal fade" id="deleteModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content text-right">
                <div class="modal-header">
                    <h5 class="modal-title">حذف قائمة المواد</h5>
                    <button type="button" class="close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="إغلاق">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="mb-1">ستُحذف <strong id="deleteItemName"></strong> نهائياً.</p>
                    <p class="stk-empty__hint mb-0">لا يمكن التراجع عن هذا الإجراء.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal" data-bs-dismiss="modal">
                        إلغاء
                    </button>
                    <button type="button" id="confirmDelete" class="btn btn-danger">
                        <i class="feather icon-trash-2"></i> حذف نهائي
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script>
        $(function () {
            const routes = {
                data: @json(route('BOM.getData')),
                show: @json(route('Bom.show', ':id')),
                edit: @json(route('Bom.edit', ':id')),
                destroy: @json(route('Bom.destroy', ':id')),
            };

            const escapeHtml = value => $('<div>').text(value == null ? '' : value).html();

            let currentPage = 1;
            let deleteId = null;

            if ($.fn.select2) {
                $('#searchForm .select2').select2({ placeholder: 'الكل', allowClear: true, dir: 'rtl', width: '100%' });
            }

            function rowHtml(material) {
                const show = routes.show.replace(':id', material.id);
                const edit = routes.edit.replace(':id', material.id);
                const active = Number(material.status) === 1;

                return ''
                    + '<tr>'
                    + '<td><a class="stk-media__title" href="' + show + '">' + escapeHtml(material.name) + '</a></td>'
                    + '<td>' + escapeHtml(material.product ? material.product.name : '—') + '</td>'
                    + '<td>' + escapeHtml(material.branch_name || '—') + '</td>'
                    + '<td class="text-center stk-num">' + escapeHtml(material.quantity) + '</td>'
                    + '<td class="text-center stk-num">'
                    + escapeHtml(material.last_total_cost) + ' ' + escapeHtml(window.AppCurrency ? window.AppCurrency.symbol : '')
                    + '</td>'
                    + '<td class="text-center"><span class="stk-state stk-state--' + (active ? 'on' : 'off') + '">'
                    + (active ? 'نشط' : 'غير نشط') + '</span></td>'
                    + '<td class="text-center">'
                    + (Number(material.default) === 1
                        ? '<span class="badge badge-light-info"><i class="feather icon-flag"></i> افتراضية</span>'
                        : '<span class="text-muted">—</span>')
                    + '</td>'
                    + '<td class="text-center"><div class="dropdown">'
                    + '  <button type="button" class="btn btn-sm btn-outline-secondary"'
                    + '     data-bs-toggle="dropdown" aria-expanded="false" aria-label="خيارات">'
                    + '    <i class="feather icon-more-vertical"></i></button>'
                    + '  <div class="dropdown-menu dropdown-menu-end">'
                    + '    <a class="dropdown-item" href="' + show + '"><i class="feather icon-eye"></i> عرض</a>'
                    + '    <a class="dropdown-item" href="' + edit + '"><i class="feather icon-edit-2"></i> تعديل</a>'
                    + '    <div class="dropdown-divider"></div>'
                    + '    <a class="dropdown-item text-danger delete-btn" href="#" data-id="' + material.id + '"'
                    + '       data-name="' + escapeHtml(material.name) + '"><i class="feather icon-trash-2"></i> حذف</a>'
                    + '  </div>'
                    + '</div></td>'
                    + '</tr>';
            }

            /* الترقيم: نافذةٌ من خمس صفحات حول الحالية — كان يرسم كل الصفحات
               مهما بلغ عددها، فتمتدّ مئة زرّ في سطر. */
            function paginationHtml(pagination) {
                const current = pagination.current_page;
                const last = pagination.last_page;

                if (last <= 1) return '';

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
                $('#rows-count').text(response.pagination ? response.pagination.total : rows.length);

                if (! rows.length) {
                    return '<div class="card-body"><div class="stk-empty">'
                        + '<div class="stk-empty__icon"><i class="feather icon-layers"></i></div>'
                        + '<h5 class="stk-empty__title">لا قوائم مواد</h5>'
                        + '<p class="stk-empty__hint">أضف قائمة جديدة، أو وسّع معايير البحث</p>'
                        + '</div></div>';
                }

                return '<div class="table-responsive"><table class="table table-hover mb-0"><thead><tr>'
                    + '<th>الاسم</th>'
                    + '<th style="width:18%">المنتج الرئيسي</th>'
                    + '<th style="width:13%">تابع لـ</th>'
                    + '<th class="text-center" style="width:10%">كمية الإنتاج</th>'
                    + '<th class="text-center" style="width:14%">إجمالي التكلفة</th>'
                    + '<th class="text-center" style="width:11%">الحالة</th>'
                    + '<th class="text-center" style="width:11%">الافتراضية</th>'
                    + '<th class="text-center" style="width:8%">إجراءات</th>'
                    + '</tr></thead><tbody>'
                    + rows.map(rowHtml).join('')
                    + '</tbody></table></div>'
                    + paginationHtml(response.pagination || {});
            }

            function load(page) {
                currentPage = page || currentPage;
                $('#loading-indicator').removeClass('d-none');

                $.ajax({
                    url: routes.data,
                    method: 'GET',
                    data: $('#searchForm').serialize() + '&page=' + currentPage + '&is_ajax=1',
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
                clearTimeout(window.bomSearchTimer);
                window.bomSearchTimer = setTimeout(() => load(1), 500);
            });

            $('#resetSearch').on('click', function () {
                $('#searchForm')[0].reset();
                if (window.mfgResetAffiliation) window.mfgResetAffiliation();
                if ($.fn.select2) $('#searchForm .select2').val('').trigger('change');
                load(1);
            });

            $('#results-container').on('click', '.pagination a', function (e) {
                e.preventDefault();
                load(parseInt($(this).data('page'), 10));
            });

            // التفويض إلى `document` لا إلى حاوية: القائمة المنسدلة تُنقل
            // إلى <body> لترتسم فوق كل شيء، فيخرج زرّها من نسل الحاوية
            $(document).on('click', '.delete-btn', function (e) {
                e.preventDefault();
                deleteId = $(this).data('id');
                $('#deleteItemName').text($(this).data('name'));
                $('#deleteModal').modal('show');
            });

            $('#confirmDelete').on('click', function () {
                if (! deleteId) return;

                const $btn = $(this).prop('disabled', true);

                $.ajax({
                    url: routes.destroy.replace(':id', deleteId),
                    method: 'POST',
                    data: { _method: 'DELETE', _token: $('meta[name="csrf-token"]').attr('content') },
                })
                    .done(function () {
                        $('#deleteModal').modal('hide');
                        load(currentPage);
                    })
                    .fail(function (xhr) {
                        alert((xhr.responseJSON && xhr.responseJSON.message) || 'تعذّر الحذف');
                    })
                    .always(() => $btn.prop('disabled', false));
            });

            load(1);
        });
    </script>
@endsection
