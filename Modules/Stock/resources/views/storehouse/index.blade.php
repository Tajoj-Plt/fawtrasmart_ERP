@extends('stock::master')

@section('title', 'المستودعات')

@section('content')
    <div class="content-body">

        @section('page-actions')
            @if (hasPermission('stock.warehouses.create'))
                <a href="{{ route('storehouse.create') }}" class="btn btn-primary">
                    <i class="feather icon-plus"></i> مستودع جديد
                </a>
            @endif

            {{--
                كان الزرّ يُنشئ أرصدة افتتاحية «للكل» بضغطةٍ واحدة: بلا اختيار
                منتجٍ ولا كمية ولا سعر — يمسح كل مستودع ويُرحّل قيوداً بأرقامٍ
                لم يراجعها أحد. صار يقود إلى شاشة الرصيد الافتتاحي حيث تُحدَّد
                الأصناف وكمياتها وأسعارها قبل الترحيل.
            --}}
            @if (hasPermission('stock.permits.create_opening'))
                <a href="{{ route('store_permits_management.opening_balance') }}" class="btn btn-outline-primary">
                    <i class="feather icon-star"></i> رصيد افتتاحي
                </a>
            @endif
        @endsection

        @include('stock::components.page-head', [
            'title' => 'المستودعات',
            'icon' => 'home',
            'sub' => 'مستودعات المنشأة وحالاتها وتقاريرها',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'المستودعات' => null,
            ],
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        <form id="allOpeningBalancesForm" method="POST"
              action="{{ route('storehouse.generate_all_opening_balances') }}" class="d-none">
            @csrf
        </form>

        @php
            // الحالة: [التسمية، طبقة العرض] — نفس القائمة المستعملة في partials.storehouse_list
            $states = [
                'active' => ['نشط', 'on'],
                'inactive' => ['غير نشط', 'off'],
                'stopped' => ['متوقف', 'off'],
            ];
        @endphp

        {{-- ══════════ البحث والتصفية ══════════ --}}
        <div class="card stk-filters">
            <div class="card-body">
                <form id="filterForm" data-search-url="{{ route('storehouse.index') }}">
                    <div class="row">
                        <div class="col-md-5 col-sm-6">
                            <div class="form-group">
                                <label for="keywords">البحث</label>
                                <input type="text" id="keywords" name="keywords" class="form-control"
                                       value="{{ request('keywords') }}" placeholder="اسم المستودع أو عنوان الشحن…">
                            </div>
                        </div>

                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="status">الحالة</label>
                                <select id="status" name="status" class="form-control">
                                    <option value="">الكل</option>
                                    @foreach ($states as $value => $state)
                                        <option value="{{ $value }}" {{ request('status') === $value ? 'selected' : '' }}>
                                            {{ $state[0] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-3 col-sm-12 d-flex align-items-end">
                            <div class="stk-filters__foot mb-2">
                                <button type="button" id="resetSearch" class="btn btn-outline-secondary">
                                    <i class="feather icon-rotate-ccw"></i> إعادة تعيين
                                </button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="feather icon-search"></i> بحث
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card stk-table-card">
            <div class="stk-table-head">
                <h6 class="stk-table-head__title">
                    <i class="feather icon-list"></i> قائمة المستودعات
                    <span class="stk-count" id="storehouses-count">{{ $storehouses->total() }}</span>
                </h6>

                @include('stock::components.per-page')
            </div>

            <div id="storehousesContainer">
                @include('stock::storehouse.partials.storehouse_list', ['storehouses' => $storehouses, 'storehouseMeta' => $storehouseMeta])
            </div>
        </div>
    </div>

    {{-- نافذة حذف واحدة تُعاد تعبئتها — كانت واحدةً لكل مستودع في الصفحة --}}
    <div class="modal fade" id="deleteStorehouseModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content text-right">
                <div class="modal-header">
                    <h5 class="modal-title">حذف المستودع</h5>
                    <button type="button" class="close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="إغلاق">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="mb-1">سيُحذف المستودع <strong id="del_name"></strong> نهائياً.</p>
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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        const storehousesSearchUrl = @json(route('storehouse.index'));

        function showStorehousesLoading() {
            $('#storehousesContainer').css('opacity', '.55');
            if (! $('#storehouses-loading-indicator').length) {
                $('#storehousesContainer').prepend(
                    '<div id="storehouses-loading-indicator" class="text-center p-4">'
                    + '<div class="spinner-border text-primary" role="status"></div></div>'
                );
            }
        }

        function hideStorehousesLoading() {
            $('#storehouses-loading-indicator').remove();
            $('#storehousesContainer').css('opacity', '1');
        }

        function filterStorehouses(page = 1, perPage = null) {
            showStorehousesLoading();

            const data = $('#filterForm').serialize()
                + '&page=' + page
                + '&per_page=' + (perPage || $('#perPage').val() || 10);

            $.ajax({
                url: storehousesSearchUrl,
                method: 'GET',
                data: data,
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                cache: false,
                success: function (response) {
                    $('#storehousesContainer').html(response.html);
                    if (response.total !== undefined) {
                        $('#storehouses-count').text(response.total);
                    }
                },
                error: function () {
                    $('#storehousesContainer').html(
                        '<div class="alert alert-danger m-2">تعذّر تحميل البيانات — حاول مرة أخرى</div>'
                    );
                },
                complete: hideStorehousesLoading,
            });
        }

        $(function () {
            const stateLabels = { active: 'نشط', inactive: 'غير نشط', stopped: 'متوقف' };

            $('#filterForm').on('submit', function (e) {
                e.preventDefault();
                filterStorehouses();
            });

            /* بحث تلقائي بعد سكونٍ قصير — لكن ليس على كل ضغطة مفتاح */
            $('#filterForm').on('change input', 'input, select', function () {
                clearTimeout(window.storehouseSearchTimer);
                window.storehouseSearchTimer = setTimeout(() => filterStorehouses(), 500);
            });

            $('#resetSearch').on('click', function () {
                $('#filterForm')[0].reset();
                filterStorehouses();
            });

            $('#perPage').on('change', function () {
                filterStorehouses(1, $(this).val());
            });

            // الترقيم: الروابط تحمل href حقيقياً، ونحن نلتقطها لنبقى في الصفحة
            $(document).on('click', '#storehousesContainer .pagination a', function (e) {
                e.preventDefault();
                const page = $(this).data('page')
                    || new URL(this.href, window.location.origin).searchParams.get('page');
                if (page) filterStorehouses(page);
            });

            $(document).on('click', '.status-change', function (e) {
                e.preventDefault();

                const id = $(this).data('id');
                const status = $(this).data('status');

                Swal.fire({
                    title: 'تغيير الحالة إلى «' + stateLabels[status] + '»؟',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'نعم، غيّر',
                    cancelButtonText: 'إلغاء',
                    reverseButtons: true,
                }).then(result => {
                    if (! result.isConfirmed) return;

                    $.post('{{ route('storehouse.toggle_status', ['id' => '_ID_']) }}'.replace('_ID_', id), {
                        _token: '{{ csrf_token() }}',
                        status: status,
                    })
                        .done(function (response) {
                            if (response.success) {
                                location.reload();
                            } else {
                                Swal.fire({ icon: 'error', title: 'تعذّر التغيير', text: response.message });
                            }
                        })
                        .fail(function () {
                            Swal.fire({ icon: 'error', title: 'تعذّر التغيير', text: 'حدث خطأ أثناء تحديث الحالة' });
                        });
                });
            });

            $(document).on('click', '.delete-storehouse', function (e) {
                e.preventDefault();
                $('#del_name').text($(this).data('name'));
                $('#del_link').attr(
                    'href',
                    '{{ route('storehouse.delete', ['id' => '_ID_']) }}'.replace('_ID_', $(this).data('id'))
                );
                $('#deleteStorehouseModal').modal('show');
            });
        });
    </script>
@endsection
