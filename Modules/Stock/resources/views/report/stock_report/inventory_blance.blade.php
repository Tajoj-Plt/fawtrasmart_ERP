@extends('stock::master')

@section('title', 'ملخّص رصيد المخزون')

@section('content')
    @php
        $rows = collect($processedProducts ?? []);

        $grandQuantity = $rows->sum(fn ($product) => $product['total_quantity'] ?? 0);
        $grandValue = $rows->sum(fn ($product) => $product['total_value'] ?? 0);
    @endphp

    <div class="content-body">

        @include('stock::components.page-head', [
            'title' => 'ملخّص رصيد المخزون',
            'icon' => 'layers',
            'sub' => 'أرصدة المنتجات وقيمتها عبر المخازن',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'تقارير المخزون' => route('StorHouseReport.index'),
                'ملخّص رصيد المخزون' => null,
            ],
            'actions' => '<button type="button" id="exportExcel" class="btn btn-outline-secondary">'
                . '<i class="feather icon-download"></i> إكسل</button>'
                . '<button type="button" id="printReport" class="btn btn-outline-secondary">'
                . '<i class="feather icon-printer"></i> طباعة</button>',
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        <div class="card">
            <div class="card-header">
                <h4 class="card-title"><i class="feather icon-filter"></i> بحث وفلترة</h4>
            </div>

            <div class="card-body">
                <form id="balanceForm" method="GET" action="{{ route('StorHouseReport.inventoryBlance') }}" class="row">
                    <div class="col-md-3 col-sm-6">
                        <div class="form-group">
                            <label for="category">التصنيف</label>
                            <select id="category" name="category" class="form-control select2">
                                <option value="">كل التصنيفات</option>
                                @foreach ($categories as $item)
                                    <option value="{{ $item->id }}" {{ request('category') == $item->id ? 'selected' : '' }}>
                                        {{ $item->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="col-md-3 col-sm-6">
                        <div class="form-group">
                            <label for="brand">العلامة التجارية</label>
                            <select id="brand" name="brand" class="form-control select2">
                                <option value="">كل العلامات</option>
                                @foreach ($brands as $brandId => $brandName)
                                    <option value="{{ $brandId }}" {{ request('brand') == $brandId ? 'selected' : '' }}>
                                        {{ $brandName }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="col-md-3 col-sm-6">
                        <div class="form-group">
                            <label for="warehouse">المستودع</label>
                            <select id="warehouse" name="warehouse" class="form-control select2">
                                <option value="">كل المستودعات</option>
                                @foreach ($warehouses as $item)
                                    <option value="{{ $item->id }}" {{ request('warehouse') == $item->id ? 'selected' : '' }}>
                                        {{ $item->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="col-md-3 col-sm-6">
                        <div class="form-group">
                            <label for="status">الحالة</label>
                            <select id="status" name="status" class="form-control">
                                <option value="">كل الحالات</option>
                                @foreach ([1 => 'متاح', 2 => 'مخزون منخفض', 3 => 'مخزون نفد', 4 => 'غير نشط'] as $value => $label)
                                    <option value="{{ $value }}" {{ request('status') == $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="col-md-3 col-sm-6">
                        <div class="form-group">
                            <label for="sort_by">ترتيب حسب</label>
                            <select id="sort_by" name="sort_by" class="form-control">
                                <option value="">افتراضي</option>
                                @foreach ([
                                    'quantity_desc' => 'الكمية تنازلياً',
                                    'quantity_asc' => 'الكمية تصاعدياً',
                                    'value_desc' => 'القيمة تنازلياً',
                                    'value_asc' => 'القيمة تصاعدياً',
                                    'name_asc' => 'الاسم أبجدياً',
                                ] as $value => $label)
                                    <option value="{{ $value }}" {{ request('sort_by') == $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="col-md-3 col-sm-6">
                        <div class="form-group">
                            <label for="group_by">تجميع حسب</label>
                            <select id="group_by" name="group_by" class="form-control">
                                <option value="">بلا تجميع</option>
                                @foreach ([
                                    'category' => 'التصنيف',
                                    'brand' => 'العلامة التجارية',
                                    'warehouse' => 'المستودع',
                                    'status' => 'الحالة',
                                ] as $value => $label)
                                    <option value="{{ $value }}" {{ request('group_by') == $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="col-md-3 col-sm-6">
                        <div class="form-group">
                            <label for="search">بحث</label>
                            <input type="search" id="search" name="search" class="form-control"
                                   placeholder="الاسم أو الكود…" value="{{ request('search') }}">
                        </div>
                    </div>

                    <div class="col-md-3 col-sm-6">
                        <div class="form-group">
                            <label class="d-block">&nbsp;</label>
                            <div class="d-flex" style="gap:.4rem">
                                <button type="submit" class="btn btn-primary flex-grow-1">
                                    <i class="feather icon-search"></i> بحث
                                </button>
                                {{-- كان زرّ «إلغاء الفلترة» من نوع reset فلا يمسح عناوين الاستعلام --}}
                                <a href="{{ route('StorHouseReport.inventoryBlance') }}" class="btn btn-outline-secondary">
                                    <i class="feather icon-rotate-ccw"></i>
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="stk-switch">
                            <input type="checkbox" name="hide_zero_balance" value="1" {{ request('hide_zero_balance') ? 'checked' : '' }}>
                            <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                            <span class="stk-switch__label">
                                <span class="stk-switch__title">إخفاء الرصيد الصفري</span>
                                <span class="stk-switch__hint">تُستبعد المنتجات التي لا رصيد لها</span>
                            </span>
                        </label>
                    </div>
                </form>
            </div>
        </div>

        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-check-circle"></i></span>
                <span class="stk-metric__value stk-num text-success">{{ $totals['available_count'] ?? 0 }}</span>
                <span class="stk-metric__label">منتجات متاحة</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-alert-triangle"></i></span>
                <span class="stk-metric__value stk-num text-warning">{{ $totals['low_stock_count'] ?? 0 }}</span>
                <span class="stk-metric__label">مخزون منخفض</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-x-circle"></i></span>
                <span class="stk-metric__value stk-num text-danger">{{ $totals['out_of_stock_count'] ?? 0 }}</span>
                <span class="stk-metric__label">مخزون نفد</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-dollar-sign"></i></span>
                <span class="stk-metric__value stk-num">@moneyPlain($totals['total_value'] ?? 0)</span>
                <span class="stk-metric__label">إجمالي القيمة</span>
            </div>
        </div>

        <div class="card stk-table-card" id="reportContainer">
            <div class="stk-print-head">
                <h3>ملخّص رصيد المخزون</h3>
                <p>طُبع في {{ now()->format('Y-m-d H:i') }}</p>
            </div>

            <div class="stk-table-head">
                <h6 class="stk-table-head__title">
                    <i class="feather icon-package"></i> المنتجات
                    <span class="stk-count">{{ $rows->count() }}</span>
                </h6>
                <span class="stk-balance stk-num">@money($grandValue)</span>
            </div>

            @if ($rows->count() > 0)
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="reportTable">
                        <thead>
                            <tr>
                                <th style="width:44px">#</th>
                                <th style="width:12%">الكود</th>
                                <th>الاسم</th>
                                <th style="width:15%">التصنيف</th>
                                <th style="width:15%">العلامة التجارية</th>
                                <th class="text-center" style="width:11%">الكمية</th>
                                <th class="text-center" style="width:13%">القيمة</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $product)
                                @php
                                    $quantity = $product['total_quantity'] ?? 0;
                                    $tone = $quantity > 10 ? 'stk-move--success' : ($quantity > 0 ? 'text-warning' : 'stk-move--danger');
                                @endphp

                                <tr>
                                    <td class="stk-num text-muted">{{ $loop->iteration }}</td>
                                    <td class="stk-num">{{ $product['code'] ?? $product['id'] }}</td>
                                    <td><span class="stk-media__title">{{ $product['name'] }}</span></td>
                                    <td>{{ $product['category'] ?? '—' }}</td>
                                    <td>{{ $product['brand'] ?? '—' }}</td>
                                    <td class="text-center stk-num">
                                        <span class="{{ $tone }}">{{ number_format($quantity) }}</span>
                                    </td>
                                    <td class="text-center stk-num">@money($product['total_value'] ?? 0)</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="stk-total-row">
                                <td colspan="5">المجموع الكلي</td>
                                <td class="text-center stk-num">{{ number_format($grandQuantity) }}</td>
                                <td class="text-center stk-num">@money($grandValue)</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @else
                <div class="card-body">
                    <div class="stk-empty">
                        <div class="stk-empty__icon"><i class="feather icon-search"></i></div>
                        <h5 class="stk-empty__title">لا منتجات مطابقة</h5>
                        <p class="stk-empty__hint">خفّف الفلاتر أو امسحها</p>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection

@section('scripts')
    @include('stock::components.report-tools')

    <script>
        $(function () {
            stkReportTools({
                table: '#reportTable',
                fileName: 'ملخص_رصيد_المخزون',
                title: 'ملخّص رصيد المخزون',
                numericColumns: [0, 5, 6],
                cards: [
                    { title: 'منتجات متاحة', value: @json((string) ($totals['available_count'] ?? 0)), color: 'success', borderColor: 'success' },
                    { title: 'مخزون منخفض', value: @json((string) ($totals['low_stock_count'] ?? 0)), color: 'warning', borderColor: 'warning' },
                    { title: 'مخزون نفد', value: @json((string) ($totals['out_of_stock_count'] ?? 0)), color: 'danger', borderColor: 'danger' },
                    { title: 'إجمالي القيمة', value: @json(number_format($totals['total_value'] ?? 0, 2)) },
                ],
                exportButton: '#exportExcel',
                printButton: '#printReport',
                descriptionColumn: 2,
            });
        });
    </script>

    <script>
        $(function () {
            if ($.fn.select2) {
                $('#balanceForm .select2').select2({ placeholder: 'اختر…', dir: 'rtl', width: '100%' });
            }
        });
    </script>
@endsection
