@extends('account::master')

@section('title')
    تقرير الشيكات المستلمة
@stop

@section('styles')
<link rel="stylesheet" href="{{ asset('css/account-reports.css') }}">
@endsection

@section('content')
    @include('account::components.page-head', [
        'title' => 'تقرير الشيكات المستلمة',
        'crumbs' => [
            'الرئيسية' => null,
            'تقارير الشيكات' => route('checksReports.index'),
            'الشيكات المستلمة' => null,
        ],
    ])

    <div class="content-body">
        {{-- Filter Card --}}
        <div class="card stk-filters no-print">
            <div class="stk-filters__head">
                <h6 class="stk-filters__title"><i class="feather icon-filter"></i> خيارات البحث والفلترة</h6>
            </div>
            <div>
                <div class="card-body">
                    <form method="GET" action="{{ route('checksReports.received') }}" id="filterForm">
                        {{-- Basic Filters --}}
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <label class="form-label">الحساب المستلم</label>
                                <select name="recipient_account" class="form-control select2">
                                    <option value="">جميع الحسابات</option>
                                    @foreach($recipient_accounts as $account)
                                        <option value="{{ $account->id }}" {{ request('recipient_account') == $account->id ? 'selected' : '' }}>{{ $account->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">حساب التحصيل</label>
                                <select name="collection_account" class="form-control select2">
                                    <option value="">جميع حسابات التحصيل</option>
                                    @foreach($collection_accounts as $account)
                                        <option value="{{ $account->id }}" {{ request('collection_account') == $account->id ? 'selected' : '' }}>{{ $account->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">العميل</label>
                                <select name="client_id" class="form-control select2">
                                    <option value="">جميع العملاء</option>
                                    @foreach($clients ?? [] as $client)
                                        <option value="{{ $client->id }}" {{ request('client_id') == $client->id ? 'selected' : '' }}>{{ $client->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">الحالة</label>
                                <select name="status" class="form-control">
                                    <option value="">جميع الحالات</option>
                                    <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>قيد الانتظار</option>
                                    <option value="under_collection" {{ request('status') == 'under_collection' ? 'selected' : '' }}>برسم التحصيل</option>
                                    <option value="collected" {{ request('status') == 'collected' ? 'selected' : '' }}>محصّل</option>
                                    <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>مرفوض</option>
                                    <option value="endorsed" {{ request('status') == 'endorsed' ? 'selected' : '' }}>مظهّر</option>
                                </select>
                            </div>
                        </div>

                        {{-- Advanced Filters (Collapsible) --}}
                        <div class="collapse {{ request()->anyFilled(['issue_date_from', 'issue_date_to', 'due_date_from', 'due_date_to', 'cheque_number', 'amount_from', 'amount_to', 'payee_name', 'due_status', 'endorsement', 'drawer_bank']) ? 'show' : '' }}" id="advancedFilters">
                            <hr>
                            <div class="row mb-3">
                                {{-- الفترة المحاسبية: اختيارها يملأ حقلَي المدى أدناه --}}
                                @include('partials.accounting-period-filter', [
                                    'fromName' => 'issue_date_from',
                                    'toName' => 'issue_date_to',
                                    'colClass' => 'col-md-3',
                                ])

                                <div class="col-md-3">
                                    <label class="form-label">من تاريخ الإصدار</label>
                                    <input type="date" name="issue_date_from" class="form-control" value="{{ request('issue_date_from') }}">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">إلى تاريخ الإصدار</label>
                                    <input type="date" name="issue_date_to" class="form-control" value="{{ request('issue_date_to') }}">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">من تاريخ الاستحقاق</label>
                                    <input type="date" name="due_date_from" class="form-control" value="{{ request('due_date_from') }}">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">إلى تاريخ الاستحقاق</label>
                                    <input type="date" name="due_date_to" class="form-control" value="{{ request('due_date_to') }}">
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-2">
                                    <label class="form-label">رقم الشيك</label>
                                    <input type="text" name="cheque_number" class="form-control" placeholder="رقم الشيك" value="{{ request('cheque_number') }}">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">المبلغ من</label>
                                    <input type="number" name="amount_from" class="form-control" placeholder="0.00" value="{{ request('amount_from') }}" step="0.01">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">المبلغ إلى</label>
                                    <input type="number" name="amount_to" class="form-control" placeholder="0.00" value="{{ request('amount_to') }}" step="0.01">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">اسم المحرر</label>
                                    <input type="text" name="payee_name" class="form-control" placeholder="اسم المحرر" value="{{ request('payee_name') }}">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">البنك الساحب</label>
                                    <input type="text" name="drawer_bank" class="form-control" placeholder="اسم البنك" value="{{ request('drawer_bank') }}">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">حالة الاستحقاق</label>
                                    <select name="due_status" class="form-control">
                                        <option value="">الكل</option>
                                        <option value="overdue" {{ request('due_status') == 'overdue' ? 'selected' : '' }}>متأخر</option>
                                        <option value="due_today" {{ request('due_status') == 'due_today' ? 'selected' : '' }}>يستحق اليوم</option>
                                        <option value="upcoming" {{ request('due_status') == 'upcoming' ? 'selected' : '' }}>قادم</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-3">
                                    <label class="form-label">نوع التظهير</label>
                                    <select name="endorsement" class="form-control">
                                        <option value="">الكل</option>
                                        <option value="cash" {{ request('endorsement') == 'cash' ? 'selected' : '' }}>نقدي</option>
                                        <option value="bank_deposit" {{ request('endorsement') == 'bank_deposit' ? 'selected' : '' }}>إيداع بنكي</option>
                                        <option value="transfer" {{ request('endorsement') == 'transfer' ? 'selected' : '' }}>تحويل</option>
                                        <option value="other" {{ request('endorsement') == 'other' ? 'selected' : '' }}>أخرى</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">تجميع حسب</label>
                                    <select name="group_by" class="form-control">
                                        <option value="">بدون تجميع</option>
                                        <option value="recipient_account" {{ request('group_by') == 'recipient_account' ? 'selected' : '' }}>الحساب المستلم</option>
                                        <option value="collection_account" {{ request('group_by') == 'collection_account' ? 'selected' : '' }}>حساب التحصيل</option>
                                        <option value="client" {{ request('group_by') == 'client' ? 'selected' : '' }}>العميل</option>
                                        <option value="status" {{ request('group_by') == 'status' ? 'selected' : '' }}>الحالة</option>
                                        <option value="endorsement" {{ request('group_by') == 'endorsement' ? 'selected' : '' }}>نوع التظهير</option>
                                        <option value="drawer_bank" {{ request('group_by') == 'drawer_bank' ? 'selected' : '' }}>البنك الساحب</option>
                                        <option value="month" {{ request('group_by') == 'month' ? 'selected' : '' }}>الشهر</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">نوع العرض</label>
                                    <select name="view_type" class="form-control">
                                        <option value="detailed" {{ ($viewType ?? 'detailed') == 'detailed' ? 'selected' : '' }}>تفصيلي</option>
                                        <option value="summary" {{ ($viewType ?? '') == 'summary' ? 'selected' : '' }}>ملخص</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        {{-- Action Buttons --}}
                        <div class="d-flex justify-content-between align-items-center flex-wrap">
                            <div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fa fa-search me-1"></i> بحث
                                </button>
                                <button type="button" class="btn btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#advancedFilters">
                                    <i class="fa fa-sliders-h me-1"></i> بحث متقدم
                                </button>
                                <a href="{{ route('checksReports.received') }}" class="btn btn-outline-danger">
                                    <i class="fa fa-times me-1"></i> إلغاء الفلترة
                                </a>
                            </div>
                            <div>
                                <button type="button" class="btn btn-outline-success" id="exportExcel">
                                    <i class="fa fa-file-excel me-1"></i> تصدير Excel
                                </button>
                                <button type="button" class="btn btn-outline-primary" id="printBtn">
                                    <i class="fa fa-print me-1"></i> طباعة
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-file-text"></i></span>
                <span class="stk-metric__label">إجمالي المبلغ — {{ $totals['total_count'] ?? 0 }} شيك</span>
                <span class="stk-metric__value">{{ number_format($totals['total_amount'] ?? 0, 2) }}</span>
            </div>

            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-loader"></i></span>
                <span class="stk-metric__label">قيد الانتظار — {{ $statusStats['pending']['count'] ?? 0 }} شيك</span>
                <span class="stk-metric__value">{{ number_format($statusStats['pending']['amount'] ?? 0, 2) }}</span>
            </div>

            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-briefcase"></i></span>
                <span class="stk-metric__label">برسم التحصيل — {{ $statusStats['under_collection']['count'] ?? 0 }} شيك</span>
                <span class="stk-metric__value">{{ number_format($statusStats['under_collection']['amount'] ?? 0, 2) }}</span>
            </div>

            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-check-circle"></i></span>
                <span class="stk-metric__label">محصّلة — {{ $statusStats['collected']['count'] ?? 0 }} شيك</span>
                <span class="stk-metric__value">{{ number_format($statusStats['collected']['amount'] ?? 0, 2) }}</span>
            </div>

            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-x-circle"></i></span>
                <span class="stk-metric__label">مرفوضة — {{ $statusStats['rejected']['count'] ?? 0 }} شيك</span>
                <span class="stk-metric__value">{{ number_format($statusStats['rejected']['amount'] ?? 0, 2) }}</span>
            </div>

            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-repeat"></i></span>
                <span class="stk-metric__label">مظهّرة — {{ $statusStats['endorsed']['count'] ?? 0 }} شيك</span>
                <span class="stk-metric__value">{{ number_format($statusStats['endorsed']['amount'] ?? 0, 2) }}</span>
            </div>
        </div>

        <!-- Report Info -->
        <div class="card mt-2">
            <div class="card-body py-2">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="badge bg-success mr-1">الشيكات المستلمة</span>
                        <span class="text-muted">إجمالي: {{ $totals['total_count'] ?? 0 }} شيك - {{ number_format($totals['total_amount'] ?? 0, 2) }} ريال</span>
                        <span class="mx-2">|</span>
                        <span class="badge bg-danger">متأخرة: {{ $totals['overdue_count'] ?? 0 }}</span>
                        <span class="badge bg-warning">اليوم: {{ $totals['due_today_count'] ?? 0 }}</span>
                        <span class="badge bg-info">قادمة: {{ $totals['upcoming_count'] ?? 0 }}</span>
                    </div>
                    <small class="text-muted">آخر تحديث: {{ now()->format('Y-m-d H:i') }}</small>
                </div>
            </div>
        </div>

        {{-- Charts Section --}}
        @if(count($cheques) > 0)
        <div class="card mt-2 no-print">
            <div class="card-header">
                <h6 class="stk-table-head__title"><i class="fa fa-chart-pie"></i> الرسوم البيانية</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-lg-4 col-12 mb-3">
                        <div class="chart-container" style="height: 250px;">
                            <canvas id="dueStatusChart"></canvas>
                        </div>
                        <p class="text-center mt-2 font-weight-bold">توزيع حسب حالة الاستحقاق</p>
                    </div>
                    <div class="col-lg-4 col-12 mb-3">
                        <div class="chart-container" style="height: 250px;">
                            <canvas id="statusChart"></canvas>
                        </div>
                        <p class="text-center mt-2 font-weight-bold">توزيع حسب الحالة</p>
                    </div>
                    <div class="col-lg-4 col-12 mb-3">
                        <div class="chart-container" style="height: 250px;">
                            <canvas id="clientChart"></canvas>
                        </div>
                        <p class="text-center mt-2 font-weight-bold">أعلى 5 عملاء</p>
                    </div>
                </div>
            </div>
        </div>
        @endif

        {{-- Top Clients Table --}}
        @if(count($clientStats ?? []) > 0)
        <div class="card mt-2 no-print">
            <div class="card-header">
                <h6 class="stk-table-head__title"><i class="fa fa-users"></i> أعلى العملاء من حيث قيمة الشيكات</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-sm">
                        <thead class="thead-light">
                            <tr>
                                <th>#</th>
                                <th>العميل</th>
                                <th class="text-center">عدد الشيكات</th>
                                <th>إجمالي المبلغ</th>
                                <th>النسبة</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($clientStats as $index => $client)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td><strong>{{ $client['name'] }}</strong></td>
                                    <td class="text-center">{{ $client['count'] }}</td>
                                    <td class="font-weight-bold">{{ number_format($client['amount'], 2) }}</td>
                                    <td>
                                        @php $percentage = $totals['total_amount'] > 0 ? ($client['amount'] / $totals['total_amount']) * 100 : 0; @endphp
                                        <div class="progress" style="height: 18px;">
                                            <div class="progress-bar bg-success" style="width: {{ $percentage }}%;">{{ number_format($percentage, 1) }}%</div>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif

        {{-- Report Summary Info --}}
        <div class="card mb-2">
            <div class="card-body py-2">
                <div class="d-flex justify-content-between align-items-center flex-wrap">
                    <div>
                        <strong>إجمالي النتائج:</strong> {{ $totals['total_count'] ?? 0 }} شيك
                        <span class="mx-2">|</span>
                        <strong>المجموع:</strong> {{ number_format($totals['total_amount'] ?? 0, 2) }} ريال
                        <span class="mx-2">|</span>
                        <strong>المتوسط:</strong> {{ number_format($totals['average_amount'] ?? 0, 2) }} ريال
                    </div>
                    <div>
                        <small class="text-muted">
                            <i class="fas fa-calendar"></i> تاريخ التقرير: {{ now()->format('Y-m-d H:i') }}
                        </small>
                    </div>
                </div>
            </div>
        </div>

        {{-- Grouped Data Display --}}
        @if($groupedData)
            <div class="card mb-3">
                <div class="card-header bg-light">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-layer-group me-2"></i>
                        ملخص البيانات المجمعة
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead class="thead-light">
                                <tr>
                                    <th>#</th>
                                    <th>المجموعة</th>
                                    <th>عدد الشيكات</th>
                                    <th>إجمالي المبلغ</th>
                                    <th>النسبة</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php $counter = 1; @endphp
                                @foreach($groupedData as $group)
                                    <tr>
                                        <td>{{ $counter++ }}</td>
                                        <td><strong>{{ $group['name'] }}</strong></td>
                                        <td>{{ $group['count'] }}</td>
                                        <td>{{ number_format($group['amount'], 2) }}</td>
                                        <td>
                                            @php
                                                $percentage = $totals['total_amount'] > 0 ? ($group['amount'] / $totals['total_amount']) * 100 : 0;
                                            @endphp
                                            <div class="progress" style="height: 20px;">
                                                <div class="progress-bar bg-success" role="progressbar" style="width: {{ $percentage }}%;">
                                                    {{ number_format($percentage, 1) }}%
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="table-secondary">
                                <tr>
                                    <th colspan="2">الإجمالي</th>
                                    <th>{{ $totals['total_count'] }}</th>
                                    <th>{{ number_format($totals['total_amount'], 2) }}</th>
                                    <th>100%</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        {{-- Report Table --}}
        <div class="card" id="reportCard">
            <div class="card-header">
                <h6 class="stk-table-head__title"><i class="fas fa-table me-2"></i> تفاصيل الشيكات المستلمة</h6>
            </div>
            <div class="card-content">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered" id="reportTable">
                            <thead class="thead-light">
                                <tr>
                                    <th style="width: 50px;">#</th>
                                    <th>رقم الشيك</th>
                                    <th>العميل/المحرر</th>
                                    <th>البنك الساحب</th>
                                    <th>المبلغ</th>
                                    <th>تاريخ الإصدار</th>
                                    <th>تاريخ الاستحقاق</th>
                                    <th>الحالة</th>
                                    <th>حالة الاستحقاق</th>
                                    <th class="no-print">إجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($cheques as $index => $cheque)
                                    @php
                                        $dueClass = '';
                                        $dueStatus = '';
                                        if ($cheque->due_date) {
                                            $dueDate = \Carbon\Carbon::parse($cheque->due_date);
                                            $today = \Carbon\Carbon::today();
                                            $statusValue = $cheque->status?->value ?? $cheque->status;
                                            if (!in_array($statusValue, ['collected', 'rejected'])) {
                                                if ($dueDate->lt($today)) {
                                                    $dueClass = 'table-danger';
                                                    $dueStatus = 'متأخر';
                                                } elseif ($dueDate->eq($today)) {
                                                    $dueClass = 'table-warning';
                                                    $dueStatus = 'اليوم';
                                                } else {
                                                    $dueStatus = 'قادم';
                                                }
                                            }
                                        }
                                    @endphp
                                    <tr class="{{ $dueClass }}">
                                        <td>{{ $index + 1 }}</td>
                                        <td>
                                            <strong>{{ $cheque->cheque_number ?? '--' }}</strong>
                                        </td>
                                        <td>
                                            {{ $cheque->client->name ?? $cheque->payee_name ?? 'غير محدد' }}
                                        </td>
                                        <td>{{ $cheque->drawer_bank ?? '--' }}</td>
                                        <td class="text-nowrap">
                                            <strong>{{ number_format($cheque->amount ?? 0, 2) }}</strong>
                                        </td>
                                        <td>{{ $cheque->issue_date ? $cheque->issue_date->format('Y-m-d') : '--' }}</td>
                                        <td>{{ $cheque->due_date ? $cheque->due_date->format('Y-m-d') : '--' }}</td>
                                        <td>
                                            @php $statusValue = $cheque->status?->value ?? $cheque->status; @endphp
                                            @switch($statusValue)
                                                @case('pending')
                                                    <span class="badge bg-warning text-dark">قيد الانتظار</span>
                                                    @break
                                                @case('under_collection')
                                                    <span class="badge bg-info">برسم التحصيل</span>
                                                    @break
                                                @case('collected')
                                                    <span class="badge bg-success">محصّل</span>
                                                    @break
                                                @case('rejected')
                                                    <span class="badge bg-danger">مرفوض</span>
                                                    @break
                                                @case('endorsed')
                                                    <span class="badge" style="background: #8b5cf6;">مظهّر</span>
                                                    @break
                                                @default
                                                    <span class="badge bg-light text-dark">{{ $cheque->status_name ?? 'غير محدد' }}</span>
                                            @endswitch
                                        </td>
                                        <td>
                                            @if($dueStatus == 'متأخر')
                                                <span class="badge bg-danger"><i class="fas fa-exclamation-triangle"></i> متأخر</span>
                                            @elseif($dueStatus == 'اليوم')
                                                <span class="badge bg-warning text-dark"><i class="fas fa-clock"></i> اليوم</span>
                                            @elseif($dueStatus == 'قادم')
                                                <span class="badge bg-info">قادم</span>
                                            @else
                                                <span class="badge bg-secondary">--</span>
                                            @endif
                                        </td>
                                        <td class="no-print">
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-primary btn-sm" title="عرض التفاصيل" data-bs-toggle="modal" data-bs-target="#chequeModal{{ $cheque->id }}">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="10" class="text-center py-5">
                                            <div class="text-muted">
                                                <i class="fas fa-inbox fa-3x mb-3"></i>
                                                <p class="mb-0">لا توجد شيكات مطابقة لمعايير البحث</p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                            @if(count($cheques) > 0)
                            <tfoot class="table-secondary">
                                <tr>
                                    <th colspan="4" class="text-end">الإجمالي:</th>
                                    <th>{{ number_format($totals['total_amount'] ?? 0, 2) }}</th>
                                    <th colspan="5"></th>
                                </tr>
                            </tfoot>
                            @endif
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>

    {{-- Cheque Details Modals --}}
    @foreach($cheques as $cheque)
    <div class="modal fade" id="chequeModal{{ $cheque->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">تفاصيل الشيك #{{ $cheque->cheque_number }}</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-sm">
                                <tr><th>رقم الشيك:</th><td>{{ $cheque->cheque_number ?? '--' }}</td></tr>
                                <tr><th>العميل/المحرر:</th><td>{{ $cheque->client->name ?? $cheque->payee_name ?? '--' }}</td></tr>
                                <tr><th>البنك الساحب:</th><td>{{ $cheque->drawer_bank ?? '--' }}</td></tr>
                                <tr><th>فرع البنك:</th><td>{{ $cheque->drawer_branch ?? '--' }}</td></tr>
                                <tr><th>المبلغ:</th><td><strong>{{ number_format($cheque->amount ?? 0, 2) }} ريال</strong></td></tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-sm">
                                <tr><th>تاريخ الإصدار:</th><td>{{ $cheque->issue_date ? $cheque->issue_date->format('Y-m-d') : '--' }}</td></tr>
                                <tr><th>تاريخ الاستحقاق:</th><td>{{ $cheque->due_date ? $cheque->due_date->format('Y-m-d') : '--' }}</td></tr>
                                <tr><th>الحالة:</th><td>{{ $cheque->status_name ?? 'غير محدد' }}</td></tr>
                                <tr><th>الوصف:</th><td>{{ $cheque->description ?? '--' }}</td></tr>
                                <tr><th>تاريخ التحصيل:</th><td>{{ $cheque->collected_at ? $cheque->collected_at->format('Y-m-d H:i') : '--' }}</td></tr>
                            </table>
                        </div>
                    </div>
                    @if($cheque->recipientAccount || $cheque->collectionAccount)
                    <hr>
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>الحساب المستلم:</strong> {{ $cheque->recipientAccount->name ?? '--' }}</p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>حساب التحصيل:</strong> {{ $cheque->collectionAccount->name ?? '--' }}</p>
                        </div>
                    </div>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
                </div>
            </div>
        </div>
    </div>
    @endforeach
@endsection

@section('scripts')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/exceljs/4.4.0/exceljs.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
<script src="{{ asset('js/print-table.js') }}"></script>
<script src="{{ asset('js/excel-export-helper.js') }}"></script>

<script>
    $(document).ready(function() {
        // Initialize Select2
        if ($.fn.select2) {
            $('.select2').select2({
                dir: 'rtl',
                allowClear: true,
                width: '100%'
            });
        }

        // Charts
        @if(isset($chartData) && count($cheques) > 0)
            renderCharts(@json($chartData));
        @endif

        // Export Excel
        $('#exportExcel').click(function() {
            exportReceivedChecksReport();
        });

        // Print
        $('#printBtn').click(function() {
            printReceivedChecksReport();
        });
    });

    function renderCharts(chartData) {
        // Due Status Chart
        new Chart(document.getElementById('dueStatusChart').getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: chartData.dueStatus.labels,
                datasets: [{
                    data: chartData.dueStatus.values,
                    backgroundColor: chartData.dueStatus.colors,
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });

        // Status Chart
        new Chart(document.getElementById('statusChart').getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: chartData.status.labels,
                datasets: [{
                    data: chartData.status.values,
                    backgroundColor: chartData.status.colors,
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });

        // Client Chart
        new Chart(document.getElementById('clientChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: chartData.clients.labels,
                datasets: [{
                    label: 'المبلغ',
                    data: chartData.clients.values,
                    backgroundColor: 'rgba(16, 185, 129, 0.8)',
                    borderColor: 'rgba(16, 185, 129, 1)',
                    borderWidth: 2,
                    borderRadius: 5
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    x: { beginAtZero: true }
                }
            }
        });
    }

    // =====================================
    // تصدير Excel - يدعم الملخص والتفاصيل
    // =====================================
    function exportReceivedChecksReport() {
        const viewType = '{{ $viewType ?? "detailed" }}';
        
        if (viewType === 'summary') {
            exportReceivedChecksSummary();
        } else {
            exportReceivedChecksDetails();
        }
    }

    function exportReceivedChecksSummary() {
        const totals = {
            total_count: {{ $totals['total_count'] ?? 0 }},
            total_amount: {{ $totals['total_amount'] ?? 0 }},
            average_amount: {{ $totals['average_amount'] ?? 0 }},
            pending_count: {{ $statusStats['pending']['count'] ?? 0 }},
            pending_amount: {{ $statusStats['pending']['amount'] ?? 0 }},
            under_collection_count: {{ $statusStats['under_collection']['count'] ?? 0 }},
            under_collection_amount: {{ $statusStats['under_collection']['amount'] ?? 0 }},
            collected_count: {{ $statusStats['collected']['count'] ?? 0 }},
            collected_amount: {{ $statusStats['collected']['amount'] ?? 0 }},
            rejected_count: {{ $statusStats['rejected']['count'] ?? 0 }},
            rejected_amount: {{ $statusStats['rejected']['amount'] ?? 0 }},
            endorsed_count: {{ $statusStats['endorsed']['count'] ?? 0 }},
            endorsed_amount: {{ $statusStats['endorsed']['amount'] ?? 0 }},
            overdue_count: {{ $totals['overdue_count'] ?? 0 }},
            due_today_count: {{ $totals['due_today_count'] ?? 0 }},
            upcoming_count: {{ $totals['upcoming_count'] ?? 0 }}
        };

        // جمع بيانات الملخص من الجدول المجمع
        const summaryTableData = [];
        @if($groupedData)
            @foreach($groupedData as $group)
                summaryTableData.push({
                    values: [
                        summaryTableData.length + 1,
                        '{{ $group["name"] }}',
                        {{ $group['count'] }},
                        {{ $group['amount'] }},
                        '{{ number_format(($totals["total_amount"] > 0 ? ($group["amount"] / $totals["total_amount"]) * 100 : 0), 1) }}%'
                    ],
                    alignments: ['center', 'right', 'center', 'center', 'center']
                });
            @endforeach
        @endif

        const config = {
            fileName: 'تقرير_الشيكات_المستلمة_ملخص',
            mainTitle: 'تقرير الشيكات المستلمة - ملخص',
            subTitle: 'نظام المحاسبة المتكامل',
            
            reportInfo: {
                'نوع التقرير': 'ملخص',
                'تاريخ التقرير': new Date().toLocaleDateString('ar-SA')
            },

            statisticsCards: [
                {
                    title: 'إجمالي الشيكات',
                    value: formatNumber(totals.total_count, 0),
                    unit: 'شيك',
                    color: 'primary',
                    borderColor: 'primary'
                },
                {
                    title: 'إجمالي المبلغ',
                    value: formatNumber(totals.total_amount),
                    unit: ' ' + window.AppCurrency.symbol,
                    color: 'primary',
                    borderColor: 'primary'
                },
                {
                    title: 'قيد الانتظار',
                    value: formatNumber(totals.pending_amount),
                    unit: ' ' + window.AppCurrency.symbol,
                    subtitle: totals.pending_count + ' شيك',
                    color: 'warning',
                    borderColor: 'warning'
                },
                {
                    title: 'برسم التحصيل',
                    value: formatNumber(totals.under_collection_amount),
                    unit: ' ' + window.AppCurrency.symbol,
                    subtitle: totals.under_collection_count + ' شيك',
                    color: 'primary',
                    borderColor: 'primary'
                },
                {
                    title: 'محصّلة',
                    value: formatNumber(totals.collected_amount),
                    unit: ' ' + window.AppCurrency.symbol,
                    subtitle: totals.collected_count + ' شيك',
                    color: 'success',
                    borderColor: 'success'
                },
                {
                    title: 'مرفوضة',
                    value: formatNumber(totals.rejected_amount),
                    unit: ' ' + window.AppCurrency.symbol,
                    subtitle: totals.rejected_count + ' شيك',
                    color: 'danger',
                    borderColor: 'danger'
                }
            ],

            tableTitle: 'ملخص الشيكات المجمعة',
            tableHeaders: ['#', 'المجموعة', 'عدد الشيكات', 'إجمالي المبلغ', 'النسبة'],
            columnWidths: [8, 40, 18, 18, 16],
            
            tableData: summaryTableData,

            summaryRows: [
                {
                    label: 'الإجمالي',
                    values: ['', totals.total_count, formatNumber(totals.total_amount), '100%'],
                    mergeFrom: 1,
                    mergeTo: 2,
                    color: 'primary',
                    alignment: 'center'
                }
            ],

            buttonSelector: '#exportExcel',
            successMessage: 'تم تصدير التقرير بنجاح!',
            errorMessage: 'حدث خطأ أثناء تصدير التقرير'
        };

        exportToExcelProfessional(config);
    }

    function exportReceivedChecksDetails() {
        const totals = {
            total_count: {{ $totals['total_count'] ?? 0 }},
            total_amount: {{ $totals['total_amount'] ?? 0 }},
            average_amount: {{ $totals['average_amount'] ?? 0 }},
            pending_count: {{ $statusStats['pending']['count'] ?? 0 }},
            pending_amount: {{ $statusStats['pending']['amount'] ?? 0 }},
            under_collection_count: {{ $statusStats['under_collection']['count'] ?? 0 }},
            under_collection_amount: {{ $statusStats['under_collection']['amount'] ?? 0 }},
            collected_count: {{ $statusStats['collected']['count'] ?? 0 }},
            collected_amount: {{ $statusStats['collected']['amount'] ?? 0 }},
            rejected_count: {{ $statusStats['rejected']['count'] ?? 0 }},
            rejected_amount: {{ $statusStats['rejected']['amount'] ?? 0 }},
            endorsed_count: {{ $statusStats['endorsed']['count'] ?? 0 }},
            endorsed_amount: {{ $statusStats['endorsed']['amount'] ?? 0 }},
            overdue_count: {{ $totals['overdue_count'] ?? 0 }},
            due_today_count: {{ $totals['due_today_count'] ?? 0 }},
            upcoming_count: {{ $totals['upcoming_count'] ?? 0 }}
        };

        // جمع بيانات الجدول التفصيلي
        const tableData = [];
        let rowNumber = 1;
        
        $('#reportTable tbody tr').each(function() {
            const cells = $(this).find('td');
            if (cells.length === 0 || cells.length < 9) return;

            const chequeNumber = $(cells[1]).text().trim();
            const client = $(cells[2]).text().trim();
            const bank = $(cells[3]).text().trim();
            const amount = parseFloat($(cells[4]).text().replace(/,/g, '').trim()) || 0;
            const issueDate = $(cells[5]).text().trim();
            const dueDate = $(cells[6]).text().trim();
            const status = $(cells[7]).text().trim();
            const dueStatus = $(cells[8]).text().trim();

            tableData.push({
                values: [
                    rowNumber++,
                    chequeNumber,
                    client,
                    bank,
                    amount,
                    issueDate,
                    dueDate,
                    status,
                    dueStatus
                ],
                alignments: ['center', 'center', 'right', 'right', 'center', 'center', 'center', 'center', 'center']
            });
        });

        const config = {
            fileName: 'تقرير_الشيكات_المستلمة_تفصيلي',
            mainTitle: 'تقرير الشيكات المستلمة - تفصيلي',
            subTitle: 'نظام المحاسبة المتكامل',
            
            reportInfo: {
                'نوع التقرير': 'تفصيلي',
                'تاريخ التقرير': new Date().toLocaleDateString('ar-SA')
            },

            statisticsCards: [
                {
                    title: 'إجمالي الشيكات',
                    value: formatNumber(totals.total_count, 0),
                    unit: 'شيك',
                    color: 'primary',
                    borderColor: 'primary'
                },
                {
                    title: 'إجمالي المبلغ',
                    value: formatNumber(totals.total_amount),
                    unit: ' ' + window.AppCurrency.symbol,
                    color: 'primary',
                    borderColor: 'primary'
                },
                {
                    title: 'قيد الانتظار',
                    value: formatNumber(totals.pending_amount),
                    unit: ' ' + window.AppCurrency.symbol,
                    subtitle: totals.pending_count + ' شيك',
                    color: 'warning',
                    borderColor: 'warning'
                },
                {
                    title: 'برسم التحصيل',
                    value: formatNumber(totals.under_collection_amount),
                    unit: ' ' + window.AppCurrency.symbol,
                    subtitle: totals.under_collection_count + ' شيك',
                    color: 'primary',
                    borderColor: 'primary'
                },
                {
                    title: 'محصّلة',
                    value: formatNumber(totals.collected_amount),
                    unit: ' ' + window.AppCurrency.symbol,
                    subtitle: totals.collected_count + ' شيك',
                    color: 'success',
                    borderColor: 'success'
                },
                {
                    title: 'مرفوضة',
                    value: formatNumber(totals.rejected_amount),
                    unit: ' ' + window.AppCurrency.symbol,
                    subtitle: totals.rejected_count + ' شيك',
                    color: 'danger',
                    borderColor: 'danger'
                }
            ],

            tableTitle: 'تفاصيل الشيكات المستلمة',
            tableHeaders: ['#', 'رقم الشيك', 'العميل/المحرر', 'البنك الساحب', 'المبلغ', 'تاريخ الإصدار', 'تاريخ الاستحقاق', 'الحالة', 'حالة الاستحقاق'],
            columnWidths: [6, 14, 25, 20, 14, 14, 14, 16, 16],
            
            tableData: tableData,

            summaryRows: [
                {
                    label: 'الإجمالي',
                    values: ['', '', '', formatNumber(totals.total_amount), '', '', '', ''],
                    mergeFrom: 1,
                    mergeTo: 4,
                    color: 'primary',
                    alignment: 'right'
                }
            ],

            buttonSelector: '#exportExcel',
            successMessage: 'تم تصدير التقرير بنجاح!',
            errorMessage: 'حدث خطأ أثناء تصدير التقرير'
        };

        exportToExcelProfessional(config);
    }

    // =====================================
    // الطباعة - تدعم الملخص والتفاصيل
    // =====================================
    function printReceivedChecksReport() {
        const viewType = '{{ $viewType ?? "detailed" }}';
        
        if (viewType === 'summary') {
            printReceivedChecksSummary();
        } else {
            printReceivedChecksDetails();
        }
    }

    function printReceivedChecksSummary() {
        const totals = {
            total_count: {{ $totals['total_count'] ?? 0 }},
            total_amount: {{ $totals['total_amount'] ?? 0 }},
            pending_count: {{ $statusStats['pending']['count'] ?? 0 }},
            pending_amount: {{ $statusStats['pending']['amount'] ?? 0 }},
            collected_count: {{ $statusStats['collected']['count'] ?? 0 }},
            collected_amount: {{ $statusStats['collected']['amount'] ?? 0 }}
        };

        printTable({
            tableSelector: '#reportTable tbody tr',
            tfootSelector: '#reportTable tfoot',
            title: 'تقرير الشيكات المستلمة - ملخص',
            infoCards: [
                { label: 'إجمالي الشيكات', value: totals.total_count + ' شيك' },
                { label: 'إجمالي المبلغ', value: formatNumber(totals.total_amount) + ' ' + window.AppCurrency.symbol },
                { label: 'قيد الانتظار', value: totals.pending_count + ' شيك', class: 'text-warning' },
                { label: 'محصّلة', value: totals.collected_count + ' شيك', class: 'text-success' }
            ],
            filterInfo: {
                'نوع التقرير': 'ملخص',
                'تاريخ التقرير': new Date().toLocaleDateString('ar-SA')
            },
            tableHeaders: ['#', 'المجموعة', 'عدد الشيكات', 'إجمالي المبلغ', 'النسبة'],
            columnWidths: ['5%', '40%', '15%', '20%', '20%'],
            descriptionColumn: 2,
            windowWidth: 1000,
            windowHeight: 800
        });
    }

    function printReceivedChecksDetails() {
        const totals = {
            total_count: {{ $totals['total_count'] ?? 0 }},
            total_amount: {{ $totals['total_amount'] ?? 0 }},
            pending_count: {{ $statusStats['pending']['count'] ?? 0 }},
            pending_amount: {{ $statusStats['pending']['amount'] ?? 0 }},
            collected_count: {{ $statusStats['collected']['count'] ?? 0 }},
            collected_amount: {{ $statusStats['collected']['amount'] ?? 0 }}
        };

        printTable({
            tableSelector: '#reportTable tbody tr:not(.no-print)',
            tfootSelector: '#reportTable tfoot',
            title: 'تقرير الشيكات المستلمة - تفصيلي',
            infoCards: [
                { label: 'إجمالي الشيكات', value: totals.total_count + ' شيك' },
                { label: 'إجمالي المبلغ', value: formatNumber(totals.total_amount) + ' ' + window.AppCurrency.symbol },
                { label: 'قيد الانتظار', value: totals.pending_count + ' شيك', class: 'text-warning' },
                { label: 'محصّلة', value: totals.collected_count + ' شيك', class: 'text-success' }
            ],
            filterInfo: {
                'نوع التقرير': 'تفصيلي',
                'تاريخ التقرير': new Date().toLocaleDateString('ar-SA')
            },
            tableHeaders: ['#', 'رقم الشيك', 'العميل/المحرر', 'البنك الساحب', 'المبلغ', 'تاريخ الإصدار', 'تاريخ الاستحقاق', 'الحالة', 'حالة الاستحقاق'],
            columnWidths: ['3%', '10%', '17%', '15%', '10%', '10%', '10%', '10%', '10%'],
            descriptionColumn: 3,
            windowWidth: 1400,
            windowHeight: 900
        });
    }
</script>
@endsection
