@extends('stock::master')

@section('title', 'قوائم الأسعار')

@section('content')
    @php $hasFilters = request()->hasAny(['keywords', 'product', 'category']); @endphp

    <div class="content-body">

        @include('stock::components.page-head', [
            'title' => 'قوائم الأسعار',
            'icon' => 'tag',
            'sub' => 'تسعير المنتجات لفئات عملاء أو قنوات بيع مختلفة',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'قوائم الأسعار' => null,
            ],
            'actions' => Gate::allows('stock.price_lists.create')
                ? '<a href="' . route('price_list.create') . '" class="btn btn-primary">'
                    . '<i class="feather icon-plus"></i> قائمة أسعار جديدة</a>'
                : '',
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        {{-- ══════════ البحث والتصفية ══════════ --}}
        <div class="card stk-filters">
            <div class="card-body">
                <div class="stk-filters__head">
                    <h6 class="stk-filters__title"><i class="feather icon-filter"></i> بحث وتصفية</h6>
                    @if ($hasFilters)
                        <span class="stk-count">فلاتر مفعّلة</span>
                    @endif
                </div>

                <form id="searchForm" data-search-url="{{ route('price_list.index') }}">
                    @csrf
                    <div class="row">
                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="keywords">البحث</label>
                                <input type="text" id="keywords" name="keywords" class="form-control"
                                       value="{{ request('keywords') }}" placeholder="اسم القائمة…">
                            </div>
                        </div>

                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="product">المنتج</label>
                                <select id="product" name="product" class="form-control select2">
                                    <option value="">الكل</option>
                                    @foreach ($products as $product)
                                        <option value="{{ $product->id }}"
                                            {{ request('product') == $product->id ? 'selected' : '' }}>
                                            {{ $product->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="category">التصنيف</label>
                                <select id="category" name="category" class="form-control select2">
                                    <option value="">الكل</option>
                                    @foreach ($category as $cat)
                                        <option value="{{ $cat->id }}"
                                            {{ request('category') == $cat->id ? 'selected' : '' }}>
                                            {{ $cat->name }}
                                        </option>
                                    @endforeach
                                </select>
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
                    <i class="feather icon-list"></i> القوائم
                    @if (method_exists($price_lists, 'total'))
                        <span class="stk-count">{{ $price_lists->total() }}</span>
                    @endif
                </h6>

                <div class="d-flex align-items-center" style="gap:.6rem">
                    <div class="spinner-border spinner-border-sm text-primary d-none" id="loading-indicator" role="status"></div>
                    @include('stock::components.per-page')
                </div>
            </div>

            <div id="results-container">
                @include('stock::price_list.partials.table', ['price_lists' => $price_lists])
            </div>
        </div>
    </div>

    {{-- نافذة حذف واحدة — كانت واحدةً داخل كل صفّ --}}
    <div class="modal fade" id="deletePriceListModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content text-right">
                <div class="modal-header">
                    <h5 class="modal-title">حذف قائمة الأسعار</h5>
                    <button type="button" class="close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="إغلاق">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="mb-1">ستُحذف <strong id="del_name"></strong> نهائياً.</p>
                    <p class="stk-empty__hint mb-0">لا يمكن التراجع عن هذا الإجراء.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal" data-bs-dismiss="modal">
                        إلغاء
                    </button>
                    <a href="#" id="del_link" class="btn btn-danger">
                        <i class="feather icon-trash-2"></i> حذف نهائي
                    </a>
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
            if ($.fn.select2) {
                $('#searchForm .select2').select2({ placeholder: 'الكل', allowClear: true, dir: 'rtl', width: '100%' });
            }

            window.loadData = function (page, perPage) {
                $('#loading-indicator').removeClass('d-none');

                const data = {};
                new FormData($('#searchForm')[0]).forEach((value, key) => { data[key] = value; });

                data.page = page || 1;
                data.per_page = perPage || $('#perPage').val() || 10;

                $.get('{{ route('price_list.search') }}', data)
                    .done(response => $('#results-container').html(response))
                    .fail(() => Swal.fire({ icon: 'error', title: 'تعذّر تحميل البيانات' }))
                    .always(() => $('#loading-indicator').addClass('d-none'));
            };

            $('#searchForm').on('submit', function (e) {
                e.preventDefault();
                loadData(1);
            });

            $('#searchForm').on('change input', 'input, select', function () {
                clearTimeout(window.priceListTimer);
                window.priceListTimer = setTimeout(() => loadData(1), 500);
            });

            $('#resetSearch').on('click', function () {
                $('#searchForm')[0].reset();
                if ($.fn.select2) $('#searchForm .select2').val('').trigger('change');
                loadData(1);
            });

            $('#perPage').on('change', function () {
                loadData(1, this.value);
            });

            /* الترقيم يأتي الآن من روابط Laravel القياسية، فنلتقط `page` من الرابط */
            $('#results-container').on('click', '.pagination a', function (e) {
                e.preventDefault();

                const page = new URL(this.href, window.location.origin).searchParams.get('page');
                if (page) loadData(page);
            });

            // التفويض إلى `document` لا إلى حاوية: القائمة المنسدلة تُنقل
            // إلى <body> لترتسم فوق كل شيء، فيخرج زرّها من نسل الحاوية
            $(document).on('click', '.delete-price-list', function (e) {
                e.preventDefault();

                $('#del_name').text($(this).data('name'));
                $('#del_link').attr(
                    'href',
                    '{{ route('price_list.delete', ['id' => '_ID_']) }}'.replace('_ID_', $(this).data('id'))
                );
                $('#deletePriceListModal').modal('show');
            });
        });
    </script>
@endsection
