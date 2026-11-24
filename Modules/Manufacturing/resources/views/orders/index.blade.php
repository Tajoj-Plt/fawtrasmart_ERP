@extends('manufacturing::master')

@section('title', 'أوامر التصنيع')

@section('content')
    <div class="content-body">

        @section('page-actions')
            <a href="{{ route('manufacturing.orders.create') }}" class="btn btn-primary">
                <i class="feather icon-plus"></i> أمر تصنيع جديد
            </a>
            <button type="button" class="btn btn-outline-secondary" id="export-btn">
                <i class="feather icon-download"></i> تصدير
            </button>
        @endsection

        @include('stock::components.page-head', [
            'title' => 'أوامر التصنيع',
            'icon' => 'tool',
            'sub' => 'أوامر الإنتاج وحالاتها وتكاليفها',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'التصنيع' => null,
                'أوامر التصنيع' => null,
            ],
        ])

        @include('layouts.alerts.error')
        @include('stock::components.form-errors')
        @include('layouts.alerts.success')

        {{-- ══════════ البحث والتصفية ══════════
             على قياس فلاتر «فواتير المبيعات»: الصفُّ الظاهر للبحث والمنتج
             والحالة ونطاق التاريخ، ثمّ «تابع لـ»، والباقي في «بحث متقدّم». --}}
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

                <form id="filter-form">
                    <div class="row">
                        <div class="col-lg-3 col-md-6">
                            <div class="form-group">
                                <label for="search">اسم الأمر أو رقمه</label>
                                <input type="text" id="search" name="search" class="form-control"
                                       placeholder="الاسم أو الكود…">
                                <small class="stk-help">يبحث في اسم الأمر ورقمه</small>
                            </div>
                        </div>

                        <div class="col-lg-3 col-md-6">
                            <div class="form-group">
                                <label for="product_id">المنتج</label>
                                <select id="product_id" name="product_id" class="form-control select2">
                                    <option value="">كل المنتجات</option>
                                    @foreach ($products as $product)
                                        <option value="{{ $product->id }}">{{ $product->name }}</option>
                                    @endforeach
                                </select>
                                <small class="stk-help">المنتج المصنَّع في الأمر</small>
                            </div>
                        </div>

                        <div class="col-lg-2 col-md-6">
                            <div class="form-group">
                                <label for="status">الحالة</label>
                                {{-- الحالات المحفوظة فعلاً في العمود، لا التسميات القديمة
                                     («نشط»/«منتهي») التي لا تطابق أي قيمة مخزَّنة --}}
                                <select id="status" name="status" class="form-control">
                                    <option value="">كل الحالات</option>
                                    @foreach ([
                                        'pending' => 'جديد',
                                        'materials_received' => 'تم استلام المواد',
                                        'in_progress' => 'قيد التنفيذ',
                                        'completed' => 'مكتمل',
                                        'closed' => 'مغلق',
                                        'cancelled' => 'ملغي',
                                    ] as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-lg-4 col-md-6">
                            <div class="form-group">
                                <label for="from_date">فترة الأمر</label>
                                <div class="input-group">
                                    <input type="date" id="from_date" name="from_date" class="form-control" aria-label="من تاريخ">
                                    <input type="date" name="to_date" class="form-control" aria-label="إلى تاريخ">
                                </div>
                                <small class="stk-help">الأوامر التي تقع فترتُها ضمن هذا النطاق</small>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        {{-- «تابع لـ» — انظر الجزء المشترك --}}
                        @include('manufacturing::partials.scope-filters', ['ajax' => true])
                    </div>

                    <div class="collapse" id="advancedSearchForm">
                        <div class="row">
                            <div class="col-md-4 col-sm-6">
                                <div class="form-group">
                                    <label for="material_list_id">قائمة المواد</label>
                                    <select id="material_list_id" name="material_list_id" class="form-control select2">
                                        <option value="">كل القوائم</option>
                                        @foreach ($materialLists as $list)
                                            <option value="{{ $list->id }}">{{ $list->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-4 col-sm-6">
                                <div class="form-group">
                                    <label for="client_id">العميل</label>
                                    <select id="client_id" name="client_id" class="form-control select2">
                                        <option value="">كل العملاء</option>
                                        @foreach ($clients as $client)
                                            <option value="{{ $client->id }}">{{ $client->trade_name }} — #{{ $client->code }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-4 col-sm-6">
                                <div class="form-group">
                                    <label for="production_stage_id">المرحلة الإنتاجية</label>
                                    <select id="production_stage_id" name="production_stage_id" class="form-control select2">
                                        <option value="">كل المراحل</option>
                                        @foreach ($productionStages as $stage)
                                            <option value="{{ $stage->id }}">{{ $stage->stage_name ?? $stage->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="stk-filters__foot">
                        <button type="button" id="reset-btn" class="btn btn-outline-secondary">
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
                    <i class="feather icon-list"></i> الأوامر
                    <span class="stk-count" id="total-count">0</span>
                </h6>
                <div class="d-flex align-items-center" style="gap:.6rem">
                    <div class="spinner-border spinner-border-sm text-primary d-none" id="loading-indicator" role="status"></div>
                    @include('stock::components.per-page', ['id' => 'perPageSelect'])
                </div>
            </div>

            <div id="results-container"></div>
        </div>
    </div>

    <div class="modal fade" id="deleteOrderModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content text-right">
                <div class="modal-header">
                    <h5 class="modal-title">حذف أمر التصنيع</h5>
                    <button type="button" class="close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="إغلاق">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="mb-1">سيُحذف الأمر <strong id="del_name"></strong> نهائياً.</p>
                    <p class="stk-empty__hint mb-0">لا يمكن التراجع عن هذا الإجراء.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal" data-bs-dismiss="modal">
                        إلغاء
                    </button>
                    {{--
                        نموذج DELETE لا رابط GET — انظر التعليق في
                        `orders/partials/delete-modal.blade.php`.
                    --}}
                    <form id="del_form" method="POST" class="d-inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger">
                            <i class="feather icon-trash-2"></i> حذف نهائي
                        </button>
                    </form>
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
                data: @json(route('manufacturing.orders.data')),
                show: @json(route('manufacturing.orders.show', ':id')),
                edit: @json(route('manufacturing.orders.edit', ':id')),
                destroy: @json(route('manufacturing.orders.delete', ':id')),
            };

            /*
                `innerHTML` يُهرِّب < و > و & فقط — لا يُهرِّب علامة
                الاقتباس. والقيمة تُدرَج أدناه داخل `data-name="…"`،
                فاسمُ أمرٍ فيه " يخرج من السمة ويحقن onmouseover=.
            */
            const esc = value => $('<div>').text(value == null ? '' : value).html()
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
            const money = value => Number(value || 0).toLocaleString('en-US', {
                minimumFractionDigits: 2, maximumFractionDigits: 2,
            });
            const date = value => (value ? String(value).slice(0, 10) : '—');
            const symbol = () => (window.AppCurrency ? window.AppCurrency.symbol : '');

            const states = {
                pending: ['جديد', 'secondary'],
                materials_received: ['تم استلام المواد', 'info'],
                in_progress: ['قيد التنفيذ', 'warning'],
                completed: ['مكتمل', 'success'],
                closed: ['مغلق', 'secondary'],
                cancelled: ['ملغي', 'danger'],
                active: ['نشط', 'primary'],
            };

            let currentPage = 1;

            function rowHtml(order) {
                const [label, tone] = states[order.status] || [esc(order.status || 'غير محدد'), 'secondary'];
                const show = routes.show.replace(':id', order.id);

                return ''
                    + '<tr>'
                    + '<td><div class="stk-media__body">'
                    + '  <a class="stk-media__title" href="' + show + '">' + esc(order.name) + '</a>'
                    + '  <span class="stk-media__sub stk-num">#' + esc(order.code) + '</span>'
                    + '</div></td>'
                    + '<td>' + esc(order.product ? order.product.name : '—') + '</td>'
                    + '<td>' + esc(order.branch_name || '—') + '</td>'
                    + '<td class="text-center stk-num">' + esc(order.quantity) + '</td>'
                    + '<td class="text-center"><span class="stk-num d-block">' + date(order.from_date) + '</span>'
                    + '  <span class="stk-media__sub stk-num">' + date(order.to_date) + '</span></td>'
                    + '<td class="text-center stk-num stk-balance">' + money(order.last_total_cost) + ' ' + esc(symbol()) + '</td>'
                    + '<td>' + (order.client
                        ? '<span class="stk-media__title">' + esc(order.client.trade_name) + '</span>'
                          + '<span class="stk-media__sub stk-num">#' + esc(order.client.code) + '</span>'
                        : '—') + '</td>'
                    + '<td class="text-center"><span class="badge badge-light-' + tone + '">' + label + '</span></td>'
                    + '<td class="text-center"><div class="dropdown">'
                    + '  <button type="button" class="btn btn-sm btn-outline-secondary"'
                    + '     data-bs-toggle="dropdown" aria-expanded="false" aria-label="خيارات">'
                    + '    <i class="feather icon-more-vertical"></i></button>'
                    + '  <div class="dropdown-menu dropdown-menu-end">'
                    + '    <a class="dropdown-item" href="' + show + '"><i class="feather icon-eye"></i> عرض</a>'
                    // «تعديل» للأمر الجديد وحده، و«حذف» للجديد والملغى — ما يقبله الخادم فعلاً
                    + (order.status === 'pending'
                        ? '    <a class="dropdown-item" href="' + routes.edit.replace(':id', order.id) + '">'
                          + '      <i class="feather icon-edit-2"></i> تعديل</a>'
                        : '')
                    + (order.status === 'pending' || order.status === 'cancelled'
                        ? '    <div class="dropdown-divider"></div>'
                          + '    <a class="dropdown-item text-danger delete-order" href="#" data-id="' + order.id + '"'
                          + '       data-name="' + esc(order.name) + '"><i class="feather icon-trash-2"></i> حذف</a>'
                        : '')
                    + '  </div>'
                    + '</div></td>'
                    + '</tr>';
            }

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
                const orders = (response.data && response.data.orders) || [];
                const pagination = (response.data && response.data.pagination) || response.pagination || {};

                $('#total-count').text(pagination.total || orders.length);

                if (! orders.length) {
                    return '<div class="card-body"><div class="stk-empty">'
                        + '<div class="stk-empty__icon"><i class="feather icon-tool"></i></div>'
                        + '<h5 class="stk-empty__title">لا أوامر تصنيع</h5>'
                        + '<p class="stk-empty__hint">لا شيء يطابق معايير البحث الحالية</p>'
                        + '</div></div>';
                }

                return '<div class="table-responsive"><table class="table table-hover mb-0"><thead><tr>'
                    + '<th style="width:18%">الأمر</th>'
                    + '<th style="width:15%">المنتج</th>'
                    + '<th style="width:11%">تابع لـ</th>'
                    + '<th class="text-center" style="width:8%">الكمية</th>'
                    + '<th class="text-center" style="width:12%">الفترة</th>'
                    + '<th class="text-center" style="width:14%">التكلفة</th>'
                    + '<th style="width:15%">العميل</th>'
                    + '<th class="text-center" style="width:11%">الحالة</th>'
                    + '<th class="text-center" style="width:7%">إجراءات</th>'
                    + '</tr></thead><tbody>'
                    + orders.map(rowHtml).join('')
                    + '</tbody></table></div>'
                    + paginationHtml(pagination);
            }

            function loadOrders(page) {
                currentPage = page || currentPage;
                $('#loading-indicator').removeClass('d-none');

                $.ajax({
                    url: routes.data,
                    method: 'GET',
                    data: $('#filter-form').serialize()
                        + '&per_page=' + ($('#perPageSelect').val() || 10)
                        + '&page=' + currentPage,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    cache: false,
                })
                    .done(response => $('#results-container').html(render(response)))
                    .fail(function () {
                        $('#results-container').html(
                            '<div class="alert alert-danger m-2">تعذّر تحميل البيانات</div>'
                        );
                    })
                    .always(() => $('#loading-indicator').addClass('d-none'));
            }

            if ($.fn.select2) {
                $('#filter-form .select2').select2({ placeholder: 'الكل', allowClear: true, dir: 'rtl', width: '100%' });
            }

            $('#filter-form').on('submit', function (e) {
                e.preventDefault();
                loadOrders(1);
            });

            $('#filter-form').on('change input', 'input, select', function () {
                clearTimeout(window.ordersSearchTimer);
                window.ordersSearchTimer = setTimeout(() => loadOrders(1), 500);
            });

            $('#reset-btn').on('click', function () {
                $('#filter-form')[0].reset();
                if (window.mfgResetAffiliation) window.mfgResetAffiliation();
                if ($.fn.select2) $('#filter-form .select2').val('').trigger('change');
                loadOrders(1);
            });

            $('#perPageSelect').on('change', () => loadOrders(1));

            $('#results-container').on('click', '.pagination a', function (e) {
                e.preventDefault();
                loadOrders(parseInt($(this).data('page'), 10));
            });

            /* الحذف عبر المسار المسجَّل فعلاً: كان السكربت ينادي
               `DELETE /manufacturing/orders/{id}` — عنواناً لا وجود له،
               فلا يُحذف شيء ويعود 404 صامتاً. */
            // التفويض إلى `document` لا إلى حاوية: القائمة المنسدلة تُنقل
            // إلى <body> لترتسم فوق كل شيء، فيخرج زرّها من نسل الحاوية
            $(document).on('click', '.delete-order', function (e) {
                e.preventDefault();

                $('#del_name').text($(this).data('name'));
                $('#del_form').attr('action', routes.destroy.replace(':id', $(this).data('id')));
                $('#deleteOrderModal').modal('show');
            });

            $('#export-btn').on('click', function () {
                window.location.href = routes.data + '?export=1&' + $('#filter-form').serialize();
            });

            loadOrders(1);
        });
    </script>
@endsection
