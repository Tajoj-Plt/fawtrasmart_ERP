@extends('account::master')

@section('title')
    تقرير الشيكات المدفوعة
@stop

@section('styles')
<link rel="stylesheet" href="{{ asset('css/account-reports.css') }}">
@endsection

@section('content')
    @include('account::components.page-head', [
        'title' => 'تقرير الشيكات المدفوعة',
        'crumbs' => [
            'الرئيسية' => null,
            'تقارير الشيكات' => route('checksReports.index'),
            'الشيكات المدفوعة' => null,
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
                    <form method="GET" action="{{ route('checksReports.deliveredChecks') }}" id="filterForm">
                        {{-- Basic Filters --}}
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <label class="form-label">البنك</label>
                                <select name="bank_id" class="form-control select2">
                                    <option value="">جميع البنوك</option>
                                    @foreach($banks as $bank)
                                        <option value="{{ $bank->id }}" {{ request('bank_id') == $bank->id ? 'selected' : '' }}>{{ $bank->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">دفتر الشيكات</label>
                                <select name="cheque_book_id" class="form-control select2">
                                    <option value="">جميع الدفاتر</option>
                                    @foreach($cheque_books as $book)
                                        <option value="{{ $book->id }}" {{ request('cheque_book_id') == $book->id ? 'selected' : '' }}>{{ $book->cheque_book_number }}</option>
                                    @endforeach
                                </select>
                            </div>
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
                                <label class="form-label">الحالة</label>
                                <select name="status" class="form-control">
                                    <option value="">جميع الحالات</option>
                                    <option value="issued" {{ request('status') == 'issued' ? 'selected' : '' }}>صادر</option>
                                    <option value="cashed" {{ request('status') == 'cashed' ? 'selected' : '' }}>مصروف</option>
                                    <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>مرفوض</option>
                                    <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>ملغي</option>
                                </select>
                            </div>
                        </div>

                        {{-- Advanced Filters (Collapsible) --}}
                        <div class="collapse {{ request()->anyFilled(['issue_date_from', 'issue_date_to', 'due_date_from', 'due_date_to', 'cheque_number', 'amount_from', 'amount_to', 'payee_name', 'due_status', 'supplier_id']) ? 'show' : '' }}" id="advancedFilters">
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
                                <div class="col-md-3">
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
                                    <label class="form-label">اسم المستفيد</label>
                                    <input type="text" name="payee_name" class="form-control" placeholder="اسم المستفيد" value="{{ request('payee_name') }}">
                                </div>
                                <div class="col-md-3">
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
                                    <label class="form-label">المورد</label>
                                    <select name="supplier_id" class="form-control select2">
                                        <option value="">جميع الموردين</option>
                                        @foreach($suppliers ?? [] as $supplierAccount)
                                            <option value="{{ $supplierAccount->id }}" {{ request('supplier_id') == $supplierAccount->id ? 'selected' : '' }}>{{ $supplierAccount->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">تجميع حسب</label>
                                    <select name="group_by" class="form-control">
                                        <option value="">بدون تجميع</option>
                                        <option value="bank" {{ request('group_by') == 'bank' ? 'selected' : '' }}>البنك</option>
                                        <option value="cheque_book" {{ request('group_by') == 'cheque_book' ? 'selected' : '' }}>دفتر الشيكات</option>
                                        <option value="recipient" {{ request('group_by') == 'recipient' ? 'selected' : '' }}>الحساب المستلم</option>
                                        <option value="status" {{ request('group_by') == 'status' ? 'selected' : '' }}>الحالة</option>
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
                                <a href="{{ route('checksReports.deliveredChecks') }}" class="btn btn-outline-danger">
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
                <span class="stk-metric__icon"><i class="feather icon-send"></i></span>
                <span class="stk-metric__label">صادرة — {{ $statusStats['issued']['count'] ?? 0 }} شيك</span>
                <span class="stk-metric__value">{{ number_format($statusStats['issued']['amount'] ?? 0, 2) }}</span>
            </div>

            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-check-circle"></i></span>
                <span class="stk-metric__label">مصروفة — {{ $statusStats['cashed']['count'] ?? 0 }} شيك</span>
                <span class="stk-metric__value">{{ number_format($statusStats['cashed']['amount'] ?? 0, 2) }}</span>
            </div>

            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-x-circle"></i></span>
                <span class="stk-metric__label">مرفوضة — {{ $statusStats['rejected']['count'] ?? 0 }} شيك</span>
                <span class="stk-metric__value">{{ number_format($statusStats['rejected']['amount'] ?? 0, 2) }}</span>
            </div>

            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-clock"></i></span>
                <span class="stk-metric__label">متأخرة — {{ $totals['overdue_count'] ?? 0 }} شيك</span>
                <span class="stk-metric__value">{{ number_format($totals['overdue_amount'] ?? 0, 2) }}</span>
            </div>

            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-slash"></i></span>
                <span class="stk-metric__label">ملغاة — {{ $statusStats['cancelled']['count'] ?? 0 }} شيك</span>
                <span class="stk-metric__value">{{ number_format($statusStats['cancelled']['amount'] ?? 0, 2) }}</span>
            </div>
        </div>

        <!-- Report Info -->
        <div class="card mt-2">
            <div class="card-body py-2">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="badge bg-danger mr-1">الشيكات المدفوعة</span>
                        <span class="text-muted">إجمالي: {{ $totals['total_count'] ?? 0 }} شيك - {{ number_format($totals['total_amount'] ?? 0, 2) }} ريال</span>
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
                            <canvas id="bankChart"></canvas>
                        </div>
                        <p class="text-center mt-2 font-weight-bold">توزيع حسب البنك</p>
                    </div>
                </div>
            </div>
        </div>
        @endif

        {{-- Grouped Data Display --}}
        @if($groupedData)
            <div class="card mt-2">
                <div class="card-header">
                    <h6 class="stk-table-head__title"><i class="fa fa-layer-group"></i> ملخص البيانات المجمعة</h6>
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
                                                <div class="progress-bar" role="progressbar" style="width: {{ $percentage }}%;">
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
                <h6 class="stk-table-head__title"><i class="fas fa-table me-2"></i> تفاصيل الشيكات المدفوعة</h6>
            </div>
            <div class="card-content">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered" id="reportTable">
                            <thead class="thead-light">
                                <tr>
                                    <th style="width: 50px;">#</th>
                                    <th>رقم الشيك</th>
                                    <th>البنك</th>
                                    <th>دفتر الشيكات</th>
                                    <th>المستفيد</th>
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
                                            if (!in_array($statusValue, ['cashed', 'cancelled'])) {
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
                                        <td>{{ $cheque->bank->name ?? 'غير محدد' }}</td>
                                        <td>{{ $cheque->chequeBook->cheque_book_number ?? '--' }}</td>
                                        <td>
                                            {{ $cheque->recipientAccount->name ?? $cheque->payee_name ?? 'غير محدد' }}
                                        </td>
                                        <td class="text-nowrap">
                                            <strong>{{ number_format($cheque->amount ?? 0, 2) }}</strong>
                                        </td>
                                        <td>{{ $cheque->issue_date ? $cheque->issue_date->format('Y-m-d') : '--' }}</td>
                                        <td>{{ $cheque->due_date ? $cheque->due_date->format('Y-m-d') : '--' }}</td>
                                        <td>
                                            @php $statusValue = $cheque->status?->value ?? $cheque->status; @endphp
                                            @switch($statusValue)
                                                @case('issued')
                                                    <span class="badge bg-primary">صادر</span>
                                                    @break
                                                @case('cashed')
                                                    <span class="badge bg-success">مصروف</span>
                                                    @break
                                                @case('rejected')
                                                    <span class="badge bg-danger">مرفوض</span>
                                                    @break
                                                @case('cancelled')
                                                    <span class="badge bg-secondary">ملغي</span>
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
                                        <td colspan="11" class="text-center py-5">
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
                                    <th colspan="5" class="text-end">الإجمالي:</th>
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
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">تفاصيل الشيك #{{ $cheque->cheque_number }}</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-sm">
                                <tr><th>رقم الشيك:</th><td>{{ $cheque->cheque_number ?? '--' }}</td></tr>
                                <tr><th>البنك:</th><td>{{ $cheque->bank->name ?? 'غير محدد' }}</td></tr>
                                <tr><th>دفتر الشيكات:</th><td>{{ $cheque->chequeBook->cheque_book_number ?? '--' }}</td></tr>
                                <tr><th>المستفيد:</th><td>{{ $cheque->recipientAccount->name ?? $cheque->payee_name ?? '--' }}</td></tr>
                                <tr><th>المبلغ:</th><td><strong>{{ number_format($cheque->amount ?? 0, 2) }} ريال</strong></td></tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-sm">
                                <tr><th>تاريخ الإصدار:</th><td>{{ $cheque->issue_date ? $cheque->issue_date->format('Y-m-d') : '--' }}</td></tr>
                                <tr><th>تاريخ الاستحقاق:</th><td>{{ $cheque->due_date ? $cheque->due_date->format('Y-m-d') : '--' }}</td></tr>
                                <tr><th>الحالة:</th><td>{{ $cheque->status_name ?? 'غير محدد' }}</td></tr>
                                <tr><th>الوصف:</th><td>{{ $cheque->description ?? '--' }}</td></tr>
                                <tr><th>تاريخ الصرف:</th><td>{{ $cheque->cashed_at ? $cheque->cashed_at->format('Y-m-d H:i') : '--' }}</td></tr>
                            </table>
                        </div>
                    </div>
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
            exportDeliveredChecksReport();
        });

        // Print
        $('#printBtn').click(function() {
            printDeliveredChecks();
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

        // Bank Chart
        new Chart(document.getElementById('bankChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: chartData.banks.labels,
                datasets: [{
                    label: 'المبلغ',
                    data: chartData.banks.values,
                    backgroundColor: 'rgba(59, 130, 246, 0.8)',
                    borderColor: 'rgba(59, 130, 246, 1)',
                    borderWidth: 2,
                    borderRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    }

    // =====================================
    // تصدير Excel باستخدام الملف المساعد
    // =====================================
    function exportDeliveredChecksReport() {
        const totals = {
            total_count: {{ $totals['total_count'] ?? 0 }},
            total_amount: {{ $totals['total_amount'] ?? 0 }},
            average_amount: {{ $totals['average_amount'] ?? 0 }},
            issued_count: {{ $statusStats['issued']['count'] ?? 0 }},
            issued_amount: {{ $statusStats['issued']['amount'] ?? 0 }},
            cashed_count: {{ $statusStats['cashed']['count'] ?? 0 }},
            cashed_amount: {{ $statusStats['cashed']['amount'] ?? 0 }},
            rejected_count: {{ $statusStats['rejected']['count'] ?? 0 }},
            rejected_amount: {{ $statusStats['rejected']['amount'] ?? 0 }},
            overdue_count: {{ $totals['overdue_count'] ?? 0 }},
            overdue_amount: {{ $totals['overdue_amount'] ?? 0 }},
            cancelled_count: {{ $statusStats['cancelled']['count'] ?? 0 }},
            cancelled_amount: {{ $statusStats['cancelled']['amount'] ?? 0 }}
        };

        // جمع بيانات الجدول
        const tableData = [];
        let rowNumber = 1;
        
        $('#reportTable tbody tr').each(function() {
            const cells = $(this).find('td');
            if (cells.length === 0 || cells.length < 10) return;

            const chequeNumber = $(cells[1]).text().trim();
            const payee = $(cells[2]).text().trim();
            const bank = $(cells[3]).text().trim();
            const amount = parseFloat($(cells[4]).text().replace(/,/g, '').trim()) || 0;
            const issueDate = $(cells[5]).text().trim();
            const dueDate = $(cells[6]).text().trim();
            const status = $(cells[7]).text().trim();
            const dueStatus = $(cells[8]).text().trim();
            const paidDate = $(cells[9]).text().trim();

            tableData.push({
                values: [
                    rowNumber++,
                    chequeNumber,
                    payee,
                    bank,
                    amount,
                    issueDate,
                    dueDate,
                    status,
                    dueStatus,
                    paidDate
                ],
                alignments: ['center', 'center', 'right', 'right', 'center', 'center', 'center', 'center', 'center', 'center']
            });
        });

        const config = {
            fileName: 'تقرير_الشيكات_المدفوعة',
            mainTitle: 'تقرير الشيكات المدفوعة',
            subTitle: 'نظام المحاسبة المتكامل',
            
            reportInfo: {
                'تاريخ التقرير': new Date().toLocaleDateString('ar-SA')
            },

            statisticsCards: [
                {
                    title: 'إجمالي المبلغ',
                    value: formatNumber(totals.total_amount),
                    unit: ' ' + window.AppCurrency.symbol,
                    subtitle: totals.total_count + ' شيك',
                    color: 'primary',
                    borderColor: 'primary'
                },
                {
                    title: 'صادرة',
                    value: formatNumber(totals.issued_amount),
                    unit: ' ' + window.AppCurrency.symbol,
                    subtitle: totals.issued_count + ' شيك',
                    color: 'primary',
                    borderColor: 'primary'
                },
                {
                    title: 'مصروفة',
                    value: formatNumber(totals.cashed_amount),
                    unit: ' ' + window.AppCurrency.symbol,
                    subtitle: totals.cashed_count + ' شيك',
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
                },
                {
                    title: 'متأخرة',
                    value: formatNumber(totals.overdue_amount),
                    unit: ' ' + window.AppCurrency.symbol,
                    subtitle: totals.overdue_count + ' شيك',
                    color: 'warning',
                    borderColor: 'warning'
                },
                {
                    title: 'ملغاة',
                    value: formatNumber(totals.cancelled_amount),
                    unit: ' ' + window.AppCurrency.symbol,
                    subtitle: totals.cancelled_count + ' شيك',
                    color: 'gray',
                    borderColor: 'gray'
                }
            ],

            tableTitle: 'تفاصيل الشيكات المدفوعة',
            tableHeaders: ['#', 'رقم الشيك', 'المستفيد', 'البنك', 'المبلغ', 'تاريخ الإصدار', 'تاريخ الاستحقاق', 'الحالة', 'حالة الاستحقاق', 'تاريخ الصرف'],
            columnWidths: [6, 14, 20, 18, 14, 14, 14, 14, 16, 14],
            
            tableData: tableData,

            summaryRows: [
                {
                    label: 'الإجمالي',
                    values: ['', '', '', formatNumber(totals.total_amount), '', '', '', '', '', ''],
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
    // الطباعة باستخدام الملف المساعد
    // =====================================
    function printDeliveredChecks() {
        const totals = {
            total_count: {{ $totals['total_count'] ?? 0 }},
            total_amount: {{ $totals['total_amount'] ?? 0 }},
            issued_count: {{ $statusStats['issued']['count'] ?? 0 }},
            issued_amount: {{ $statusStats['issued']['amount'] ?? 0 }},
            cashed_count: {{ $statusStats['cashed']['count'] ?? 0 }},
            cashed_amount: {{ $statusStats['cashed']['amount'] ?? 0 }}
        };

        printTable({
            tableSelector: '#reportTable tbody tr:not(.no-print)',
            tfootSelector: '#reportTable tfoot',
            title: 'تقرير الشيكات المدفوعة',
            infoCards: [
                { label: 'إجمالي الشيكات', value: totals.total_count + ' شيك' },
                { label: 'إجمالي المبلغ', value: formatNumber(totals.total_amount) + ' ' + window.AppCurrency.symbol },
                { label: 'صادرة', value: totals.issued_count + ' شيك', class: 'text-primary' },
                { label: 'مصروفة', value: totals.cashed_count + ' شيك', class: 'text-success' }
            ],
            filterInfo: {
                'تاريخ التقرير': new Date().toLocaleDateString('ar-SA')
            },
            tableHeaders: ['#', 'رقم الشيك', 'المستفيد', 'البنك', 'المبلغ', 'تاريخ الإصدار', 'تاريخ الاستحقاق', 'الحالة', 'حالة الاستحقاق', 'تاريخ الصرف'],
            columnWidths: ['3%', '10%', '15%', '13%', '10%', '10%', '10%', '10%', '12%', '10%'],
            descriptionColumn: 3,
            windowWidth: 1400,
            windowHeight: 900
        });
    }
</script>
@endsection
