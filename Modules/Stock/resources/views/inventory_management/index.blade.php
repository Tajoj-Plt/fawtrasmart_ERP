@extends('stock::master')

@section('title', 'إدارة الجرد')

@section('content')
    @php
        $hasFilters = request()->hasAny(['storehouse_id', 'keywords', 'status', 'period_id']);
        $isPaginated = ($adjustments ?? null) instanceof \Illuminate\Pagination\LengthAwarePaginator;
    @endphp

    <div class="content-body">

        @include('stock::components.page-head', [
            'title' => 'إدارة الجرد',
            'icon' => 'clipboard',
            'sub' => 'أوراق الجرد وحالاتها وتنفيذ التسويات',
            'crumbs' => [
                'الرئيسية' => route('dashboard'),
                'إدارة الجرد' => null,
            ],
            'actions' => hasPermission('stock.adjustments.create')
                ? '<a href="' . route('inventory_management.create') . '" class="btn btn-primary">'
                    . '<i class="feather icon-plus"></i> ورقة جرد جديدة</a>'
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

                <form id="search-form" method="GET" action="{{ route('inventory_management.index') }}">
                    {{-- عدد السجلّات يُرسَل مع البحث لا بدالة جافاسكربت غير موجودة --}}
                    <input type="hidden" name="per_page" id="per_page_value" value="{{ request('per_page', 10) }}">

                    <div class="row">
                            {{--
                                الفترة المحاسبية علاقةً: ورقةُ الجرد مختومةٌ بها لحظةَ
                                إنشائها، والنطاقُ الافتراضي السنةُ المالية الجارية.
                            --}}
                            @include('partials.accounting-period-filter', [
                                'colClass' => 'col-md-3',
                                'period' => $period ?? [],
                            ])

                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="storehouse_id">المستودع</label>
                                <select id="storehouse_id" name="storehouse_id" class="form-control select2">
                                    <option value="">الكل</option>
                                    @foreach ($storehouses as $storehouse)
                                        <option value="{{ $storehouse->id }}"
                                            {{ request('storehouse_id') == $storehouse->id ? 'selected' : '' }}>
                                            {{ $storehouse->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="keywords">بحث</label>
                                <input type="text" id="keywords" name="keywords" class="form-control"
                                       value="{{ request('keywords') }}" placeholder="الرقم أو التفاصيل…">
                            </div>
                        </div>

                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="status">الحالة</label>
                                <select id="status" name="status" class="form-control">
                                    <option value="">الكل</option>
                                    @foreach (['draft' => 'مسودة', 'in_progress' => 'قيد التنفيذ', 'completed' => 'مكتمل', 'adjusted' => 'تمت التسوية', 'cancelled' => 'ملغي'] as $value => $label)
                                        <option value="{{ $value }}" {{ request('status') === $value ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="stk-filters__foot">
                        <a href="{{ route('inventory_management.index') }}" class="btn btn-outline-secondary">
                            <i class="feather icon-rotate-ccw"></i> إعادة تعيين
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="feather icon-search"></i> بحث
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- ══════════ النتائج ══════════ --}}
        <div class="card stk-table-card">
            @if (isset($adjustments) && count($adjustments) > 0)
                <div class="stk-table-head">
                    <h6 class="stk-table-head__title">
                        <i class="feather icon-list"></i> أوراق الجرد
                        <span class="stk-count">{{ $isPaginated ? $adjustments->total() : count($adjustments) }}</span>
                    </h6>

                    @include('stock::components.per-page')
                </div>

                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>المستودع</th>
                                <th class="text-center" style="width:18%">الحالة</th>
                                <th class="text-center" style="width:14%">النوع</th>
                                <th class="text-center" style="width:12%">عدد الأصناف</th>
                                <th class="text-center" style="width:10%">إجراءات</th>
                            </tr>
                        </thead>
                        <tbody id="inventory-table-body">
                            @include('stock::inventory_management.partials.table', ['adjustments' => $adjustments])
                        </tbody>
                    </table>
                </div>

                @if ($isPaginated && $adjustments->hasPages())
                    <div class="stk-table-foot">
                        <p class="stk-table-foot__count">
                            عرض <span class="stk-num">{{ $adjustments->firstItem() }}</span>–<span class="stk-num">{{ $adjustments->lastItem() }}</span>
                            من <span class="stk-num">{{ $adjustments->total() }}</span>
                        </p>
                        <nav aria-label="صفحات النتائج">
                            {{ $adjustments->appends(request()->except('page'))->links() }}
                        </nav>
                    </div>
                @endif
            @else
                <div class="card-body">
                    <div class="stk-empty">
                        <div class="stk-empty__icon"><i class="feather icon-clipboard"></i></div>
                        <h5 class="stk-empty__title">لا أوراق جرد</h5>
                        <p class="stk-empty__hint">
                            {{ $hasFilters ? 'لا شيء يطابق الفلاتر الحالية' : 'ابدأ بإنشاء ورقة جرد لمستودع' }}
                        </p>
                        @if (hasPermission('stock.adjustments.create'))
                        <a href="{{ route('inventory_management.create') }}" class="btn btn-primary">
                            <i class="feather icon-plus"></i> ورقة جرد جديدة
                        </a>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection

@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script>
        $(function () {
            if ($.fn.select2) {
                $('#search-form .select2').select2({ placeholder: 'الكل', allowClear: true, dir: 'rtl', width: '100%' });
            }

            /* كان مُنتقي عدد السجلّات ينادي `window.loadData` وهي غير معرَّفة
               في هذه الصفحة، فلا يفعل شيئاً. الآن يُعيد إرسال نموذج البحث. */
            $('#perPage').on('change', function () {
                $('#per_page_value').val(this.value);
                $('#search-form').submit();
            });

            $(document).on('click', '.delete-inventory', function (e) {
                e.preventDefault();

                const id = $(this).data('id');
                const name = $(this).data('name');

                Swal.fire({
                    title: 'حذف ورقة الجرد؟',
                    text: '#' + name,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'نعم، احذف',
                    cancelButtonText: 'إلغاء',
                    reverseButtons: true,
                }).then(result => {
                    if (! result.isConfirmed) return;

                    $.get('{{ route('inventory.delete', ['id' => '_ID_']) }}'.replace('_ID_', id))
                        .done(function (response) {
                            if (response && response.success === false) {
                                Swal.fire({ icon: 'error', title: 'تعذّر الحذف', text: response.message });
                                return;
                            }
                            location.reload();
                        })
                        .fail(function (xhr) {
                            Swal.fire({
                                icon: 'error',
                                title: 'تعذّر الحذف',
                                text: (xhr.responseJSON && xhr.responseJSON.message) || 'حدث خطأ أثناء الحذف',
                            });
                        });
                });
            });
        });
    </script>
@endsection
