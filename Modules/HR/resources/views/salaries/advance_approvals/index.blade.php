@extends('hr::master')

@section('title')
    إدارة السلف
@stop

@section('content')
    @php
        // صلاحياتُ السلف للصفوف — تُحسب مرّةً لا لكلّ صفّ
        $loanCan = [
            'show' => hasPermission('loans.show'),
            'approve' => hasPermission('loans.approve'),
            'disburse' => hasPermission('loans.disburse'),
            'settle' => hasPermission('loans.settle'),
        ];
    @endphp
    @section('page-actions')
        @if (\Illuminate\Support\Facades\Route::has('hr.advances.export'))
            @permission('loans.index')
                <div class="btn-group">
                    <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-ellipsis-h"></i>
                        <span class="d-none d-md-inline">إجراءات</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><h6 class="dropdown-header">التصدير</h6></li>
                        <li>
                            <a class="dropdown-item" href="{{ route('hr.advances.export', request()->query()) }}">
                                <i class="fas fa-file-excel me-2 text-success"></i>تصدير Excel
                            </a>
                        </li>
                    </ul>
                </div>
            @endpermission
        @endif
        @if (canAll(['loans.create', 'loans.approve', 'loans.disburse']))
            <a href="{{ route('hr.advance-approvals.create') }}" class="btn btn-primary">
                <i class="feather icon-plus"></i> إصدار سلفة لموظف
            </a>
        @endif
    @endsection

    @include('stock::components.page-head', [
        'title' => 'إدارة السلف',
        'icon' => 'arrow-up-circle',
        'crumbs' => ['الرئيسية' => route('hr.department.dashboard'), 'إدارة السلف' => null],
    ])

    <div class="content-body">
        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        {{-- ══════════ إرشادُ الشاشة ══════════ --}}
        @include('hr::components.guide', [
            'key' => 'hr-advance-approvals.index',
            'title' => 'ما هذه الشاشة، وما الذي يترتّب على ما فيها؟',
            'lead' => 'هذه سلفُ الموظفين في نطاقك: الطلباتُ الجديدة والسلفُ القديمة معاً. ولكلّ مرحلةٍ أثرُها المحاسبيّ.',
            'blocks' => [
                'المراحلُ وأثرُها' => [
                    'الاعتماد: قرارٌ بلا مال ولا قيد. والسلفةُ المعتمدةُ غيرُ المصروفة لا تُقتطع من الراتب.',
                    'الصرف: قيدٌ مدينُه حسابُ سلف الموظف ودائنُه الخزينة — ولا يتمّ إن لم يكفِ رصيدُ الخزينة (يُفحص من الدفتر تحت قفل)، فلا تصير الخزينةُ سالبة.',
                    'السداد: اقتطاعٌ من الراتب أو تحصيلٌ نقديّ (مدين الخزينة / دائن حساب السلف). وهو «تسوية» لا إلغاء: السلفةُ تبقى في دورة السداد حتى تُقفل.',
                ],
                'الإلغاء' => [
                    'عمليةٌ مستقلّة: قبل الصرف تُغلق بلا قيد؛ بعد الصرف بقيدٍ عكسيّ يردّ المبلغ إلى الخزينة التي خرج منها؛ ويُمنع متى بدأ السداد أو أُدرجت السلفةُ في مسير رواتب قائم.',
                ],
            ],
            'facts' => [
                'الاعتمادُ لا يُخرج مالاً — الصرفُ يُخرجه',
                'لا صرفَ يجعل الخزينة سالبة',
                'التسويةُ سداد، والإلغاءُ عكسٌ',
            ],
        ])

        <div class="stk-metrics">
            <div class="stk-metric stk-metric--warning">
                <span class="stk-metric__icon"><i class="feather icon-inbox"></i></span>
                <span class="stk-metric__label">بانتظار الاعتماد</span>
                <span class="stk-metric__value stk-num">{{ $statistics['pending'] }}</span>
            </div>
            <div class="stk-metric stk-metric--info">
                <span class="stk-metric__icon"><i class="feather icon-clock"></i></span>
                <span class="stk-metric__label">معتمدة بانتظار الصرف</span>
                <span class="stk-metric__value stk-num">{{ $statistics['awaiting_disbursement'] }}</span>
            </div>
            <div class="stk-metric stk-metric--success">
                <span class="stk-metric__icon"><i class="feather icon-refresh-cw"></i></span>
                <span class="stk-metric__label">في دورة السداد</span>
                <span class="stk-metric__value stk-num">{{ $statistics['open_count'] }}</span>
            </div>
            <div class="stk-metric stk-metric--danger">
                <span class="stk-metric__icon"><i class="feather icon-trending-up"></i></span>
                <span class="stk-metric__label">المتبقّي على الموظفين
                    <i class="feather icon-help-circle stk-tip" title="مجموع ما لم يُسدَّد من السلف المصروفة (الطلبات) في نطاقك"></i></span>
                <span class="stk-metric__value">{{ \App\Support\CurrencySettings::format($statistics['open_amount']) }}</span>
            </div>
        </div>

        <div class="card stk-filters">
            <div class="card-body">
                <div class="stk-filters__head">
                    <h6 class="stk-filters__title"><i class="feather icon-filter"></i> بحث وتصفية</h6>
                    @if (request()->hasAny(['stage', 'source', 'employee_id', 'date_from', 'date_to']))
                        <span class="stk-count">فلاتر مفعّلة</span>
                    @endif
                </div>

                <form action="{{ route('hr.advance-approvals.index') }}" method="GET">
                    <div class="row">
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="stage">المرحلة</label>
                                <select name="stage" id="stage" class="form-control hr-s2">
                                    <option value="">كل المراحل</option>
                                    @foreach (\App\Models\SalaryAdvanceRequest::STAGES as $key => $meta)
                                        <option value="{{ $key }}" @selected(request('stage') === $key)>{{ $meta[0] }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="employee_id">الموظف</label>
                                <select name="employee_id" id="employee_id" class="form-control select2">
                                    <option value="">كل الموظفين</option>
                                    @foreach ($employees as $employee)
                                        <option value="{{ $employee->id }}" @selected((string) request('employee_id') === (string) $employee->id)>
                                            {{ $employee->full_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2 col-sm-6">
                            <div class="form-group">
                                <label for="source">المصدر
                                    <i class="feather icon-help-circle stk-tip" title="«طلب»: نظام طلبات السلف. «قديمة»: سلف نظام الأقساط الأوّل."></i>
                                </label>
                                <select name="source" id="source" class="form-control hr-s2">
                                    <option value="">الكل</option>
                                    <option value="request" @selected(request('source') === 'request')>طلبات</option>
                                    <option value="legacy" @selected(request('source') === 'legacy')>قديمة</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2 col-sm-6">
                            <div class="form-group">
                                <label for="date_from">من تاريخ</label>
                                <input type="date" name="date_from" id="date_from" class="form-control" value="{{ request('date_from') }}">
                            </div>
                        </div>
                        <div class="col-md-2 col-sm-6">
                            <div class="form-group">
                                <label for="date_to">إلى تاريخ</label>
                                <input type="date" name="date_to" id="date_to" class="form-control" value="{{ request('date_to') }}">
                            </div>
                        </div>
                    </div>

                    <div class="stk-filters__foot">
                        <a href="{{ route('hr.advance-approvals.index') }}" class="btn btn-outline-secondary">
                            <i class="feather icon-rotate-ccw"></i> إعادة تعيين
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="feather icon-search"></i> بحث
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card stk-table-card">
            <div class="stk-table-head">
                <h6 class="stk-table-head__title">
                    <i class="feather icon-list"></i> السلف
                    <span class="stk-count">{{ $requests->total() }}</span>
                </h6>

                @include('stock::components.per-page', [
                    'sizes' => [20, 50, 100],
                    'current' => request('per_page', 20),
                    'onchange' => "window.location.href='" . route('hr.advance-approvals.index') . '?' . http_build_query(request()->except('per_page', 'page')) . "&per_page=' + this.value",
                ])
            </div>

            @if ($requests->count())
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>الموظف</th>
                                <th>التاريخ</th>
                                <th class="text-center">المبلغ</th>
                                <th class="text-center">المتبقّي</th>
                                <th>النوع</th>
                                <th class="text-center">المرحلة</th>
                                <th class="text-center" style="width:110px">إجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($requests as $row)
                                @php
                                    $isLegacy = $row->_source === 'legacy';
                                    $emp = $row->employee;
                                    $showUrl = $isLegacy ? route('ancestor.show', $row->id) : route('hr.advance-approvals.show', $row->id);
                                @endphp
                                <tr>
                                    <td>
                                        <span class="stk-num">#{{ $row->id }}</span>
                                        @if ($isLegacy)
                                            <span class="stk-tag" title="من نظام السلف القديم (أقساط مجدولة)">قديمة</span>
                                        @endif
                                    </td>
                                    <td>
                                        <strong>{{ $emp->full_name ?? '—' }}</strong>
                                        @if ($emp && ! $emp->employee_advance_account_id)
                                            <span class="stk-help text-warning"><i class="feather icon-alert-triangle"></i> لا حساب سلف</span>
                                        @endif
                                    </td>
                                    <td>{{ $row->requested_date ? \Carbon\Carbon::parse($row->requested_date)->format('Y-m-d') : '—' }}</td>
                                    <td class="text-center"><strong>{{ \App\Support\CurrencySettings::format($row->amount) }}</strong></td>
                                    <td class="text-center">
                                        @if (in_array($row->stage, ['disbursed', 'repaying', 'settled'], true))
                                            {{ \App\Support\CurrencySettings::format($row->remaining) }}
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($row->advance_type === 'scheduled')
                                            مجدولة <span class="text-muted stk-num">{{ $row->paid_installments }}/{{ $row->installments_count }}</span>
                                        @else
                                            عادية
                                        @endif
                                    </td>
                                    <td class="text-center"><span class="stk-state {{ $row->stage_class }}">{{ $row->stage_label }}</span></td>
                                    <td class="text-center">
                                        @php
                                            // الإجراءاتُ كلُّها تمرّ بشاشة العرض (`loans.show`) — بلاها لا قائمة
                                            $rowCanShow = $loanCan['show'];
                                            $rowCanApprove = $rowCanShow && ! $isLegacy && $row->stage === 'pending' && $loanCan['approve'];
                                            $rowCanDisburse = $rowCanShow && ! $isLegacy && $row->stage === 'approved' && $loanCan['disburse'];
                                            $rowCanPay = $isLegacy && in_array($row->stage, ['disbursed', 'repaying'], true) && $loanCan['settle'];
                                        @endphp
                                        @if ($rowCanShow || $rowCanPay)
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false" aria-label="خيارات">
                                                <i class="fa fa-ellipsis-v"></i>
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-end">
                                                @if ($rowCanShow)
                                                    <a class="dropdown-item" href="{{ $showUrl }}"><i class="fa fa-eye me-2 text-primary"></i>عرض</a>
                                                @endif
                                                @if ($rowCanApprove)
                                                    <a class="dropdown-item" href="{{ $showUrl }}"><i class="fa fa-check-square me-2 text-success"></i>مراجعة واعتماد</a>
                                                @elseif ($rowCanDisburse)
                                                    <a class="dropdown-item" href="{{ $showUrl }}"><i class="fa fa-money-bill-wave me-2 text-success"></i>صرف السلفة</a>
                                                @endif
                                                @if ($rowCanPay)
                                                    <a class="dropdown-item" href="{{ route('ancestor.pay', $row->id) }}"><i class="fa fa-hand-holding-usd me-2 text-info"></i>تحصيل قسط</a>
                                                @endif
                                            </div>
                                        </div>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @include('stock::components.table-foot', ['paginator' => $requests, 'unit' => 'سلفة'])
            @else
                <div class="stk-empty">
                    <div class="stk-empty__icon"><i class="feather icon-inbox"></i></div>
                    <h6 class="stk-empty__title">لا توجد سلف</h6>
                    <p class="stk-empty__hint">
                        @if (request()->hasAny(['stage', 'source', 'employee_id', 'date_from', 'date_to']))
                            لا نتائج بهذه الفلاتر — جرّب إعادة التعيين.
                        @else
                            طلباتُ الموظفين والسلفُ الصادرة تظهر هنا.
                        @endif
                    </p>
                </div>
            @endif
        </div>
    </div>

    @include('hr::components.table-menu')
@endsection

@push('scripts')
    <script>
        $(function () {
            if ($.fn.select2) {
                $('#employee_id').select2({ dir: 'rtl', width: '100%', allowClear: true, placeholder: 'كل الموظفين' });
            }
        });
    </script>
@endpush
