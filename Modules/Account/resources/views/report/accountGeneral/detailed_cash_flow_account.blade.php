@extends('account::master')

@section('title')
    تدفقات الحساب: {{ $account->name }}
@stop

@section('content')
@include('account::components.page-head', [
    'title' => 'تفاصيل التدفقات النقدية - '.$account->name,
    'crumbs' => [
        'التقارير المحاسبية' => route('GeneralAccountReports.index'),
        'التدفقات النقدية التفصيلية' => route('GeneralAccountReports.detailedCashFlow', ['from_date' => $fromDate, 'to_date' => $toDate, 'branch' => $branchFilter]),
        $account->name => null,
    ],
])

<div class="content-body">
    {{-- Filters --}}
    <div class="card">
        <div class="card-body">
            <form method="GET" action="{{ route('GeneralAccountReports.detailedCashFlowAccount', ['accountId' => $account->id]) }}" class="form">
                <div class="form-body row">
                    {{-- الفترة المحاسبية: اختيارها يملأ حقلَي المدى أدناه --}}
                    @include('partials.accounting-period-filter', [
                        'fromName' => 'from_date',
                        'toName' => 'to_date',
                        'colClass' => 'form-group col-md-3',
                    ])

                    <div class="form-group col-md-3">
                        <label>من تاريخ</label>
                        <input type="date" name="from_date" class="form-control" value="{{ $fromDate }}">
                    </div>
                    <div class="form-group col-md-3">
                        <label>إلى تاريخ</label>
                        <input type="date" name="to_date" class="form-control" value="{{ $toDate }}">
                    </div>
                    <div class="form-group col-md-3">
                        <label>الفرع</label>
                        <select name="branch" class="form-control">
                            <option value="">جميع الفروع</option>
                            @foreach($branches as $b)
                                <option value="{{ $b->id }}" {{ ($branchFilter ?? '') == $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group col-md-3 d-flex align-items-end">
                        <button class="btn btn-primary me-2"><i class="fa fa-search"></i> بحث</button>
                        <a href="{{ route('GeneralAccountReports.detailedCashFlow', ['from_date' => $fromDate, 'to_date' => $toDate, 'branch' => $branchFilter]) }}" class="btn btn-outline-secondary"><i class="fa fa-arrow-right"></i> رجوع</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Account header --}}
    <div class="row">
        <div class="col-md-3">
            <div class="card stat-card">
                <div class="card-body">
                    <small class="text-muted">الحساب</small>
                    <h5 class="mb-0">{{ $account->name }}</h5>
                    <small class="text-muted">كود: {{ $account->code ?? '-' }}</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card" style="border-left: 4px solid #6c757d;">
                <div class="card-body">
                    <small class="text-muted">الرصيد الافتتاحي</small>
                    <h4 class="{{ $openingBalance >= 0 ? 'text-primary' : 'text-danger' }}">{{ number_format($openingBalance, 2) }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card" style="border-left: 4px solid #28c76f;">
                <div class="card-body">
                    <small class="text-muted">إجمالي مدين / دائن</small>
                    <h5 class="text-success">{{ number_format($totalDebit, 2) }}</h5>
                    <h5 class="text-danger">{{ number_format($totalCredit, 2) }}</h5>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card" style="border-left: 4px solid #7367f0;">
                <div class="card-body">
                    <small class="text-muted">الرصيد الختامي</small>
                    <h4 class="{{ $closingBalance >= 0 ? 'text-primary' : 'text-danger' }}">{{ number_format($closingBalance, 2) }}</h4>
                </div>
            </div>
        </div>
    </div>

    {{-- Movements --}}
    <div class="card">
        <div class="card-content">
            <div class="card-body">
                <h4 class="card-title mb-2"><i class="feather icon-list"></i> الحركات النقدية للحساب</h4>
                @if(empty($movements))
                    <div class="alert alert-info text-center">
                        <i class="feather icon-info"></i>
                        لا توجد حركات نقدية على هذا الحساب خلال الفترة المحددة.
                    </div>
                @else
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="thead-light">
                            <tr>
                                <th>#</th>
                                <th>التاريخ</th>
                                <th>رقم القيد</th>
                                <th>البيان</th>
                                <th>البنك/الخزينة المقابل</th>
                                <th>حسابات أخرى في القيد</th>
                                <th>نوع العملية</th>
                                <th class="text-left">مدين</th>
                                <th class="text-left">دائن</th>
                                <th class="text-left">الرصيد المتجدد</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr style="background:#fffbe6;">
                                <td colspan="9" class="text-end"><strong>الرصيد الافتتاحي</strong></td>
                                <td class="text-left fw-bold {{ $openingBalance >= 0 ? 'text-primary' : 'text-danger' }}">{{ number_format($openingBalance, 2) }}</td>
                            </tr>
                            @foreach($movements as $i => $m)
                                <tr>
                                    <td>{{ $i + 1 }}</td>
                                    <td>{{ $m['date'] }}</td>
                                    <td>
                                        <a href="{{ route('journal.show', ['id' => $m['entry_id']]) }}" class="badge bg-light text-dark" target="_blank">
                                            {{ $m['reference_number'] ?? '-' }}
                                        </a>
                                    </td>
                                    <td>{{ $m['description'] ?: '-' }}</td>
                                    <td><span class="text-info">{{ $m['cash_counter'] ?: '-' }}</span></td>
                                    <td><small class="text-muted">{{ $m['other_counter'] ?: '-' }}</small></td>
                                    <td>
                                        @if($m['op_type'] == 'استلام نقدي')
                                            <span class="badge bg-success">{{ $m['op_type'] }}</span>
                                        @elseif($m['op_type'] == 'دفع نقدي')
                                            <span class="badge bg-danger">{{ $m['op_type'] }}</span>
                                        @else
                                            <span class="badge bg-secondary">-</span>
                                        @endif
                                    </td>
                                    <td class="text-left text-success">{{ $m['debit'] > 0 ? number_format($m['debit'], 2) : '' }}</td>
                                    <td class="text-left text-danger">{{ $m['credit'] > 0 ? number_format($m['credit'], 2) : '' }}</td>
                                    <td class="text-left fw-bold {{ $m['running_balance'] >= 0 ? 'text-primary' : 'text-danger' }}">{{ number_format($m['running_balance'], 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr style="background:#f8f9fa; font-weight:bold;">
                                <td colspan="7" class="text-end">الإجمالي</td>
                                <td class="text-left text-success">{{ number_format($totalDebit, 2) }}</td>
                                <td class="text-left text-danger">{{ number_format($totalCredit, 2) }}</td>
                                <td class="text-left {{ $closingBalance >= 0 ? 'text-primary' : 'text-danger' }}">{{ number_format($closingBalance, 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
