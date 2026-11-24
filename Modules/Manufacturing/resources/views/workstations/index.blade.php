{{--
    محطات العمل.

    أُعيد بناء الشاشة على قياس «فواتير المبيعات»: بطاقةُ فلترةٍ بصفٍّ
    ظاهر (بحث، تكلفة الساعة، نطاق تاريخ) ثمّ «تابع لـ» والعملة، وبطاقةُ
    نتائجَ تُرسَل مع الصفحة وتُستبدل وحدها بـAjax عند البحث والتنقّل.
--}}

@extends('manufacturing::master')

@section('title', 'محطات العمل')

@section('content')
    @php
        $hasFilters = request()->hasAny(['search', 'branch_id', 'currency', 'cost_from', 'cost_to', 'from_date', 'to_date']);
    @endphp

    <div class="content-body">

        @include('stock::components.page-head', [
            'title' => 'محطات العمل',
            'icon' => 'cpu',
            'sub' => 'محطات التصنيع وتكاليف تشغيلها',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'التصنيع' => null,
                'محطات العمل' => null,
            ],
            'actions' => hasPermission('stock.workstations.create')
                ? '<a href="' . route('manufacturing.workstations.create') . '" class="btn btn-primary">'
                    . '<i class="feather icon-plus"></i> محطة عمل جديدة</a>'
                : '',
        ])

        @include('layouts.alerts.error')
        @include('stock::components.form-errors')
        @include('layouts.alerts.success')

        {{-- ══════════ البحث والتصفية ══════════ --}}
        <div class="card stk-filters">
            <div class="card-body">
                <div class="stk-filters__head">
                    <h6 class="stk-filters__title"><i class="feather icon-filter"></i> بحث وتصفية</h6>

                    @if ($hasFilters)
                        <span class="stk-count">فلاتر مفعَّلة</span>
                    @endif
                </div>

                <form id="searchForm">
                    <div class="row">
                        <div class="col-lg-4 col-md-6">
                            <div class="form-group">
                                <label for="searchInput">اسم المحطة أو كودها</label>
                                <input type="text" id="searchInput" name="search" class="form-control"
                                       value="{{ request('search') }}" placeholder="الاسم أو الكود أو الوصف…">
                                <small class="stk-help">يبحث في الاسم والرقم والوصف</small>
                            </div>
                        </div>

                        <div class="col-lg-4 col-md-6">
                            <div class="form-group">
                                <label for="cost_from">تكلفة الساعة</label>
                                <div class="input-group">
                                    <input type="number" step="any" min="0" id="cost_from" name="cost_from"
                                           class="form-control" value="{{ request('cost_from') }}" placeholder="من">
                                    <input type="number" step="any" min="0" name="cost_to"
                                           class="form-control" value="{{ request('cost_to') }}" placeholder="إلى">
                                </div>
                                <small class="stk-help">من — إلى</small>
                            </div>
                        </div>

                        <div class="col-lg-4 col-md-12">
                            <div class="form-group">
                                <label for="from_date">تاريخ الإضافة</label>
                                <div class="input-group">
                                    <input type="date" id="from_date" name="from_date" class="form-control"
                                           value="{{ request('from_date') }}" aria-label="من تاريخ">
                                    <input type="date" name="to_date" class="form-control"
                                           value="{{ request('to_date') }}" aria-label="إلى تاريخ">
                                </div>
                                <small class="stk-help">من — إلى</small>
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
                    <i class="feather icon-list"></i> المحطات
                    <span class="stk-count" id="rows-count">{{ $workstations->total() }}</span>
                </h6>
                <div class="spinner-border spinner-border-sm text-primary d-none" id="loading-indicator" role="status"></div>
            </div>

            <div id="results-container">
                @include('manufacturing::workstations.table', ['workstations' => $workstations])
            </div>
        </div>
    </div>

    {{-- ══════════ نافذة الحذف ══════════ --}}
    <div class="modal fade" id="deleteModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content text-right">
                <div class="modal-header">
                    <h5 class="modal-title">حذف محطة العمل</h5>
                    <button type="button" class="close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="إغلاق">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="mb-1">ستُحذف <strong id="itemToDelete"></strong> نهائياً.</p>
                    <p class="stk-empty__hint mb-0">لا يمكن التراجع عن هذا الإجراء.</p>
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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        $(function () {
            const routes = {
                index: @json(route('manufacturing.workstations.index')),
                data: @json(route('manufacturing.workstations.getData')),
                destroy: @json(route('manufacturing.workstations.delete', ':id')),
            };

            const token = $('meta[name="csrf-token"]').attr('content');

            let currentPage = {{ $workstations->currentPage() }};
            let deleteId = null;
            let request = null;
            let timer = null;

            /* الجدولُ يُستبدل وحده؛ والطلبُ الجديد يُلغي الذي لم يعد بعد */
            window.loadData = function (page) {
                currentPage = page || currentPage;

                if (request) request.abort();

                $('#loading-indicator').removeClass('d-none');

                request = $.ajax({
                    url: routes.data,
                    method: 'GET',
                    data: $('#searchForm').serialize()
                        + '&per_page=' + ($('#perPage').val() || 10)
                        + '&page=' + currentPage,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    cache: false,
                })
                    .done(function (response) {
                        $('#results-container').html(response.html);
                        $('#rows-count').text(response.total);
                    })
                    .fail(function (xhr, status) {
                        if (status === 'abort') return;

                        $('#results-container').html(
                            '<div class="alert alert-danger m-2">تعذّر تحميل البيانات — حاول مرة أخرى</div>'
                        );
                    })
                    .always(function () { $('#loading-indicator').addClass('d-none'); });
            };

            $('#searchForm').on('submit', function (e) {
                e.preventDefault();
                loadData(1);
            });

            // النصّ والأرقام تنتظر توقّف الكتابة؛ والقوائم والتواريخ و«تابع لـ» تبحث فور التغيير
            $('#searchForm').on('input', 'input[type="text"], input[type="number"]', function () {
                clearTimeout(timer);
                timer = setTimeout(function () { loadData(1); }, 500);
            });

            $('#searchForm').on('change', 'select, input[type="date"], input[type="hidden"]', function () {
                clearTimeout(timer);
                timer = setTimeout(function () { loadData(1); }, 150);
            });

            $('#resetSearch').on('click', function () {
                window.location.href = routes.index;
            });

            $('#results-container').on('click', '.pagination a[data-page]', function (e) {
                e.preventDefault();
                if ($(this).closest('.page-item').is('.disabled, .active')) return;
                loadData(parseInt($(this).data('page'), 10));
            });

            // التفويض إلى `document` لا إلى حاوية: القائمة المنسدلة تُنقل
            // إلى <body> لترتسم فوق كل شيء، فيخرج زرّها من نسل الحاوية
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

                        loadData(currentPage);
                    })
                    .fail(function (xhr) {
                        // الرفضُ (محطّةٌ مستخدمة في أمرٍ أو قائمة مواد) يعود 422 برسالته
                        $('#deleteModal').modal('hide');
                        Swal.fire({
                            icon: 'error',
                            title: 'تعذّر الحذف',
                            text: (xhr.responseJSON && xhr.responseJSON.message) || 'حدث خطأ أثناء الحذف',
                        });
                    })
                    .always(() => $btn.prop('disabled', false));
            });
        });
    </script>
@endsection
