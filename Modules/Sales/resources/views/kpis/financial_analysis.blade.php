@extends('sales::master')

@section('title', 'التحليل المالي ومبيعات الفروع')

@section('content')
<div class="content-wrapper">
    <div class="content-header row">
        <div class="content-header-left col-md-9 col-12 mb-2">
            <div class="row breadcrumbs-top">
                <div class="col-12">
                    <h2 class="content-header-title float-start mb-0">التحليل المالي</h2>
                    <div class="breadcrumb-wrapper">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('dashboard.kpis') }}">المبيعات</a></li>
                            <li class="breadcrumb-item active">التحليل المالي ومبيعات الفروع</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="content-body">
        {{-- من أجل `.sl-guide` وحدها؛ كلُّ قواعدها `.sl-`ـيّة فلا تمسّ
             أنماطَ هذه الشاشة --}}
        @include('sales::partials.settings-theme')
        @include('components.hint-tips')

        {{-- مطويّةٌ افتراضياً: الشاشة تقارير أرقامٍ يُفتَح لأجلها --}}
        @include('sales::components.guide', [
            'key' => 'sales-dashboard.financial-analysis',
            'title' => __('sales_dashboard.guide.financial.title'),
            'lead' => __('sales_dashboard.guide.financial.lead'),
            'open' => false,
            'blocks' => [
                [
                    __('sales_dashboard.guide.financial.a'),
                    __('sales_dashboard.guide.financial.b'),
                    __('sales_dashboard.guide.financial.c'),
                ],
            ],
        ])

        <!-- الفلاتر -->
        <div class="card">
            <div class="card-body">
                <form method="GET" action="{{ route('dashboard.financial_analysis') }}" class="row g-2 align-items-center">
                    @if($isMainBranch)
                    <div class="col-md-3">
                        <label class="form-label">الفرع</label>
                        <select name="branch_id" class="form-select select2">
                            <option value="all">كل الفروع</option>
                            @foreach($branches as $branch)
                                <option value="{{ $branch->id }}" {{ $selected_branch_id == $branch->id ? 'selected' : '' }}>
                                    {{ $branch->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    @endif
                    <div class="col-md-3">
                        <label class="form-label">الفترة</label>
                        <select name="period" class="form-select">
                            <option value="1" {{ request('period') == '1' ? 'selected' : '' }}>اليوم</option>
                            <option value="7" {{ request('period') == '7' ? 'selected' : '' }}>آخر 7 أيام</option>
                            <option value="30" {{ request('period', '30') == '30' ? 'selected' : '' }}>آخر 30 يوم</option>
                            <option value="90" {{ request('period') == '90' ? 'selected' : '' }}>آخر 3 أشهر</option>
                            <option value="365" {{ request('period') == '365' ? 'selected' : '' }}>السنة الحالية</option>
                            <option value="custom" {{ request('period') == 'custom' ? 'selected' : '' }}>مخصص</option>
                        </select>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-1">تصفية</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- 1. القسم الأول: ملخص الفرع (Top Overview Cards) -->
        <div class="row">
            <div class="col-lg-3 col-sm-6 col-12">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="fw-bolder fs-4 mb-0 text-primary">{{ number_format($totalSales, 2) }} ر.س</h5>
                        <p class="card-text">إجمالي المبيعات (Gross Sales)</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-sm-6 col-12">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="fw-bolder fs-4 mb-0 text-danger">{{ number_format($totalReturns, 2) }} ر.س</h5>
                        <p class="card-text">إجمالي المرتجعات (Returns)</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-sm-6 col-12">
                <div class="card text-center bg-light-warning">
                    <div class="card-body">
                        <h5 class="fw-bolder fs-4 mb-0 text-warning" id="display-expenses">{{ number_format($totalExpenses, 2) }} ر.س</h5>
                        <p class="card-text">إجمالي المصروفات التشغيلية</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-sm-6 col-12">
                <div class="card text-center bg-light-success">
                    <div class="card-body">
                        <h5 class="fw-bolder fs-4 mb-0 text-success" id="display-net-profit">{{ number_format($totalSales - ($totalReturns + $totalExpenses), 2) }} ر.س</h5>
                        <p class="card-text">صافي الربح الفعلي للفرع</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. القسم الثاني: حاسبة المصروفات وصافي الربح -->
        <div class="card">
            <div class="card-header border-bottom">
                <h4 class="card-title">حاسبة صافي الربح الفعلي</h4>
            </div>
            <div class="card-body mt-2">
                <div class="row align-items-center">
                    <div class="col-md-4">
                        <label class="form-label" for="operational-expenses">إجمالي المصروفات التشغيلية المسجلة للفرع في هذه الفترة</label>
                        <div class="input-group">
                            <input type="number" id="operational-expenses" class="form-control" value="{{ $totalExpenses }}" readonly>
                            <span class="input-group-text">ر.س</span>
                        </div>
                    </div>
                    <div class="col-md-8 text-center mt-3 mt-md-0">
                        <div class="p-2 border rounded bg-light">
                            <p class="mb-1 text-muted">المعادلة: <strong>(إجمالي المبيعات) - (إجمالي المرتجعات + المصروفات)</strong></p>
                            <h4 class="mb-0 text-success" id="calc-net-profit">
                                {{ number_format($totalSales, 2) }} - ({{ number_format($totalReturns, 2) }} + <span id="calc-exp">{{ number_format($totalExpenses, 2) }}</span>) = <span id="calc-result" class="fw-bold">{{ number_format($totalSales - ($totalReturns + $totalExpenses), 2) }}</span> ر.س
                            </h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. القسم الرابع: تحليل أداء الموظفين -->
        <div class="card">
            <div class="card-header border-bottom">
                <h4 class="card-title">تحليل الأرباح والمبيعات حسب موظف الفرع</h4>
            </div>
            <div class="card-body mt-2">
                <div class="table-responsive">
                    <table class="table table-hover table-bordered text-center">
                        <thead class="table-light">
                            <tr>
                                <th>اسم الموظف</th>
                                <th>إجمالي مبيعاته</th>
                                <th>كمية المباع</th>
                                <th>قيمة المرتجعات من فواتيره</th>
                                <th>نسبة المرتجعات للموظف</th>
                                <th class="text-success">صافي الربح المُدخل للفرع (المبيعات - التكلفة - المرتجعات)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($employeesPerformance as $emp)
                                @php
                                    $empSales = $emp->total_gross_sales;
                                    $empReturns = $emp->returned_value;
                                    $empCOGS = $emp->total_cogs;
                                    $returnRatio = $empSales > 0 ? ($empReturns / $empSales) * 100 : 0;
                                    $empNetProfit = $empSales - ($empCOGS + $empReturns);
                                @endphp
                                <tr>
                                    <td class="fw-bold">{{ $emp->name }}</td>
                                    <td class="text-primary">{{ number_format($empSales, 2) }}</td>
                                    <td>{{ number_format($emp->total_qty_sold, 2) }}</td>
                                    <td class="text-danger">{{ number_format($empReturns, 2) }}</td>
                                    <td>
                                        <span class="badge {{ $returnRatio > 10 ? 'bg-danger' : 'bg-success' }}">
                                            {{ number_format($returnRatio, 2) }}%
                                        </span>
                                    </td>
                                    <td class="text-success fw-bolder">{{ number_format($empNetProfit, 2) }}</td>
                                </tr>
                            @endforeach
                            @if($employeesPerformance->isEmpty())
                                <tr>
                                    <td colspan="6">لا توجد بيانات للفترة المحددة.</td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- 3. القسم الثالث: جدول أداء المنتجات -->
        <div class="card">
            <div class="card-header border-bottom">
                <h4 class="card-title">البيانات الأساسية للمنتجات</h4>
            </div>
            <div class="card-body mt-2">
                <div class="table-responsive">
                    <table class="table table-hover table-bordered text-center">
                        <thead class="table-light">
                            <tr>
                                <th>اسم المنتج</th>
                                <th>الكمية المباعة</th>
                                <th>إجمالي المبيعات</th>
                                <th>كمية المرتجعات</th>
                                <th>قيمة المرتجعات</th>
                                <th>صافي المبيعات للمنتج</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($productsPerformance as $product)
                                @php
                                    $netProductSales = $product->total_gross_sales - $product->returned_value;
                                @endphp
                                <tr>
                                    <td class="text-start">{{ $product->name }}</td>
                                    <td>{{ number_format($product->total_qty_sold, 2) }}</td>
                                    <td class="text-primary">{{ number_format($product->total_gross_sales, 2) }}</td>
                                    <td>{{ number_format($product->returned_qty, 2) }}</td>
                                    <td class="text-danger">{{ number_format($product->returned_value, 2) }}</td>
                                    <td class="fw-bold text-success">{{ number_format($netProductSales, 2) }}</td>
                                </tr>
                            @endforeach
                            @if($productsPerformance->isEmpty())
                                <tr>
                                    <td colspan="6">لا توجد بيانات للفترة المحددة.</td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>
@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const expensesInput = document.getElementById('operational-expenses');
        const displayExpenses = document.getElementById('display-expenses');
        const displayNetProfit = document.getElementById('display-net-profit');
        const calcExp = document.getElementById('calc-exp');
        const calcResult = document.getElementById('calc-result');

        const totalSales = {{ $totalSales ?? 0 }};
        const totalReturns = {{ $totalReturns ?? 0 }};
        const totalExpenses = {{ $totalExpenses ?? 0 }};

        function calculateNetProfit() {
            const expenses = totalExpenses;
            const netProfit = totalSales - (totalReturns + expenses);

            // Formatting
            const formattedExp = expenses.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            const formattedNet = netProfit.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});

            displayExpenses.textContent = formattedExp + ' ر.س';
            displayNetProfit.textContent = formattedNet + ' ر.س';
            calcExp.textContent = formattedExp;
            calcResult.textContent = formattedNet;
        }

        // Run once to initialize
        calculateNetProfit();
    });
</script>
@endsection
