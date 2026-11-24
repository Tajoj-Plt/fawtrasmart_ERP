<div class="card stk-filters mb-3">
    <div class="card-body">
        <div class="stk-filters__head">
            <h6 class="stk-filters__title"><i class="feather icon-filter"></i> فلاتر البحث</h6>
        </div>

        <form id="stockMovementsFilterForm" method="GET" action="">
            <div class="row">
                {{-- فلتر المصدر --}}
                <div class="col-md-3 mb-3">
                    <label for="source_filter" class="form-label">المصدر</label>
                    <select class="form-control select2 filter-trigger" id="source_filter" name="source_id">
                        <option value="">الكل</option>
                        @php
                            $permissionSources = \App\Models\PermissionSource::all();
                        @endphp
                        @foreach ($permissionSources as $source)
                            @if ($source->id != 7)
                                <option value="{{ $source->id }}" {{ isset($source_id) && $source_id == $source->id ? 'selected' : '' }}>
                                    {{ $source->name }}
                                </option>
                            @endif
                        @endforeach
                    </select>
                </div>

                {{-- فلتر المستودع --}}
                <div class="col-md-3 mb-3">
                    <label for="warehouse_filter" class="form-label">المستودع</label>
                    <select class="form-control select2 filter-trigger" id="warehouse_filter" name="warehouse_id">
                        <option value="">الكل</option>
                        @php
                            // القائمة من المتحكّم (مستودعات المتجر المرئيّة) — لا كلُّ مستودعات النظام
                            $warehouses = $warehouses ?? collect();
                        @endphp
                        @foreach ($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}" {{ isset($warehouse_id) && $warehouse_id == $warehouse->id ? 'selected' : '' }}>
                                {{ $warehouse->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- فلتر المستخدم --}}
                <div class="col-md-3 mb-3">
                    <label for="user_filter" class="form-label">الموظف</label>
                    <select class="form-control select2 filter-trigger" id="user_filter" name="user_id">
                        <option value="">الكل</option>
                        @php
                            $users = \App\Models\User::whereIn('id',
                                \App\Models\WarehousePermitsProducts::where('product_id', $product->id)
                                    ->join('warehouse_permits', 'warehouse_permits_products.warehouse_permits_id', '=', 'warehouse_permits.id')
                                    ->pluck('warehouse_permits.created_by')
                                    ->unique()
                            )->get();
                        @endphp
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" {{ isset($user_id) && $user_id == $user->id ? 'selected' : '' }}>
                                {{ $user->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- فلتر التاريخ من --}}
                <div class="col-md-2 mb-3">
                    <label for="date_from_filter" class="form-label">من تاريخ</label>
                    <input type="date" class="form-control filter-trigger" id="date_from_filter" name="date_from" value="{{ request('date_from') }}">
                </div>

                {{-- فلتر التاريخ إلى --}}
                <div class="col-md-2 mb-3">
                    <label for="date_to_filter" class="form-label">إلى تاريخ</label>
                    <input type="date" class="form-control filter-trigger" id="date_to_filter" name="date_to" value="{{ request('date_to') }}">
                </div>

                {{-- زر البحث --}}
                <div class="col-md-1 mb-3">
                    <label class="form-label">&nbsp;</label>
                    <button type="button" id="searchBtn" class="btn btn-primary btn-sm w-100" onclick="loadStockMovementsWithFilters()">
                        <i class="feather icon-search"></i>
                    </button>
                </div>

                {{-- زر المسح --}}
                <div class="col-md-1 mb-3">
                    <label class="form-label">&nbsp;</label>
                    <button type="button" id="clearBtn" class="btn btn-outline-secondary btn-sm w-100" onclick="clearStockMovementsFilters()">
                        <i class="feather icon-x"></i>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    function loadStockMovementsWithFilters() {
        const productId = '{{ $product->id ?? null }}';
        if (!productId) return;

        const sourceId = document.getElementById('source_filter')?.value || '';
        const warehouseId = document.getElementById('warehouse_filter')?.value || '';
        const userId = document.getElementById('user_filter')?.value || '';
        const dateFrom = document.getElementById('date_from_filter')?.value || '';
        const dateTo = document.getElementById('date_to_filter')?.value || '';

        const container = document.getElementById('stock-movements-content');

        if (container) {
            container.innerHTML = '<div class="text-center p-5"><div class="spinner-border" role="status"><span class="visually-hidden">جاري التحميل...</span></div></div>';
        }

        const params = new URLSearchParams();
        if (sourceId) params.append('source_id', sourceId);
        if (warehouseId) params.append('warehouse_id', warehouseId);
        if (userId) params.append('user_id', userId);
        if (dateFrom) params.append('date_from', dateFrom);
        if (dateTo) params.append('date_to', dateTo);

        fetch(`{{ url('/online_store/products') }}/${productId}/stock-movements?${params.toString()}`, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && container) {
                container.innerHTML = data.html;
                if (typeof $.fn.select2 !== 'undefined') {
                    $('.select2').select2();
                }
            } else if (!data.success) {
                container.innerHTML = `<div class="alert alert-danger"><i class="feather icon-alert-circle"></i> ${data.error || 'خطأ في تحميل البيانات'}</div>`;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            if (container) {
                container.innerHTML = '<div class="alert alert-danger"><i class="feather icon-alert-circle"></i> حدث خطأ أثناء تحميل البيانات</div>';
            }
        });
    }

    function loadStockMovementsPage(page) {
        const productId = '{{ $product->id ?? null }}';
        if (!productId) return;

        const sourceId = document.getElementById('source_filter')?.value || '';
        const warehouseId = document.getElementById('warehouse_filter')?.value || '';
        const userId = document.getElementById('user_filter')?.value || '';
        const dateFrom = document.getElementById('date_from_filter')?.value || '';
        const dateTo = document.getElementById('date_to_filter')?.value || '';

        const container = document.getElementById('stock-movements-content');

        const params = new URLSearchParams();
        params.append('page', page);
        if (sourceId) params.append('source_id', sourceId);
        if (warehouseId) params.append('warehouse_id', warehouseId);
        if (userId) params.append('user_id', userId);
        if (dateFrom) params.append('date_from', dateFrom);
        if (dateTo) params.append('date_to', dateTo);

        fetch(`{{ url('/online_store/products') }}/${productId}/stock-movements?${params.toString()}`, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && container) {
                container.innerHTML = data.html;
                if (typeof $.fn.select2 !== 'undefined') {
                    $('.select2').select2();
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
        });
    }

    function clearStockMovementsFilters() {
        document.getElementById('source_filter').value = '';
        document.getElementById('warehouse_filter').value = '';
        document.getElementById('user_filter').value = '';
        document.getElementById('date_from_filter').value = '';
        document.getElementById('date_to_filter').value = '';

        if (typeof $.fn.select2 !== 'undefined') {
            $('#source_filter').val('').trigger('change');
            $('#warehouse_filter').val('').trigger('change');
            $('#user_filter').val('').trigger('change');
        }

        loadStockMovementsWithFilters();
    }
</script>

@if (isset($stock_movements) && is_object($stock_movements) && method_exists($stock_movements, 'count') && $stock_movements->count() > 0)
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th style="width: 25%">العملية</th>
                    <th style="width: 12%">نوع المصدر</th>
                    <th style="width: 15%">المسؤول</th>
                    <th>حركة</th>
                    <th>الرصيد بعد</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($stock_movements as $stock_movement)
                    @php
                        $permit_id = $stock_movement->permit_id;
                        $permit_number = $stock_movement->permit_number;
                        $permission_date = $stock_movement->permission_date;
                        $permission_type = $stock_movement->permission_type;
                        $source_name = $stock_movement->source_name ?? 'غير محدد';
                        $user_name = $stock_movement->user_name ?? 'غير محدد';
                        $user_email = $stock_movement->user_email ?? '';
                        $store_name = $stock_movement->store_name ?? 'غير محدد';
                        $from_store_name = $stock_movement->from_store_name ?? 'غير محدد';
                        $to_store_name = $stock_movement->to_store_name ?? 'غير محدد';
                        $quantity = $stock_movement->quantity ?? 0;
                        $stock_before = $stock_movement->stock_before ?? 0;
                        $stock_after = $stock_movement->stock_after ?? 0;
                        $unit_price = $stock_movement->unit_price ?? 0;
                        $isCreditNoteOrReturn = in_array($stock_movement->permission_source_id ?? null, [8, 9]);
                    @endphp

                    {{-- عمليات التحويل بين المستودعات --}}
                    @if ($permission_type == 3 || $permission_type == 13)
                        @php
                            $isFrom = $stock_after < $stock_before;
                        @endphp
                        <tr>
                            <td>
                                <strong class="stk-num">{{ $permission_date ?? 'غير محدد' }}
                                    (#{{ $permit_id ?? 'غير محدد' }})</strong><br>
                                <span>تحويل مخزني ({{ $permit_number ?? 'غير محدد' }}#)</span><br>
                                @if ($isFrom)
                                    <span class="badge badge-light-danger">🔻 من: {{ $from_store_name }}</span>
                                @else
                                    <span class="badge badge-light-success">🔺 إلى: {{ $to_store_name }}</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge badge-light-info">{{ $source_name }}</span>
                            </td>
                            <td>
                                <strong>{{ $user_name }}</strong><br>
                                <small class="text-muted">{{ $user_email }}</small>
                            </td>
                            <td>
                                <strong class="stk-num {{ $isFrom ? 'text-danger' : 'text-success' }}">
                                    {{ number_format($quantity) }}
                                    <i class="feather {{ $isFrom ? 'icon-minus' : 'icon-plus' }}"></i>
                                </strong><br>
                                <small class="stk-num"><abbr title="سعر الوحدة">{{ number_format($product->sale_price ?? 0, 2) }}&nbsp;{{ \App\Support\CurrencySettings::symbol() }}</abbr></small>
                            </td>
                            <td>
                                <strong class="stk-num">{{ number_format($stock_after) }}</strong><br>
                                <small class="stk-num"><abbr title="سعر الوحدة">{{ number_format($product->sale_price ?? 0, 2) }}&nbsp;{{ \App\Support\CurrencySettings::symbol() }}</abbr></small>
                            </td>
                        </tr>

                    {{-- الاشعارات الدائنة ومرتجعات المبيعات --}}
                    @elseif($isCreditNoteOrReturn)
                        <tr>
                            <td>
                                <strong class="stk-num">{{ $permission_date ?? 'غير محدد' }}
                                    (#{{ $permit_id ?? 'غير محدد' }})</strong><br>
                                <span>
                                    @if($stock_movement->permission_source_id == 8)
                                        <span class="badge badge-light-info">مرتجع مبيعات</span>
                                    @elseif($stock_movement->permission_source_id == 9)
                                        <span class="badge badge-light-info">اشعار دائن مبيعات</span>
                                    @endif
                                    ({{ $permit_number ?? 'غير محدد' }}#)
                                </span><br>
                                <span class="badge badge-light-success">🔺 إلى: {{ $store_name }}</span>
                            </td>
                            <td>
                                <span class="badge badge-light-info">{{ $source_name }}</span>
                            </td>
                            <td>
                                <strong>{{ $user_name }}</strong><br>
                                <small class="text-muted">{{ $user_email }}</small>
                            </td>
                            <td>
                                <strong class="stk-num text-success">
                                    {{ number_format($quantity) }}
                                    <i class="feather icon-plus text-success"></i>
                                </strong><br>
                                <small class="stk-num"><abbr title="سعر الوحدة">{{ number_format($product->sale_price ?? 0, 2) }}&nbsp;{{ \App\Support\CurrencySettings::symbol() }}</abbr></small>
                            </td>
                            <td>
                                <strong class="stk-num">{{ number_format($stock_after) }}</strong><br>
                                <small class="stk-num"><abbr title="سعر الوحدة">{{ number_format($product->sale_price ?? 0, 2) }}&nbsp;{{ \App\Support\CurrencySettings::symbol() }}</abbr></small>
                            </td>
                        </tr>

                    {{-- العمليات العادية (إضافة أو صرف) --}}
                    @elseif($permission_type == 1 || $permission_type == 2)
                        <tr>
                            <td>
                                <strong class="stk-num">{{ $permission_date ?? 'غير محدد' }}
                                    (#{{ $permit_id ?? 'غير محدد' }})</strong><br>
                                <span>
                                    @if ($permission_type == 1)
                                        <span class="badge badge-light-success">إضافة مخزن</span>
                                    @elseif($permission_type == 2)
                                        <span class="badge badge-light-danger">صرف مخزن</span>
                                    @endif
                                    ({{ $permit_number ?? 'غير محدد' }}#)
                                </span><br>
                                <span>{{ $store_name }}</span>
                            </td>
                            <td>
                                <span class="badge badge-light-primary">{{ $source_name }}</span>
                            </td>
                            <td>
                                <strong>{{ $user_name }}</strong><br>
                                <small class="text-muted">{{ $user_email }}</small>
                            </td>
                            <td>
                                <strong class="stk-num">
                                    {{ number_format($quantity) }}
                                    @if ($permission_type == 1)
                                        <i class="feather icon-plus text-success"></i>
                                    @elseif($permission_type == 2)
                                        <i class="feather icon-minus text-danger"></i>
                                    @endif
                                </strong><br>
                                <small class="stk-num"><abbr title="سعر الوحدة">{{ number_format($product->sale_price ?? 0, 2) }}&nbsp;{{ \App\Support\CurrencySettings::symbol() }}</abbr></small>
                            </td>
                            <td>
                                <strong class="stk-num">{{ number_format($stock_after) }}</strong><br>
                                <small class="stk-num"><abbr title="سعر الوحدة">{{ number_format($product->sale_price ?? 0, 2) }}&nbsp;{{ \App\Support\CurrencySettings::symbol() }}</abbr></small>
                            </td>
                        </tr>

                    {{-- المبيعات --}}
                    @elseif($permission_type == 10)
                        <tr>
                            <td>
                                <strong class="stk-num">{{ $permission_date ?? 'غير محدد' }}
                                    (#{{ $permit_id ?? 'غير محدد' }})</strong><br>
                                <span><span class="badge badge-light-warning">فاتورة رقم</span>
                                    ({{ $permit_number ?? 'غير محدد' }}#)</span>
                            </td>
                            <td>
                                <span class="badge badge-light-warning">{{ $source_name }}</span>
                            </td>
                            <td>
                                <strong>{{ $user_name }}</strong><br>
                                <small class="text-muted">{{ $user_email }}</small>
                            </td>
                            <td>
                                <strong class="stk-num text-danger">
                                    {{ number_format($quantity) }}
                                    <i class="feather icon-minus text-danger"></i>
                                </strong><br>
                                <small class="stk-num"><abbr title="سعر الوحدة">{{ number_format($product->sale_price ?? 0, 2) }}&nbsp;{{ \App\Support\CurrencySettings::symbol() }}</abbr></small>
                            </td>
                            <td>
                                <strong class="stk-num">{{ number_format($stock_after) }}</strong><br>
                                <small class="stk-num"><abbr title="سعر الوحدة">{{ number_format($product->sale_price ?? 0, 2) }}&nbsp;{{ \App\Support\CurrencySettings::symbol() }}</abbr></small>
                            </td>
                        </tr>

                    {{-- عمليات أخرى --}}
                    @else
                        <tr>
                            <td>
                                <strong class="stk-num">{{ $permission_date ?? 'غير محدد' }}
                                    (#{{ $permit_id ?? 'غير محدد' }})</strong><br>
                                <span>{{ $store_name }}</span>
                            </td>
                            <td>
                                <span class="badge badge-light-secondary">{{ $source_name }}</span>
                            </td>
                            <td>
                                <strong>{{ $user_name }}</strong><br>
                                <small class="text-muted">{{ $user_email }}</small>
                            </td>
                            <td>
                                <strong class="stk-num">{{ number_format($quantity) }}</strong><br>
                                <small class="stk-num"><abbr title="سعر الوحدة">{{ number_format($product->sale_price ?? 0, 2) }}&nbsp;{{ \App\Support\CurrencySettings::symbol() }}</abbr></small>
                            </td>
                            <td>
                                <strong class="stk-num">{{ number_format($stock_after) }}</strong><br>
                                <small class="stk-num"><abbr title="سعر الوحدة">{{ number_format($product->sale_price ?? 0, 2) }}&nbsp;{{ \App\Support\CurrencySettings::symbol() }}</abbr></small>
                            </td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- الصفحات --}}
    @if ($stock_movements->hasPages())
        <nav aria-label="Stock movements pagination" class="mt-4">
            <ul class="pagination justify-content-center">
                @if ($stock_movements->onFirstPage())
                    <li class="page-item disabled"><span class="page-link">الأول</span></li>
                    <li class="page-item disabled"><span class="page-link">السابق</span></li>
                @else
                    <li class="page-item">
                        <a class="page-link" href="javascript:void(0)" onclick="loadStockMovementsPage(1)">الأول</a>
                    </li>
                    <li class="page-item">
                        <a class="page-link" href="javascript:void(0)" onclick="loadStockMovementsPage({{ $stock_movements->currentPage() - 1 }})">السابق</a>
                    </li>
                @endif

                <li class="page-item active"><span class="page-link">{{ $stock_movements->currentPage() }}</span></li>

                @if ($stock_movements->hasMorePages())
                    <li class="page-item">
                        <a class="page-link" href="javascript:void(0)" onclick="loadStockMovementsPage({{ $stock_movements->currentPage() + 1 }})">التالي</a>
                    </li>
                @else
                    <li class="page-item disabled"><span class="page-link">التالي</span></li>
                @endif

                @if ($stock_movements->currentPage() < $stock_movements->lastPage())
                    <li class="page-item">
                        <a class="page-link" href="javascript:void(0)" onclick="loadStockMovementsPage({{ $stock_movements->lastPage() }})">الأخير</a>
                    </li>
                @else
                    <li class="page-item disabled"><span class="page-link">الأخير</span></li>
                @endif
            </ul>
        </nav>
    @endif
@else
    <div class="stk-empty">
        <span class="stk-empty__icon"><i class="feather icon-activity"></i></span>
        <h6 class="stk-empty__title">لا توجد حركات مخزون</h6>
        <p class="stk-empty__hint">لا توجد حركات مخزون لهذا المنتج حتى الآن.</p>
    </div>
@endif
