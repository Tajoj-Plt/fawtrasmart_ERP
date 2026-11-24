{{--
    مسارات الإنتاج.

    أُعيد بناء الشاشة على قياس «فواتير المبيعات»: بطاقةُ فلترةٍ بصفٍّ
    واحدٍ ظاهر (بحث، مرحلة، نطاق تاريخ) ثمّ «تابع لـ»، وبطاقةُ نتائجَ
    تُرسَل مع الصفحة وتُستبدل وحدها بـAjax عند البحث والتنقّل.

    ما أُصلح:
      • الجدولُ كان يُفتح فارغاً ولا تظهر المسارات إلّا بعد أوّل بحث.
      • فلترُ الجهة كان في النموذج ولا يُرسَل مع الطلب إطلاقاً.
--}}

@extends('manufacturing::master')

@section('title', 'مسارات الإنتاج')

@section('content')
    @php
        $hasFilters = request()->hasAny(['search', 'production_stage_id', 'branch_id', 'from_date', 'to_date']);
    @endphp

    <div class="content-body">

        @include('stock::components.page-head', [
            'title' => 'مسارات الإنتاج',
            'icon' => 'git-merge',
            'sub' => 'مراحل التصنيع وترتيبها لكل مسار',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'إعدادات التصنيع' => route('Manufacturing.settings.index'),
                'مسارات الإنتاج' => null,
            ],
            'actions' => hasPermission('stock.production_paths.create')
                ? '<a href="' . route('manufacturing.paths.create') . '" class="btn btn-primary">'
                    . '<i class="feather icon-plus"></i> مسار جديد</a>'
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
                                <label for="searchInput">اسم المسار أو كوده</label>
                                <input type="text" id="searchInput" name="search" class="form-control"
                                       value="{{ request('search') }}" placeholder="الاسم أو الكود…">
                                <small class="stk-help">يبحث في اسم المسار ورقمه</small>
                            </div>
                        </div>

                        <div class="col-lg-4 col-md-6">
                            <div class="form-group">
                                <label for="productionStageSelect">المرحلة الإنتاجية</label>
                                <select id="productionStageSelect" name="production_stage_id" class="form-control select2">
                                    <option value="">كل المراحل</option>
                                    @foreach ($production_stages as $production_stage)
                                        <option value="{{ $production_stage->id }}"
                                            @selected(request('production_stage_id') == $production_stage->id)>
                                            {{ $production_stage->stage_name }}
                                        </option>
                                    @endforeach
                                </select>
                                <small class="stk-help">المسارات التي تمرّ بهذه المرحلة</small>
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
                        {{-- «تابع لـ» — انظر الجزء المشترك (لا عملة للمسار) --}}
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
                    <i class="feather icon-list"></i> المسارات
                    <span class="stk-count" id="rows-count">{{ $paths->total() }}</span>
                </h6>
                <div class="spinner-border spinner-border-sm text-primary d-none" id="loading-indicator" role="status"></div>
            </div>

            <div id="results-container">
                @include('manufacturing::settings.Paths.table', ['paths' => $paths])
            </div>
        </div>
    </div>

    {{-- نافذة حذف واحدة — كانت واحدةً بين كل صفّين داخل الجدول --}}
    <div class="modal fade" id="deletePathModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content text-right">
                <div class="modal-header">
                    <h5 class="modal-title">حذف مسار الإنتاج</h5>
                    <button type="button" class="close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="إغلاق">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="mb-1">سيُحذف المسار <strong id="del_name"></strong> نهائياً.</p>
                    <p class="stk-empty__hint mb-0">لا يمكن التراجع عن هذا الإجراء.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal" data-bs-dismiss="modal">
                        إلغاء
                    </button>
                    {{-- نموذج DELETE برمز CSRF بدل رابط GET — وجهته تُضبط من JS --}}
                    <form method="POST" action="#" id="del_form" class="d-inline">
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
    <script>
        $(function () {
            const indexUrl = @json(route('manufacturing.paths.index'));
            let request = null;
            let timer = null;

            if ($.fn.select2) {
                $('#searchForm select.select2').select2({ placeholder: 'الكل', allowClear: true, dir: 'rtl', width: '100%' });
            }

            /* الجدولُ يُستبدل وحده؛ والطلبُ الجديد يُلغي الذي لم يعد بعد */
            window.loadData = function (page) {
                if (request) request.abort();

                $('#loading-indicator').removeClass('d-none');

                request = $.ajax({
                    url: indexUrl,
                    method: 'GET',
                    data: $('#searchForm').serialize()
                        + '&per_page=' + ($('#perPage').val() || 10)
                        + '&page=' + (page || 1),
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
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

            // النصّ ينتظر توقّف الكتابة؛ والقوائم والتواريخ و«تابع لـ» تبحث فور التغيير
            $('#searchForm').on('input', 'input[type="text"]', function () {
                clearTimeout(timer);
                timer = setTimeout(function () { loadData(1); }, 500);
            });

            $('#searchForm').on('change', 'select, input[type="date"], input[type="hidden"]', function () {
                clearTimeout(timer);
                timer = setTimeout(function () { loadData(1); }, 150);
            });

            $('#resetSearch').on('click', function () {
                window.location.href = indexUrl;
            });

            $('#results-container').on('click', '.pagination a[data-page]', function (e) {
                e.preventDefault();
                if ($(this).closest('.page-item').is('.disabled, .active')) return;
                loadData(parseInt($(this).data('page'), 10));
            });

            // التفويض إلى `document` لا إلى حاوية: القائمة المنسدلة تُنقل
            // إلى <body> لترتسم فوق كل شيء، فيخرج زرّها من نسل الحاوية
            $(document).on('click', '.delete-path', function (e) {
                e.preventDefault();

                $('#del_name').text($(this).data('name'));
                $('#del_form').attr(
                    'action',
                    '{{ route('manufacturing.paths.delete', ['id' => '_ID_']) }}'.replace('_ID_', $(this).data('id'))
                );
                $('#deletePathModal').modal('show');
            });
        });
    </script>
@endsection
