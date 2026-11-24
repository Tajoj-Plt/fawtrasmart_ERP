@extends('sales::master')

@section('title') تقرير العملاء الموحد @stop

{{--
    القسم هنا `css` لا `styles`: `sales::master` يكدّس `styles` بـ `@stack`
    ولا يلتقط الأقسام، ويمرّر `@yield('css')` عبر `layouts.head_rtl` — فكان
    كل CSS هذه الشاشة يسقط صامتاً ولا يصل إلى الصفحة.
--}}
@section('css')
@include('sales::sals_report.report_partials.styles')
<link rel="stylesheet" href="{{ asset('css/account-reports.css') }}">
@include('sales::sals_report.partials.report_filters_balance_like_styles')
<style>
    /* تنسيق أزرار الفترات الزمنية */
    .period-tabs { display: flex; gap: 8px; margin-bottom: 15px; flex-wrap: wrap; }
    .period-tabs .btn { flex: 1; min-width: 80px; border-radius: 8px; padding: 10px 12px; font-weight: 600; transition: all 0.3s ease; font-size: 0.85rem; }
    .period-tabs .btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
    .period-tabs .btn.btn-primary { box-shadow: 0 4px 15px rgba(115, 103, 240, 0.4); }

    .stat-card { border-radius: 10px; margin-bottom: 20px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); transition: transform 0.2s; }
    .stat-card:hover { transform: translateY(-5px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
    .filter-card { border-radius: 10px; margin-bottom: 20px; }

    /* تحسين جدول الملخص - مطابق لأرصدة العملاء */
    .summary-table { border-radius: 10px; overflow: hidden; white-space: nowrap; }
    .summary-table thead th { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; font-weight: 600; padding: 12px 15px; border: none; text-align: center; }
    .summary-table tbody tr { transition: all 0.2s ease; }
    .summary-table tbody tr:hover { background-color: rgba(115, 103, 240, 0.08); transform: scale(1.01); }
    .summary-table tbody td { padding: 10px 15px; vertical-align: middle; text-align: center; border-bottom: 1px solid #e9ecef; }
    .summary-table .customer-name { font-weight: 600; color: #2d3748; text-align: right; }
    .summary-table .amount-cell { font-weight: 600; font-family: 'Roboto', sans-serif; }
    .tbl-unified tfoot tr { background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); font-weight: 700; }
    .tbl-unified tfoot td { padding: 15px; font-size: 1.05rem; border-top: 2px solid #dee2e6; color: #000; }

    .unified-badge { font-size: 0.78rem; padding: 4px 9px; border-radius: 12px; font-weight: 600; }
    .days-badge-ok   { background: #d4f7e0; color: #1b8a4a; }
    .days-badge-warn { background: #fff3cd; color: #856404; }
    .days-badge-danger { background: #fde8e8; color: #b91c1c; }
    .days-badge-critical { background: #1a0000; color: #ff6b6b; }

    .balance-positive { color: #ea5455; font-weight: 700; }
    .balance-zero { color: #6c757d; }
    .balance-negative { color: #28c76f; font-weight: 700; }

    @media print { .no-print { display: none !important; } .tbl-unified thead th { background: #667eea !important; -webkit-print-color-adjust: exact; } .stat-card { break-inside: avoid; } }

    /* تحسين شكل الـ Tooltip ليدعم الأسطر المتعددة */
    .tooltip-inner {
        white-space: pre-wrap !important;
        max-width: 400px !important;
        text-align: right !important;
        direction: rtl !important;
    }
</style>
@endsection

@section('content')

    {{-- من أجل `.sl-guide` وحدها؛ شاشاتُ التقارير على ورقة `rpt-*` الخاصّة
         بها ولا تتقاطع معها في محدِّد --}}
    @include('sales::partials.settings-theme')
    @include('components.hint-tips')

    {{-- ══════════ إرشادُ الشاشة ══════════
         مطويّةٌ افتراضياً: من فتح تقريراً جاء لأرقامه --}}
    <div class="no-print">
        @include('sales::components.guide', [
            'key' => 'sales-reports.unified-customer',
            'title' => __('sales_reports.guide.hub.title'),
            'lead' => __('sales_reports.guide.hub.lead'),
            'open' => false,
            'blocks' => [
                [
                    __('sales_reports.guide.hub.a'),
                    __('sales_reports.guide.hub.b'),
                    __('sales_reports.guide.hub.c'),
                    __('sales_reports.guide.hub.d'),
                ],
            ],
        ])
    </div>
<div class="content-header row">
    <div class="content-header-left col-md-9 col-12 mb-2">
        <div class="row breadcrumbs-top">
            <div class="col-12">
                <h2 class="content-header-title float-left mb-0">تقرير العملاء الموحد</h2>
                <div class="breadcrumb-wrapper col-12">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('salesReports.index') }}">تقارير المبيعات</a></li>
                        <li class="breadcrumb-item active">الموحد</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="content-body rpt">
@include('layouts.alerts.success')
@include('layouts.alerts.error')

{{-- ===== فلاتر ===== --}}
<div class="card filter-card no-print">
    <div class="card-header">
        <h4 class="card-title"><i class="fa fa-filter"></i> خيارات البحث</h4>
        <a class="heading-elements-toggle"><i class="la la-ellipsis-v font-medium-3"></i></a>
        <div class="heading-elements"><ul class="list-inline mb-0"><li><a data-action="collapse"><i class="ft-minus"></i></a></li></ul></div>
    </div>
    <div class="card-content collapse show">
        <div class="card-body">
            <form method="GET" action="{{ route('salesReports.unified') }}" id="filterForm">
                {{-- أزرار الفترات --}}
                <div class="row mb-2">
                    <div class="col-12">
                        <div class="period-tabs">
                            @foreach(['1'=>'اليوم','7'=>'أسبوع','30'=>'شهر','90'=>'3 أشهر','180'=>'6 أشهر','365'=>'سنة','custom'=>'مخصص'] as $p => $label)
                            <button type="button" class="btn period-btn {{ $period == $p ? 'btn-primary' : 'btn-outline-primary' }}" data-period="{{ $p }}">
                                {{ $label }}
                            </button>
                            @endforeach
                        </div>
                    </div>
                </div>
                <input type="hidden" name="period" id="periodInput" value="{{ $period }}">
                <input type="hidden" name="cascade_node" id="cascade_node" value="{{ request('cascade_node') }}">
                <div class="row mt-2" id="cascadeContainer"></div>

                <div class="row mt-2">
                    <div class="col-md-3 col-sm-6 mb-1">
                        <label>من تاريخ</label>
                        <input type="date" name="date_from" id="date_from" class="form-control" value="{{ $fromDate->format('Y-m-d') }}">
                    </div>
                    <div class="col-md-3 col-sm-6 mb-1">
                        <label>إلى تاريخ</label>
                        <input type="date" name="date_to" id="date_to" class="form-control" value="{{ $toDate->format('Y-m-d') }}">
                    </div>
                    <div class="col-md-3 col-sm-6 mb-1">
                        <label>العميل</label>
                        <select name="client_id" class="form-control select2">
                            <option value="">الكل</option>
                            @foreach($clients as $c)
                            <option value="{{ $c->id }}" {{ $filterClientId == $c->id ? 'selected' : '' }}>
                                {{ $c->code }} - {{ $c->trade_name }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-1">
                        <label>الموظف</label>
                        <select name="employee_id" class="form-control select2">
                            <option value="">الكل</option>
                            @foreach($employees as $e)
                            <option value="{{ $e->id }}" {{ $filterEmployeeId == $e->id ? 'selected' : '' }}>{{ $e->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="row mt-1">
                    <div class="col-md-3 col-sm-6 mb-1">
                        <label>تصنيف العميل</label>
                        <select name="category_id" class="form-control select2">
                            <option value="">الكل</option>
                            @foreach($categories as $cat)
                            <option value="{{ $cat->id }}" {{ $filterCategoryId == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-1">
                        <label>حالة العميل</label>
                        <select name="status_id" class="form-control select2">
                            <option value="except_suspended" {{ $filterStatusId === 'except_suspended' ? 'selected' : '' }}>الكل ماعدا الموقوف</option>
                            <option value="all" {{ $filterStatusId === 'all' ? 'selected' : '' }}>الكل</option>
                            @foreach($statuses as $s)
                            <option value="{{ $s->id }}" {{ (string)$filterStatusId === (string)$s->id ? 'selected' : '' }}>{{ $s->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div> <!-- إغلاق صف الفلاتر الأول -->

                <div class="row mt-2">
                     <!-- أزرار البحث -->
                     <div class="col-md-6 col-sm-12 mb-1">
                         <label>&nbsp;</label>
                         <div class="d-flex align-items-center">
                             <div class="custom-control custom-checkbox mr-2">
                                 <input type="checkbox" name="show_zero" class="custom-control-input" id="show_zero" {{ $showZero ? 'checked' : '' }} value="1">
                                 <label class="custom-control-label font-weight-bold" for="show_zero">إظهار الأرصدة الصفرية</label>
                             </div>
                             <button type="submit" class="btn btn-primary mr-1">
                                 <i class="fa fa-search"></i> بحث
                             </button>
                             <a href="{{ route('salesReports.unified') }}" class="btn btn-outline-secondary mr-2">
                                 <i class="fa fa-refresh"></i> إلغاء البحث
                             </a>
                         </div>
                     </div>

                    <!-- أزرار الطباعة والتصدير -->
                    <div class="col-md-6 col-sm-12 mb-1">
                        <label>&nbsp;</label>
                        <div>
                            <button type="button" id="printBtn" class="btn btn-success">
                                <i class="fa fa-print"></i> طباعة
                            </button>
                            <button type="button" id="exportExcel" class="btn btn-info ml-1">
                                <i class="fa fa-file-excel-o"></i> Excel
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ===== بطاقات الإحصائيات ===== --}}
<div class="row">
    <div class="col-lg-3 col-md-6 col-12">
        <div class="card stat-card">
            <div class="card-header d-flex flex-column align-items-start pb-0">
                <div class="avatar bg-rgba-primary p-50 m-0"><div class="avatar-content"><i class="feather icon-users text-primary font-medium-5"></i></div></div>
                <h2 class="text-bold-700 mt-1">{{ $totals['total_clients'] }}</h2>
                <p class="mb-0">عدد العملاء</p>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 col-12">
        <div class="card stat-card">
            <div class="card-header d-flex flex-column align-items-start pb-0">
                <div class="avatar bg-rgba-success p-50 m-0"><div class="avatar-content"><i class="feather icon-trending-up text-success font-medium-5"></i></div></div>
                <h2 class="text-bold-700 mt-1 text-success">{{ number_format($totals['total_sales'], 2) }}</h2>
                <p class="mb-0">إجمالي المبيعات (ريال)</p>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 col-12">
        <div class="card stat-card">
            <div class="card-header d-flex flex-column align-items-start pb-0">
                <div class="avatar bg-rgba-info p-50 m-0"><div class="avatar-content"><i class="feather icon-dollar-sign text-info font-medium-5"></i></div></div>
                <h2 class="text-bold-700 mt-1 text-info">{{ number_format($totals['total_collections'], 2) }}</h2>
                <p class="mb-0">إجمالي التحصيلات (ريال)</p>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 col-12">
        <div class="card stat-card">
            <div class="card-header d-flex flex-column align-items-start pb-0">
                <div class="avatar bg-rgba-warning p-50 m-0"><div class="avatar-content"><i class="feather icon-activity text-warning font-medium-5"></i></div></div>
                <h2 class="text-bold-700 mt-1 {{ $totals['total_balance'] > 0 ? 'text-danger' : ($totals['total_balance'] < 0 ? 'text-success' : '') }}">
                    {{ number_format(abs($totals['total_balance']), 2) }}
                </h2>
                <p class="mb-0">إجمالي الأرصدة (ريال)</p>
            </div>
        </div>
    </div>
</div>

{{-- ===== شريط المعلومات ===== --}}
<div class="card mt-1 no-print">
    <div class="card-body py-2">
        <div class="d-flex justify-content-between align-items-center flex-wrap">
            <div>
                <span class="badge badge-light-primary"><i class="fa fa-calendar"></i> الفترة: {{ $fromDate->format('Y-m-d') }} — {{ $toDate->format('Y-m-d') }}</span>
            </div>
            <div>
                <span class="badge badge-light-info ml-1"><i class="fa fa-users"></i> عدد العملاء: <strong>{{ count($reportData) }}</strong></span>
            </div>
        </div>
    </div>
</div>

{{-- ===== الجدول الموحد ===== --}}
<div class="card mt-1" id="reportCard">
    <div class="card-header">
        <h4 class="card-title"><i class="feather icon-grid"></i> التقرير الموحد للعملاء</h4>
    </div>
    <div class="card-content">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover summary-table mb-0" id="reportTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>الكود</th>
                            <th>العميل</th>
                            <th>الفرع</th>
                            <th>التصنيف</th>
                            <th>المبيعات / مرتجع</th>
                            <th>التحصيلات</th>
                            <th>الرصيد</th>
                            <th>تاريخ آخر زيارة</th>
                            <th style="max-width: 200px;">الملاحظة</th>
                            <th>مدة المديونية</th>
                            <th>العهدة</th>
                            <th>الحالة</th>
                            <th style="width: 10%">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($reportData as $i => $row)
                        @php
                            $days = $row['days_late'];
                            $daysClass = 'days-badge-ok';
                            if ($days > 30) { $daysClass = 'days-badge-danger'; } elseif ($days > 7) { $daysClass = 'days-badge-warn'; }
                            $bal = $row['balance'];
                            $balClass = $bal > 0 ? 'balance-positive' : ($bal < 0 ? 'balance-negative' : 'balance-zero');
                        @endphp
                        <tr class="text-nowrap">
                            <td>{{ $i + 1 }}</td>
                            <td><small class="text-muted">{{ $row['client_code'] }}</small></td>
                            <td class="text-right text-truncate" style="max-width: 200px;">
                                <a href="{{ route('clients.show', $row['client_id']) }}" class="font-weight-bold" style="color: #2d3748; text-decoration: none;" title="عرض ملف العميل">
                                    {{ $row['client_name'] }}
                                </a>
                            </td>
                            <td><span class="badge badge-light-secondary">{{ $row['branch_name'] }}</span></td>
                            <td><span class="badge badge-light-info">{{ $row['category_name'] }}</span></td>
                            {{-- ==== خلية المبيعات + hidden div popover ==== --}}
                            @php
                                $salesPopHtml = '<div style="min-width:220px;">';
                                $hasSalesRows = false;
                                foreach(($row['unified_history'] ?? []) as $uh) {
                                    if(in_array($uh['type'], ['مبيعات','مرتجع'])) {
                                        $hasSalesRows = true;
                                        $isReturn  = $uh['type'] === 'مرتجع';
                                        $rowBg   = $isReturn ? '#fff5f5' : '#f0fff4';
                                        $amtClr  = $isReturn ? '#ea5455' : '#28c76f';
                                        $lbl     = $isReturn ? 'مرتجع' : 'فاتورة';
                                        $pfx     = $isReturn ? '(-) ' : '';
                                        $salesPopHtml .=
                                            '<div style="display:flex;justify-content:space-between;align-items:center;'.
                                            'padding:5px 10px;border-bottom:1px solid #eee;background:'.$rowBg.';">'.
                                            '<small style="color:#bbb;font-size:0.73rem;">'.\Carbon\Carbon::parse($uh['date'])->format('Y-m-d').'</small>'.
                                            '<span style="color:#888;font-size:0.75rem;padding:0 5px;">'.$lbl.'</span>'.
                                            '<strong style="color:'.$amtClr.';font-size:0.9rem;">'.$pfx.number_format($uh['amount'],2).'</strong>'.
                                            '</div>';
                                    }
                                }
                                if(!$hasSalesRows) {
                                    $salesPopHtml .= '<div style="color:#aaa;padding:10px;text-align:center;font-size:0.85rem;">لا توجد فواتير في هذه الفترة</div>';
                                }
                                $salesPopHtml .= '</div>';
                            @endphp
                            <div id="spop-{{ $row['client_id'] }}" style="display:none">{!! $salesPopHtml !!}</div>
                            <td class="amount-cell text-success font-weight-bold pop-cell"
                                style="cursor:pointer;"
                                data-pop-target="spop-{{ $row['client_id'] }}"
                                data-pop-title="فواتير ومرتجعات">
                                {{ number_format($row['net_sales'], 2) }}
                                @if($row['returns_amount'] > 0)
                                    <br><small class="text-danger" style="font-size:0.75rem">({{ number_format($row['returns_amount'], 2) }})</small>
                                @endif
                            </td>

                            {{-- ==== خلية التحصيلات + hidden div popover ==== --}}
                            @php
                                $colPopHtml = '<div style="min-width:220px;">';
                                $hasColRows = false;
                                foreach(($row['unified_history'] ?? []) as $uh) {
                                    if($uh['type'] === 'تحصيل') {
                                        $hasColRows = true;
                                        $colPopHtml .=
                                            '<div style="display:flex;justify-content:space-between;align-items:center;'.
                                            'padding:5px 10px;border-bottom:1px solid #eee;background:#f0f9ff;">'.
                                            '<small style="color:#bbb;font-size:0.73rem;">'.\Carbon\Carbon::parse($uh['date'])->format('Y-m-d').'</small>'.
                                            '<strong style="color:#00cfe8;font-size:0.9rem;">'.number_format($uh['amount'],2).'</strong>'.
                                            '</div>';
                                    }
                                }
                                if(!$hasColRows) {
                                    $colPopHtml .= '<div style="color:#aaa;padding:10px;text-align:center;font-size:0.85rem;">لا توجد تحصيلات في هذه الفترة</div>';
                                }
                                $colPopHtml .= '</div>';
                            @endphp
                            <div id="cpop-{{ $row['client_id'] }}" style="display:none">{!! $colPopHtml !!}</div>
                            <td class="amount-cell text-info font-weight-bold pop-cell"
                                style="cursor:pointer;"
                                data-pop-target="cpop-{{ $row['client_id'] }}"
                                data-pop-title="سجل التحصيلات">
                                {{ number_format($row['total_collections'], 2) }}
                                @if($row['last_collection_date'])
                                    <br><small class="text-muted" style="font-size:0.75rem"><i class="fa fa-calendar-check-o"></i> {{ \Carbon\Carbon::parse($row['last_collection_date'])->format('Y-m-d') }}</small>
                                @endif
                            </td>
                            <td class="{{ $balClass }} font-weight-bold">
                                {{ number_format(abs($bal), 2) }}
                            </td>
                            <td>
                                @if($row['last_visit_date'])
                                    @php $vi = $row['visit_info'] ?? null; @endphp
                                    <small class="d-block text-muted">{{ \Carbon\Carbon::parse($row['last_visit_date'])->format('Y-m-d') }}</small>
                                    @if($vi)
                                        <span class="badge badge-{{ $vi['status_class'] }}" style="font-size:0.7rem;">
                                            {{ $vi['status_label'] }}
                                        </span>
                                        <br>
                                        <small class="text-{{ $vi['time_since_class'] }}" style="font-size:0.7rem;">{{ $vi['time_since_visit'] }}</small>
                                    @endif
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-truncate text-right" style="max-width: 160px; cursor: pointer;" data-toggle="tooltip" data-placement="top" title="{{ $row['last_note'] }}">
                                @if($row['last_note'])
                                    <i class="fa fa-comment-o text-primary"></i> 
                                    {{ $row['last_note'] }}
                                @else
                                    <span class="text-muted text-center d-block">—</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge {{ $daysClass }}">{{ $days }} يوم</span>
                            </td>
                            <td>
                                @if($row['last_deposit'] > 0)
                                    <span class="badge badge-light-warning">{{ $row['last_deposit'] }}</span>
                                @else
                                    <small class="text-muted">—</small>
                                @endif
                            </td>
                            <td>
                                <span class="badge" style="background:{{ $row['client_status_color'] ?? '#6c757d' }}22; color:{{ $row['client_status_color'] ?? '#6c757d' }}; border:1px solid {{ $row['client_status_color'] ?? '#6c757d' }}33;">
                                    {{ $row['client_status_name'] }}
                                </span>
                            </td>
                            <td>
                                <div class="btn-group">
                                    <div class="dropdown">
                                        <button class="btn bg-gradient-info fa fa-ellipsis-v mr-1 mb-1 btn-sm"
                                            type="button" data-toggle="dropdown" aria-expanded="false">
                                        </button>
                                        <div class="dropdown-menu dropdown-menu-right">
                                            <a class="dropdown-item" href="#" data-toggle="modal" data-target="#notesModal{{ $row['client_id'] }}">
                                                <i class="fas fa-file-alt me-2 text-primary"></i> ملاحظات العميل
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="15" class="text-center py-5">
                                <i class="fa fa-inbox text-muted fa-3x mb-3 d-block"></i>
                                <h5 class="text-muted">لا توجد بيانات للعرض</h5>
                                <p class="text-muted">حدد معايير البحث واضغط بحث</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                    @if(count($reportData) > 0)
                    <tfoot>
                        <tr style="background:#3c4fe0;color:#fff;font-weight:700;">
                            <td colspan="5" class="text-right">الإجمالي العام</td>
                            <td>
                                {{ number_format(array_sum(array_column($reportData,'net_sales')), 2) }}
                                <br><small style="font-size:0.75rem; color:#e2e8f0;">({{ array_sum(array_column($reportData,'invoice_count')) }} فاتورة)</small>
                            </td>
                            <td>{{ number_format($totals['total_collections'], 2) }}</td>
                            <td>{{ number_format(abs($totals['total_balance']), 2) }}</td>
                            <td colspan="6">—</td>
                        </tr>
                    </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>
</div>

{{-- Modals Loop --}}
@foreach($reportData as $row)
<div class="modal fade text-left" id="notesModal{{ $row['client_id'] }}" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content" style="border-radius: 12px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.2);">
            <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <h4 class="modal-title text-white">
                    <i class="feather icon-clipboard me-2"></i>
                    {{ $row['client_name'] }} — كشف العميل الموحد
                </h4>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close" style="opacity: 1; text-shadow: none;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-0" style="background:#f8f9fa;">

                {{-- ===== معلومات أساسية ===== --}}
                <div class="row mx-0 px-3 pt-3 pb-2" style="background:#fff; border-bottom:1px solid #dee2e6;">
                    <div class="col-md-3 col-6 mb-2">
                        <small class="text-muted d-block">رقم العميل</small>
                        <strong>{{ $row['client_code'] }}</strong>
                    </div>
                    <div class="col-md-3 col-6 mb-2">
                        <small class="text-muted d-block">الفرع</small>
                        <strong>{{ $row['branch_name'] }}</strong>
                    </div>
                    <div class="col-md-3 col-6 mb-2">
                        <small class="text-muted d-block">الموظف</small>
                        <strong>{{ $row['employee_name'] }}</strong>
                    </div>
                    <div class="col-md-3 col-6 mb-2">
                        <small class="text-muted d-block">الحالة</small>
                        <span class="badge" style="background:{{ $row['client_status_color'] ?? '#6c757d' }}22; color:{{ $row['client_status_color'] ?? '#6c757d' }}; border:1px solid {{ $row['client_status_color'] ?? '#6c757d' }}33;">
                            {{ $row['client_status_name'] }}
                        </span>
                    </div>
                </div>

                <div class="p-3">

                {{-- ===== بطاقة الزيارة ===== --}}
                @php $vi = $row['visit_info'] ?? null; @endphp
                <div class="card mb-3 shadow-sm">
                    <div class="card-header py-2" style="background:linear-gradient(135deg,#0f9b58,#00b09b); color:#fff;">
                        <h6 class="mb-0"><i class="feather icon-map-pin"></i> بيانات الزيارة</h6>
                    </div>
                    <div class="card-body py-3">
                        @if($vi)
                        <div class="row align-items-center">
                            <div class="col-md-3 col-6 mb-2 text-center">
                                <div class="border rounded p-2">
                                    <small class="text-muted d-block">آخر زيارة</small>
                                    <strong>{{ $vi['last_visit_date'] ? \Carbon\Carbon::parse($vi['last_visit_date'])->format('Y-m-d') : '—' }}</strong>
                                </div>
                            </div>
                            <div class="col-md-3 col-6 mb-2 text-center">
                                <div class="border rounded p-2">
                                    <small class="text-muted d-block">منذ</small>
                                    <span class="font-weight-bold text-{{ $vi['time_since_class'] }}">{{ $vi['time_since_visit'] ?? '—' }}</span>
                                </div>
                            </div>
                            <div class="col-md-3 col-6 mb-2 text-center">
                                <div class="border rounded p-2">
                                    <small class="text-muted d-block">حالة آخر زيارة</small>
                                    <span class="badge badge-{{ $vi['status_class'] }}">{{ $vi['status_label'] }}</span>
                                </div>
                            </div>
                            <div class="col-md-3 col-6 mb-2 text-center">
                                <div class="border rounded p-2">
                                    <small class="text-muted d-block">إجمالي الزيارات</small>
                                    <strong class="text-primary">{{ $vi['total_visits'] }}</strong>
                                </div>
                            </div>
                            @if(!empty($vi['justification']))
                            <div class="col-12 mt-1">
                                <div class="alert alert-warning py-1 mb-0" style="font-size:0.85rem;">
                                    <i class="feather icon-alert-circle"></i>
                                    <strong>التبرير:</strong> {{ $vi['justification'] }}
                                </div>
                            </div>
                            @endif
                        </div>
                        @else
                            <p class="text-muted mb-0 text-center"><i class="feather icon-calendar"></i> لا توجد زيارات مسجلة لهذا العميل</p>
                        @endif
                    </div>
                </div>

                {{-- ===== الجدول الموحد: زيارات + حركات + ملاحظات ===== --}}
                <style>
                .tbl-unified { width: 100%; border-collapse: separate; border-spacing: 0; }
                .tbl-unified thead th { 
                    background: #f1f5f9; color: #475569; font-weight: 700; 
                    padding: 12px 15px; border-bottom: 2px solid #e2e8f0; text-align: center; font-size: 0.95rem; 
                }
                .tbl-unified tbody tr { transition: all 0.2s ease; border-bottom: 1px solid #f1f5f9; }
                .tbl-unified tbody tr:hover { transform: translateY(-1px); box-shadow: 0 4px 6px rgba(0,0,0,0.02); }
                .tbl-unified tbody td { padding: 14px 15px; vertical-align: middle; border-bottom: 1px solid #f1f5f9; font-size: 0.95rem; }
                .tbl-unified tbody tr:last-child td { border-bottom: none; }
                </style>
                @php
                    // دمج كل البيانات في مصفوفة مفهرسة بالتاريخ
                    $timeline = [];

                    // 1. إضافة الزيارات
                    foreach(($row['visits_timeline'] ?? []) as $vt) {
                        $d = $vt['visit_date'];
                        if (!isset($timeline[$d])) {
                            $timeline[$d] = ['date' => $d, 'visit' => null, 'activities' => [], 'note' => null];
                        }
                        $timeline[$d]['visit']      = $vt;
                        $timeline[$d]['activities'] = $vt['activities'] ?? [];
                    }

                    // 2. إضافة الملاحظات (ربطها بنفس التاريخ إن وُجد، وإلا صف جديد)
                    foreach(($row['notes_list'] ?? []) as $note) {
                        $d = explode(' ', $note['date'])[0]; // Y-m-d فقط
                        if (!isset($timeline[$d])) {
                            $timeline[$d] = ['date' => $d, 'visit' => null, 'activities' => [], 'note' => null];
                        }
                        $timeline[$d]['note'] = $note;
                    }

                    // 3. ترتيب تنازلي بالتاريخ
                    krsort($timeline);
                    $timeline = array_values($timeline);

                    // 4. إجماليات
                    $totalSalesH = array_sum(array_column(array_filter($row['unified_history'] ?? [], fn($x) => $x['type'] === 'مبيعات'), 'amount'));
                    $totalRetH   = array_sum(array_column(array_filter($row['unified_history'] ?? [], fn($x) => $x['type'] === 'مرتجع'), 'amount'));
                    $totalColH   = array_sum(array_column(array_filter($row['unified_history'] ?? [], fn($x) => $x['type'] === 'تحصيل'), 'amount'));
                @endphp

                <div class="card mb-2 stat-card" style="border-radius:10px; overflow:hidden; border:none; box-shadow:0 4px 15px rgba(0,0,0,0.1);">
                    <div class="card-header py-2 d-flex justify-content-between align-items-center"
                         style="background:linear-gradient(135deg,#1e293b,#0f172a); color:#fff; border-bottom:none;">
                        <h6 class="mb-0 font-weight-bold" style="font-family:'Cairo',sans-serif; font-size:1.1rem;">
                            <i class="feather icon-activity text-success"></i>
                            سجل النشاط الكامل — زيارات، حركات، ملاحظات
                        </h6>
                        <div>
                            <span class="badge badge-success ml-1" style="font-size:0.9rem;">فواتير: {{ number_format($totalSalesH,2) }}</span>
                            @if($totalRetH > 0)
                            <span class="badge badge-danger ml-1" style="font-size:0.9rem;">مرتجع: ({{ number_format($totalRetH,2) }})</span>
                            @endif
                            <span class="badge badge-info ml-1" style="font-size:0.9rem;">تحصيل: {{ number_format($totalColH,2) }}</span>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table tbl-unified mb-0 font-cairo">
                                <thead>
                                    <tr>
                                        <th style="width:40px;">#</th>
                                        <th style="width:120px;">التاريخ</th>
                                        <th style="width:140px;">الزيارة</th>
                                        <th>الأنشطة المالية</th>
                                        <th>تفاصيل الملاحظة (إن وجدت)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($timeline as $ti => $entry)
                                    @php
                                        $hasVisit      = !empty($entry['visit']);
                                        $hasActivities = !empty($entry['activities']);
                                        $hasNote       = !empty($entry['note']);
                                        $rowStyle = '';
                                        if ($hasActivities)      $rowStyle = 'background-color: rgba(40, 199, 111, 0.04);';
                                        elseif ($hasVisit)       $rowStyle = 'background-color: rgba(115, 103, 240, 0.03);';
                                        elseif ($hasNote)        $rowStyle = 'background-color: rgba(255, 159, 67, 0.04);';
                                    @endphp
                                    <tr style="{{ $rowStyle }}">
                                        {{-- رقم --}}
                                        <td class="text-muted font-weight-bold">{{ $ti + 1 }}</td>

                                        {{-- التاريخ --}}
                                        <td>
                                            <strong style="color:#2d3748;">{{ $entry['date'] }}</strong>
                                        </td>

                                        {{-- حالة الزيارة --}}
                                        <td>
                                            @if($hasVisit)
                                                <span class="badge badge-{{ $entry['visit']['status_class'] }}" style="font-size:0.85rem; padding:6px 12px; border-radius:6px;">
                                                    {{ $entry['visit']['status_label'] }}
                                                </span>
                                                @if(!empty($entry['visit']['justification']))
                                                    <br><small class="text-muted d-block mt-1" style="font-size:0.8rem; line-height:1.4; white-space:normal; text-align:right;" title="{{ $entry['visit']['justification'] }}">
                                                        {{ Str::limit($entry['visit']['justification'], 40) }}
                                                    </small>
                                                @endif
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>

                                        {{-- الأنشطة المالية --}}
                                        <td>
                                            @if($hasActivities)
                                                <div class="d-flex flex-wrap justify-content-center" style="gap:8px;">
                                                    @foreach($entry['activities'] as $act)
                                                        <span class="badge badge-{{ $act['class'] }}" style="font-size:0.9rem; padding:6px 10px; border-radius:6px; box-shadow:0 2px 4px rgba(0,0,0,0.05);">
                                                            <i class="fa {{ $act['icon'] }} mr-1"></i>
                                                            {{ $act['type'] }}: <strong>{{ number_format($act['amount'], 2) }}</strong>
                                                        </span>
                                                    @endforeach
                                                </div>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>

                                        {{-- تفاصيل الملاحظة --}}
                                        <td style="text-align:right;">
                                            @if($hasNote)
                                                <div style="background:#fff; padding:12px; border-radius:8px; border:1px solid rgba(0,0,0,0.05); box-shadow:0 2px 5px rgba(0,0,0,0.02);">
                                                    @if(!empty($entry['note']['description']))
                                                        <div style="font-size:1rem; font-weight:600; color:#1e293b; white-space:pre-wrap; margin-bottom:8px; line-height:1.5;">{{ $entry['note']['description'] }}</div>
                                                    @endif
                                                    
                                                    <div class="d-flex flex-wrap align-items-center" style="gap:10px;">
                                                        @if(!empty($entry['note']['employee_name']))
                                                            <span class="badge" style="background:#e0e7ff; color:#3730a3; font-size:0.85rem; padding:5px 8px; border-radius:4px;">
                                                                <i class="fa fa-user"></i> كاتب الملاحظة: <strong>{{ $entry['note']['employee_name'] }}</strong>
                                                            </span>
                                                        @endif
                                                        
                                                        @if(!empty($entry['note']['deposit']))
                                                            <span class="badge" style="background:#fef3c7; color:#b45309; font-size:0.85rem; padding:5px 8px; border-radius:4px;">
                                                                <i class="fa fa-money"></i> العهدة: <strong>{{ $entry['note']['deposit'] }}</strong>
                                                            </span>
                                                        @endif
                                                        
                                                        @if(!empty($entry['note']['competitor_docs']))
                                                            <span class="badge" style="background:#fee2e2; color:#b91c1c; font-size:0.85rem; padding:5px 8px; border-radius:4px;">
                                                                <i class="fa fa-file-text-o"></i> استاند المنافس: <strong>{{ $entry['note']['competitor_docs'] }}</strong>
                                                            </span>
                                                        @endif
                                                    </div>
                                                </div>
                                            @else
                                                <span class="text-muted" style="text-align:center; display:block;">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="5" class="text-center py-5">
                                            <div style="width:70px;height:70px;border-radius:50%;background:#eff6ff;display:inline-flex;align-items:center;justify-content:center;margin:0 auto 16px auto;">
                                                <i class="fa fa-inbox" style="font-size:1.8rem;color:#2563a8;"></i>
                                            </div>
                                            <h5 style="color:#1e293b;font-weight:700;margin-bottom:6px;">لا توجد حركات مسجلة</h5>
                                            <p style="color:#64748b;font-size:0.9rem;margin:0;">لم يتم العثور على أي بيانات لهذا العميل.</p>
                                        </td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                </div>{{-- end p-3 --}}
            </div>
            <div class="modal-footer bg-white border-top">
                <div class="d-flex justify-content-between w-100 align-items-center">
                    <small class="text-muted">تم إنشاء هذا الكشف آليًا في {{ \Carbon\Carbon::now()->format('Y-m-d') }}</small>
                    <button type="button" class="btn btn-secondary px-4" data-dismiss="modal">إغلاق</button>
                </div>
            </div>
        </div>
    </div>
</div>
@endforeach

</div>{{-- end content-body --}}
@endsection

@section('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/exceljs/4.4.0/exceljs.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
<script src="{{ asset('js/print-table.js') }}"></script>
<script src="{{ asset('js/excel-export-helper.js') }}"></script>
<script>
function formatNumber(n, d=2){ return parseFloat(n||0).toLocaleString('en-US',{minimumFractionDigits:d,maximumFractionDigits:d}); }
function formatDateForInput(date){
    return date.getFullYear()+'-'+String(date.getMonth()+1).padStart(2,'0')+'-'+String(date.getDate()).padStart(2,'0');
}

$(document).ready(function(){
    // ==== Popover: قراءة المحتوى من hidden divs ====
    $('.pop-cell').each(function() {
        var $el      = $(this);
        var targetId = $el.data('pop-target');
        var title    = $el.data('pop-title') || '';
        $el.popover({
            html      : true,
            trigger   : 'hover',
            placement : 'bottom',
            container : 'body',
            sanitize  : false,
            title     : '<span style="font-size:0.88rem;font-weight:700;">' + title + '</span>',
            content   : function() { return $('#' + targetId).html(); },
            template  :
                '<div class="popover shadow-lg" role="tooltip" style="max-width:360px;direction:rtl;text-align:right;font-family:inherit;border:none;border-radius:10px;overflow:hidden;">' +
                '<div class="arrow"></div>' +
                '<h3 class="popover-header" style="background:linear-gradient(135deg,#3c4fe0,#7367f0);color:#fff;padding:7px 12px;font-size:0.88rem;"></h3>' +
                '<div class="popover-body" style="padding:0;"></div>' +
                '</div>'
        });
    });
    $(document).on('click touchstart', function(e) {
        if (!$(e.target).closest('.pop-cell, .popover').length) {
            $('.pop-cell').popover('hide');
        }
    });

    $('[data-toggle="tooltip"]').tooltip();

    if($.fn.select2){
        $('.select2').each(function(){ if($(this).data('select2')) $(this).select2('destroy'); });
        $('.select2').select2({ dir:'rtl', language:{noResults:()=>"لا توجد نتائج"}, allowClear:true, width:'100%' });
    }

    $('#printBtn').click(function(){ window.print(); });

    $('#exportExcel').click(function(){
        var wb = new ExcelJS.Workbook();
        var ws = wb.addWorksheet('التقرير الموحد', {views:[{rightToLeft:true}]});
        ws.columns = [
            {header:'#',key:'n',width:5},{header:'الكود',key:'code',width:10},
            {header:'العميل',key:'name',width:25},{header:'الفرع',key:'branch',width:15},
            {header:'التصنيف',key:'category',width:15},
            {header:'الموظف',key:'emp',width:15},{header:'الفواتير',key:'inv',width:8},
            {header:'صافي المبيعات',key:'sales',width:15},{header:'التحصيلات',key:'col',width:15},
            {header:'الرصيد',key:'bal',width:15},{header:'آخر تحصيل',key:'last',width:14},
            {header:'آخر فاتورة',key:'last_inv',width:14},{header:'آخر زيارة',key:'last_vis',width:14},
            {header:'آخر ملاحظة',key:'last_note',width:25},
            {header:'الأيام',key:'days',width:8},{header:'العهدة',key:'dep',width:8},
            {header:'الحالة',key:'status',width:12}
        ];
        ws.getRow(1).font={bold:true,color:{argb:'FFFFFFFF'}};
        ws.getRow(1).fill={type:'pattern',pattern:'solid',fgColor:{argb:'FF3C4FE0'}};

        @foreach($reportData as $i => $row)
        ws.addRow({
            n: {{ $i+1 }},
            code: {!! json_encode($row['client_code']) !!},
            name: {!! json_encode($row['client_name']) !!},
            branch: {!! json_encode($row['branch_name']) !!},
            category: {!! json_encode($row['category_name']) !!},
            emp: {!! json_encode($row['employee_name']) !!},
            inv: {{ $row['invoice_count'] }},
            sales: {{ $row['net_sales'] }},
            col: {{ $row['total_collections'] }},
            bal: {{ $row['balance'] }},
            last: {!! json_encode($row['last_collection_date'] ? \Carbon\Carbon::parse($row['last_collection_date'])->format('Y-m-d') : '—') !!},
            last_inv: {!! json_encode($row['last_invoice_date'] ? \Carbon\Carbon::parse($row['last_invoice_date'])->format('Y-m-d') : '—') !!},
            last_vis: {!! json_encode($row['last_visit_date'] ? \Carbon\Carbon::parse($row['last_visit_date'])->format('Y-m-d') : '—') !!},
            last_note: {!! json_encode($row['last_note'] ? $row['last_note'] . ($row['last_note_date'] ? ' ('.$row['last_note_date'].')' : '') : '—') !!},
            days: {{ $row['days_late'] }},
            dep: {{ $row['last_deposit'] }},
            status: {!! json_encode($row['client_status_name']) !!}
        });
        @endforeach

        wb.xlsx.writeBuffer().then(function(buf){
            saveAs(new Blob([buf],{type:'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'}),'تقرير_العملاء_الموحد.xlsx');
        });
    });

    // أزرار الفترات
    $('.period-btn').click(function(){
        var period = String($(this).data('period'));
        $('#periodInput').val(period);
        $('.period-btn').removeClass('btn-primary').addClass('btn-outline-primary');
        $(`.period-btn[data-period="${period}"]`).removeClass('btn-outline-primary').addClass('btn-primary');
        if(period !== 'custom'){
            var today = new Date(), from = new Date();
            var periods = {'1':0,'7':7,'30':30,'90':90,'180':180,'365':365};
            from.setDate(today.getDate() - (periods[period]||30));
            $('#date_from').val(formatDateForInput(from));
            $('#date_to').val(formatDateForInput(today));
            $('#filterForm').submit();
        }
    });
});

// ===== Cascade selects (نفس منطق تقارير التحصيلات) =====
const CASCADE_BRANCHES = @json($cascadeBranches ?? []);
// فرع الهيدر (users.branch_id): يُنتقى تلقائياً في المستوى الأول
const CASCADE_HEADER_BRANCH = @json($cascadeHeaderBranchId ?? (auth()->user()->branch_id ?? null));
(function(){
    function parseValue(v){ if(!v)return null; if(v.indexOf('b_')===0)return{id:parseInt(v.substring(2))}; return null; }
    function getRoots(){ return CASCADE_BRANCHES.filter(b=>!b.parent_id); }
    function getChildren(id){ return CASCADE_BRANCHES.filter(b=>b.parent_id==id); }
    function hasChildren(id){ return getChildren(id).length>0; }
    function branchExists(id){ return CASCADE_BRANCHES.some(b=>b.id==id); }
    function getPath(id){
        var path=[],cur={id:parseInt(id)},s=20;
        while(cur&&s-->0){ path.unshift(cur); var b=CASCADE_BRANCHES.find(x=>x.id==cur.id); cur=(b&&b.parent_id)?{id:b.parent_id}:null; }
        return path;
    }
    function addLevel(lvl,parentId,selVal){
        var opts=lvl===0?getRoots():getChildren(parentId);
        if(!opts.length)return false;
        var lbl=lvl===0?'الفرع الرئيسي':'المستوى '+(lvl+1);
        var $col=$('<div class="col-md-3 col-sm-6 mb-1 cascade-col"></div>');
        $col.append($('<label></label>').text(lbl));
        var $sel=$('<select class="form-control select2 cascade-select" data-level="'+lvl+'"></select>');
        $sel.append('<option value="">-- اختر --</option>');
        opts.forEach(function(b){
            var lbTxt=b.name+(lvl>0?(b.is_territory==1?' [مجموعة]':' [فرع]'):'');
            var s=(selVal===('b_'+b.id))?' selected':'';
            $sel.append('<option value="b_'+b.id+'"'+s+'>'+lbTxt+'</option>');
        });
        $col.append($sel);
        $('#cascadeContainer').append($col);
        if($.fn.select2) $sel.select2({dir:'rtl',language:{noResults:()=>"لا توجد نتائج"},allowClear:true,width:'100%'});
        $sel.on('change',function(){
            var val=$(this).val();
            $('#cascadeContainer .cascade-col').each(function(){
                var l=parseInt($(this).find('select').attr('data-level'),10);
                if(l>lvl){ var $s=$(this).find('select'); if($.fn.select2&&$s.data('select2'))$s.select2('destroy'); $(this).remove(); }
            });
            if(val){ $('#cascade_node').val(val); var p=parseValue(val); if(p&&hasChildren(p.id))addLevel(lvl+1,p.id,null); }
            else{ var $prev=$('#cascadeContainer select[data-level="'+(lvl-1)+'"]'); $('#cascade_node').val($prev.length?($prev.val()||''):''); }
        });
        return true;
    }
    function initCascade(){
        $('#cascadeContainer .cascade-col select').each(function(){ var $s=$(this); if($.fn.select2&&$s.data('select2'))$s.select2('destroy'); });
        $('#cascadeContainer').empty();
        var iv=$('#cascade_node').val(), path=[];
        if(iv){ var p=parseValue(iv); if(p)path=getPath(p.id); }
        // لا عقدة في الطلب: نبدأ من فرع الهيدر منتقى في المستوى الأول
        else if(CASCADE_HEADER_BRANCH && branchExists(CASCADE_HEADER_BRANCH)){ path=getPath(CASCADE_HEADER_BRANCH); }
        var f=path[0]?('b_'+path[0].id):null;
        addLevel(0,null,f);
        for(var i=0;i<path.length;i++){ var nx=path[i+1]||null; addLevel(i+1,path[i].id,nx?('b_'+nx.id):null); }
    }
    $(document).ready(function(){ initCascade(); });
})();
</script>
@endsection