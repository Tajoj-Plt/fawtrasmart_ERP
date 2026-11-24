{{--
    حركة مخزون منتج — تُحمَّل وتُستبدل بـAjax داخل `#stock-movements-content`.

    كان الجدول خمسة فروعٍ متطابقةً تقريباً (تحويل، مرتجع، إضافة/صرف، بيع، غير
    محدّد) يعيد كلٌّ منها كتابة الأعمدة الخمسة كاملةً. صار الفرع يحسب أربع قيم
    — العنوان، ولون الشارة، واتجاه الحركة، والموقع — ثم يُرسَم صفٌّ واحد.

    وصُحّح هنا خطأ: سعر الوحدة كان يُقرأ من `$product->sale_price` في كل صفّ،
    أي سعر البيع الحالي، بينما الصفّ يحمل `unit_price` الخاصّ بالحركة نفسها.
--}}

@php
    $movements = ($stock_movements ?? null);
    $rows = ($movements && is_object($movements) && method_exists($movements, 'count')) ? $movements : collect();

    $hasFilters = request()->hasAny(['source_id', 'warehouse_id', 'user_id', 'date_from', 'date_to']);
@endphp

{{-- ══════════ الفلاتر ══════════ --}}
<div class="card stk-filters">
    <div class="card-body">
        <div class="stk-filters__head">
            <h6 class="stk-filters__title"><i class="feather icon-filter"></i> تصفية الحركة</h6>
            @if ($hasFilters)
                <span class="stk-count">فلاتر مفعّلة</span>
            @endif
        </div>

        <form id="stockMovementsFilterForm" onsubmit="event.preventDefault(); loadStockMovementsWithFilters();">
            <div class="row">
                <div class="col-md-3 col-sm-6">
                    <div class="form-group">
                        <label for="source_filter">المصدر</label>
                        <select class="form-control select2" id="source_filter" name="source_id">
                            <option value="">الكل</option>
                            {{-- المصدر 7 مستثنىً: لا يُنتج حركة يراها المستخدم --}}
                            @foreach (($permission_sources ?? \App\Models\PermissionSource::all())->where('id', '!=', 7) as $source)
                                <option value="{{ $source->id }}"
                                    {{ ($source_id ?? null) == $source->id ? 'selected' : '' }}>
                                    {{ $source->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="col-md-3 col-sm-6">
                    <div class="form-group">
                        <label for="warehouse_filter">المستودع</label>
                        <select class="form-control select2" id="warehouse_filter" name="warehouse_id">
                            <option value="">الكل</option>
                            {{-- القائمة يمرّرها الكنترولر بنطاق المستخدم — السقوط إلى
                                 قائمةٍ فارغة لا إلى StoreHouse::all()، وإلا رأى المقيَّدُ
                                 أسماء مستودعات المنشأة كلِّها من مسارِ عرضٍ نُسي تمريرها فيه --}}
                            @foreach ($warehouses ?? collect() as $warehouse)
                                <option value="{{ $warehouse->id }}"
                                    {{ ($warehouse_id ?? null) == $warehouse->id ? 'selected' : '' }}>
                                    {{ $warehouse->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="col-md-3 col-sm-6">
                    <div class="form-group">
                        <label for="user_filter">الموظف</label>
                        <select class="form-control select2" id="user_filter" name="user_id">
                            <option value="">الكل</option>
                            @php
                                // مَن حرّك هذا المنتج فعلاً — لا كل موظّفي المنشأة
                                $movementUsers = $movement_users ?? \App\Models\User::whereIn(
                                    'id',
                                    \App\Models\WarehousePermitsProducts::where('product_id', $product->id)
                                        ->join('warehouse_permits', 'warehouse_permits_products.warehouse_permits_id', '=', 'warehouse_permits.id')
                                        ->pluck('warehouse_permits.created_by')
                                        ->unique()
                                )->get();
                            @endphp
                            @foreach ($movementUsers as $user)
                                <option value="{{ $user->id }}"
                                    {{ ($user_id ?? null) == $user->id ? 'selected' : '' }}>
                                    {{ $user->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="col-md-3 col-sm-6">
                    <div class="row">
                        <div class="col-6">
                            <div class="form-group">
                                <label for="date_from_filter">من</label>
                                <input type="date" class="form-control" id="date_from_filter"
                                       name="date_from" value="{{ request('date_from') }}">
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group">
                                <label for="date_to_filter">إلى</label>
                                <input type="date" class="form-control" id="date_to_filter"
                                       name="date_to" value="{{ request('date_to') }}">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="stk-filters__foot">
                <button type="button" class="btn btn-outline-secondary" onclick="clearStockMovementsFilters()">
                    <i class="feather icon-rotate-ccw"></i> إعادة تعيين
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="feather icon-search"></i> بحث
                </button>
            </div>
        </form>
    </div>
</div>

{{-- ══════════ الجدول ══════════ --}}
<div class="card stk-table-card">
    @if ($rows->count() > 0)
        <div class="stk-table-head">
            <h6 class="stk-table-head__title">
                <i class="feather icon-repeat"></i> الحركات
                @if (method_exists($rows, 'total'))
                    <span class="stk-count">{{ $rows->total() }}</span>
                @endif
            </h6>
        </div>

        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th style="width:30%">العملية</th>
                        <th style="width:14%">المصدر</th>
                        <th style="width:18%">المسؤول</th>
                        <th class="text-center" style="width:19%">الحركة</th>
                        <th class="text-center" style="width:19%">الرصيد بعدها</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        @php
                            $type = $row->permission_type;
                            $sourceId = $row->permission_source_id ?? null;

                            $quantity = $row->quantity ?? 0;
                            $before = $row->stock_before ?? 0;
                            $after = $row->stock_after ?? 0;
                            $unitPrice = $row->unit_price ?? 0;

                            /* الاتجاه: التحويل يُعرف من فرق الرصيد لا من نوع الإذن،
                               لأن الإذن نفسه يظهر مرّتين — صادراً وواردا. */
                            $isTransfer = in_array($type, [3, 13]);
                            $isReturn = in_array($sourceId, [8, 9]);   // 8 مرتجع مبيعات، 9 إشعار دائن

                            if ($isTransfer) {
                                $out = $after < $before;
                                $title = 'تحويل مخزني';
                                $tone = 'info';
                                $place = $out
                                    ? 'من: ' . ($row->from_store_name ?? 'غير محدد')
                                    : 'إلى: ' . ($row->to_store_name ?? 'غير محدد');
                            } elseif ($isReturn) {
                                $out = false;
                                $title = $sourceId == 8 ? 'مرتجع مبيعات' : 'إشعار دائن مبيعات';
                                $tone = 'info';
                                $place = 'إلى: ' . ($row->store_name ?? 'غير محدد');
                            } elseif ($type == 1) {
                                $out = false;
                                $title = 'إضافة مخزن';
                                $tone = 'success';
                                $place = $row->store_name ?? 'غير محدد';
                            } elseif ($type == 2) {
                                $out = true;
                                $title = 'صرف مخزن';
                                $tone = 'danger';
                                $place = $row->store_name ?? 'غير محدد';
                            } elseif ($type == 10) {
                                $out = true;
                                $title = 'فاتورة بيع';
                                $tone = 'warning';
                                $place = $row->store_name ?? 'غير محدد';
                            } else {
                                $out = null;   // نوعٌ لا نعرفه: لا نزعم اتجاهاً
                                $title = 'عملية غير مصنَّفة';
                                $tone = 'secondary';
                                $place = $row->store_name ?? $row->from_store_name ?? $row->to_store_name ?? 'غير محدد';
                            }

                            $moveTone = $out === null ? 'muted' : ($out ? 'danger' : 'success');
                            $moveIcon = $out === null ? 'help-circle' : ($out ? 'arrow-down' : 'arrow-up');
                        @endphp

                        <tr>
                            <td>
                                <div class="stk-media__body">
                                    <span class="stk-media__title">
                                        <span class="badge badge-light-{{ $tone }}">{{ $title }}</span>
                                        <span class="stk-num">#{{ $row->permit_number ?? $row->permit_id }}</span>
                                    </span>
                                    <span class="stk-media__sub stk-num">
                                        <i class="feather icon-calendar"></i> {{ $row->permission_date ?? '—' }}
                                    </span>
                                    <span class="stk-media__sub">
                                        <i class="feather icon-home"></i> {{ $place }}
                                    </span>
                                </div>
                            </td>

                            <td>
                                <span class="badge badge-light-primary">{{ $row->source_name ?? 'غير محدد' }}</span>
                            </td>

                            <td>
                                <div class="stk-media__body">
                                    <span class="stk-media__title">{{ $row->user_name ?? 'غير محدد' }}</span>
                                    @if (! empty($row->user_email))
                                        <span class="stk-media__sub">{{ $row->user_email }}</span>
                                    @endif
                                </div>
                            </td>

                            <td class="text-center">
                                <span class="stk-move stk-move--{{ $moveTone }}">
                                    <i class="feather icon-{{ $moveIcon }}"></i>
                                    <span class="stk-num">{{ number_format($quantity) }}</span>
                                </span>
                                <span class="stk-media__sub d-block" title="سعر الوحدة وقت الحركة">
                                    @money($unitPrice)
                                </span>
                            </td>

                            <td class="text-center">
                                <span class="stk-num stk-balance">{{ number_format($after) }}</span>
                                <span class="stk-media__sub d-block stk-num">كان {{ number_format($before) }}</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if (method_exists($rows, 'hasPages'))
            @include('stock::components.js-pagination', [
                'paginator' => $rows,
                'callback' => 'loadStockMovementsPage',
                'unit' => 'حركة',
            ])
        @endif
    @else
        <div class="card-body">
            <div class="stk-empty">
                <div class="stk-empty__icon"><i class="feather icon-repeat"></i></div>
                <h5 class="stk-empty__title">لا توجد حركات</h5>
                <p class="stk-empty__hint">
                    {{ $hasFilters ? 'لا شيء يطابق الفلاتر الحالية — جرّب توسيعها' : 'لم تُسجَّل أي حركة على هذا المنتج بعد' }}
                </p>
            </div>
        </div>
    @endif
</div>

<script>
    /* الدوال معرَّفة على النطاق العام: هذا القالب يُحقن بـinnerHTML عند كل
       تصفية، وscript المحقون لا يُنفَّذ — فتبقى النسخة الأولى هي العاملة. */
    (function () {
        const productId = @json($product->id ?? null);
        const endpoint = @json(url('/stock/products')) + '/' + productId + '/stock-movements';

        function currentFilters() {
            const params = new URLSearchParams();
            const fields = {
                source_id: 'source_filter',
                warehouse_id: 'warehouse_filter',
                user_id: 'user_filter',
                date_from: 'date_from_filter',
                date_to: 'date_to_filter',
            };
            for (const [key, id] of Object.entries(fields)) {
                const value = document.getElementById(id)?.value || '';
                if (value) params.append(key, value);
            }
            return params;
        }

        function load(params) {
            const container = document.getElementById('stock-movements-content');
            if (!productId || !container) return;

            container.innerHTML =
                '<div class="text-center p-5"><div class="spinner-border text-primary" role="status">'
                + '<span class="visually-hidden">جارٍ التحميل…</span></div></div>';

            fetch(endpoint + '?' + params.toString(), {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        container.innerHTML = data.html;
                        if (window.jQuery && $.fn.select2) $('#stock-movements-content .select2').select2({ dir: 'rtl', width: '100%' });
                    } else {
                        container.innerHTML = '<div class="alert alert-danger">'
                            + '<i class="feather icon-alert-circle"></i> '
                            + (data.error || 'خطأ في تحميل البيانات') + '</div>';
                    }
                })
                .catch(err => {
                    console.error(err);
                    container.innerHTML = '<div class="alert alert-danger">'
                        + '<i class="feather icon-alert-circle"></i> تعذّر تحميل البيانات</div>';
                });
        }

        window.loadStockMovementsWithFilters = function () {
            load(currentFilters());
        };

        window.loadStockMovementsPage = function (page) {
            const params = currentFilters();
            params.append('page', page);
            load(params);
        };

        window.clearStockMovementsFilters = function () {
            ['source_filter', 'warehouse_filter', 'user_filter', 'date_from_filter', 'date_to_filter']
                .forEach(id => {
                    const el = document.getElementById(id);
                    if (el) el.value = '';
                });
            if (window.jQuery && $.fn.select2) $('#stockMovementsFilterForm .select2').trigger('change');
            load(new URLSearchParams());
        };
    })();
</script>
