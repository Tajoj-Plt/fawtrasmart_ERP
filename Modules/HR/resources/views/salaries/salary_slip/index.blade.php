@extends('hr::master')

@section('title', 'قسائم الرواتب')

@php
    $slipStates = [
        \App\Models\SalarySlip::STATUS_DRAFT => 'warn',
        \App\Models\SalarySlip::STATUS_APPROVED => 'neutral',
        \App\Models\SalarySlip::STATUS_ACTIVE => 'on',
        \App\Models\SalarySlip::STATUS_STOPPED => 'off',
        \App\Models\SalarySlip::STATUS_EXPIRED => 'off',
    ];
    $fmt = fn ($v) => \App\Support\CurrencySettings::format((float) $v);
    $hasFilters = collect(['status', 'employee_id', 'payroll_id', 'search'])->contains(fn ($k) => filled(request($k)));
    $canPrint = hasPermission('payslips.print');
    $ssCanShow = hasPermission('payslips.show');
    $ssCanEdit = hasPermission('payslips.edit');
    $ssCanApprove = hasPermission('payslips.approve');
    $ssCanDelete = hasPermission('payslips.destroy');
@endphp

@section('content')
    <div class="content-body">

        @section('page-actions')
            @permission('payslips.create')
                <a href="{{ route('salarySlip.create') }}" class="btn btn-primary">
                    <i class="fas fa-plus"></i> قسيمة جديدة
                </a>
            @endpermission
        @endsection

        @include('stock::components.page-head', [
            'title' => 'قسائم الرواتب',
            'icon' => 'file-text',
            'sub' => 'اتفاقيات الراتب المستمرة — كل قسيمة تولّد صرفةً في كل دورة',
            'crumbs' => ['الرئيسية' => url('/'), 'قسائم الرواتب' => null],
        ])

        {{-- ══════════ إرشادُ الشاشة ══════════ --}}
        @include('hr::components.guide', [
            'key' => 'hr-salary-slips.index',
            'title' => 'ما هذه الشاشة، وما الذي يترتّب على ما فيها؟',
            'lead' => 'هذه قسائمُ الرواتب. والقسيمةُ قالبٌ دوريّ لا صرفةٌ واحدة: منها تتولّد صرفاتُ الراتب في مواعيدها، والصرفُ وحده هو ما يُحدث أثراً محاسبياً.',
            'blocks' => [
                'القسيمةُ والصرفة' => [
                    'حفظُ القسيمة لا يصرف مالاً ولا يُنشئ قيداً: يضبط بنودَ الراتب ومقدارَه وموعدَه، وتُشتقّ منه صرفةٌ لكلّ دورة.',
                    'ويُسجَّل مُنشئُ القسيمة، وقد يُشترَط اعتمادُ شخصٍ ثانٍ غيره قبل أن تصير نافذة.',
                ],
                'ما يترتّب على الصرف' => [
                    'الصرفُ يُنشئ **سندَ صرفٍ وقيداً محاسبياً** ويحرّك الخزينة — فهو أثرٌ ماليٌّ كامل لا حالةٌ تُقلَب.',
                    'وما صُرف لا يُحذف: يُعكَس بقيدٍ مضادٍّ بسببٍ مكتوب.',
                ],
                'ما يُقتطَع تلقائياً' => [
                    'السلفُ المعتمدةُ والاستقطاعاتُ وحسمُ الغياب تُخصَم من الصافي في الصرفة، فلا تُدخَل يدوياً في القسيمة.',
                ],
            ],
            'facts' => [
                'الحفظُ يضبط، والصرفُ يُنشئ سنداً وقيداً',
                'المصروفُ يُعكَس ولا يُحذف',
            ],
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        <div class="card">
            <div class="stk-metrics">
                <div class="stk-metric">
                    <span class="stk-metric__icon"><i class="feather icon-list"></i></span>
                    <span class="stk-metric__value">{{ $statistics['total'] }}</span>
                    <span class="stk-metric__label">إجمالي القسائم</span>
                </div>
                <div class="stk-metric stk-metric--success">
                    <span class="stk-metric__icon"><i class="feather icon-check-circle"></i></span>
                    <span class="stk-metric__value">{{ $statistics['active'] }}</span>
                    <span class="stk-metric__label">قسائم سارية</span>
                </div>
                <div class="stk-metric stk-metric--warning">
                    <span class="stk-metric__icon"><i class="fa fa-hourglass-half"></i></span>
                    <span class="stk-metric__value">{{ $statistics['pending_runs'] }}</span>
                    <span class="stk-metric__label">صرفات معلّقة</span>
                </div>
                <div class="stk-metric stk-metric--info">
                    <span class="stk-metric__icon"><i class="fa fa-money-check-alt"></i></span>
                    <span class="stk-metric__value stk-num">{{ $fmt($statistics['this_month_disbursed']) }}</span>
                    <span class="stk-metric__label">صافي مصروف هذا الشهر</span>
                </div>
            </div>
        </div>

        <div class="card stk-filters">
            <div class="card-body">
                <div class="stk-filters__head">
                    <h6 class="stk-filters__title"><i class="feather icon-filter"></i> بحث وتصفية</h6>
                    @if ($hasFilters)
                        <span class="stk-count">فلاتر مفعّلة</span>
                    @endif
                </div>
                <form method="GET" action="{{ route('salarySlip.index') }}">
                    <div class="row">
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="f_search">بحث</label>
                                <input type="text" id="f_search" name="search" class="form-control" value="{{ request('search') }}" placeholder="اسم الموظف أو رقمه أو رقم القسيمة">
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="f_payroll">المسير</label>
                                <select id="f_payroll" name="payroll_id" class="form-control ps-s2" data-placeholder="الكل">
                                    <option value=""></option>
                                    @foreach ($payrolls as $p)
                                        <option value="{{ $p->id }}" {{ (string) request('payroll_id') === (string) $p->id ? 'selected' : '' }}>{{ $p->name }} (#{{ $p->id }})</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="f_employee">الموظف</label>
                                <select id="f_employee" name="employee_id" class="form-control ps-s2" data-placeholder="الكل">
                                    <option value=""></option>
                                    @foreach ($employees as $emp)
                                        <option value="{{ $emp->id }}" {{ (string) request('employee_id') === (string) $emp->id ? 'selected' : '' }}>{{ trim($emp->first_name.' '.$emp->middle_name) }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="f_status">الحالة</label>
                                <select id="f_status" name="status" class="form-control hr-s2">
                                    <option value="">الكل</option>
                                    @foreach ($statuses as $k => $v)
                                        <option value="{{ $k }}" {{ request('status') === $k ? 'selected' : '' }}>{{ $v }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="stk-filters__foot">
                        <a href="{{ route('salarySlip.index') }}" class="btn btn-outline-secondary"><i class="feather icon-rotate-ccw"></i> إعادة تعيين</a>
                        <button type="submit" class="btn btn-primary"><i class="feather icon-search"></i> بحث</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card stk-table-card">
            @if ($slips->count() > 0)
                <div class="stk-table-head">
                    <h6 class="stk-table-head__title">
                        <i class="feather icon-list"></i> القسائم
                        <span class="stk-count">{{ $slips->total() }}</span>
                    </h6>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>القسيمة</th>
                                <th>الموظف</th>
                                <th>المسير</th>
                                <th>الفترة</th>
                                <th class="text-end">الأساسي</th>
                                <th class="text-end">الإجمالي</th>
                                <th class="text-end">الصافي</th>
                                <th class="text-center">الصرف <i class="feather icon-help-circle stk-tip" title="الصرفات المصروفة من إجمالي صرفات القسيمة"></i></th>
                                <th class="text-center">الحالة</th>
                                <th class="text-center" style="width:70px">إجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($slips as $slip)
                                @php
                                    $emp = $slip->employee;
                                    $runsCount = $slip->payrollRuns->count();
                                    $disbursed = $slip->payrollRuns->where('status', 'disbursed')->count();
                                @endphp
                                <tr>
                                    <td class="stk-num">
                                        @if ($ssCanShow)
                                            <a href="{{ route('salarySlip.show', $slip->id) }}">{{ $slip->code ?: '#'.$slip->id }}</a>
                                        @else
                                            {{ $slip->code ?: '#'.$slip->id }}
                                        @endif
                                    </td>
                                    <td>
                                        <span class="stk-media__body">
                                            <span class="stk-media__title">{{ $emp ? trim($emp->first_name.' '.$emp->middle_name) : '—' }}</span>
                                            <span class="stk-media__sub">{{ implode(' · ', array_filter([optional(optional($emp)->department)->name, optional(optional($emp)->job_title)->name])) ?: '#'.$slip->employee_id }}</span>
                                        </span>
                                    </td>
                                    <td><small>{{ $slip->payroll->name ?? '—' }}</small></td>
                                    <td class="stk-num"><small>{{ optional($slip->start_date)->format('Y-m-d') ?? '—' }}<br>← {{ optional($slip->end_date)->format('Y-m-d') ?? 'مفتوحة' }}</small></td>
                                    <td class="text-end stk-num">{{ $fmt($slip->basic_amount) }}</td>
                                    <td class="text-end stk-num">{{ $fmt($slip->total_salary) }}</td>
                                    <td class="text-end stk-num"><strong>{{ $fmt($slip->net_salary) }}</strong></td>
                                    <td class="text-center stk-num"><small>{{ $disbursed }}/{{ $runsCount }}</small></td>
                                    <td class="text-center"><span class="stk-state stk-state--{{ $slipStates[$slip->status] ?? 'neutral' }}">{{ $slip->status_label }}</span></td>
                                    <td class="text-center">
                                        @php
                                            $rowEdit = $ssCanEdit && $slip->canBeEdited();
                                            $rowApprove = $ssCanApprove && $slip->canBeApproved();
                                            $rowDelete = $ssCanDelete && $slip->canBeDeleted();
                                        @endphp
                                        @if ($ssCanShow || $rowEdit || $rowApprove || $canPrint || $rowDelete)
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary" type="button"
                                                data-bs-toggle="dropdown" data-bs-display="static"
                                                aria-expanded="false" aria-label="خيارات">
                                                <i class="fa fa-ellipsis-v"></i>
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-end">
                                                @if ($ssCanShow)
                                                <a class="dropdown-item" href="{{ route('salarySlip.show', $slip->id) }}">
                                                    <i class="fa fa-eye me-2 text-primary"></i>عرض
                                                </a>
                                                @endif
                                                    @if ($rowEdit)
                                                        <a class="dropdown-item" href="{{ route('salarySlip.edit', $slip->id) }}">
                                                            <i class="fa fa-edit me-2 text-success"></i>تعديل
                                                        </a>
                                                    @endif
                                                    @if ($rowApprove)
                                                        <form method="POST" action="{{ route('salarySlip.approve', $slip->id) }}"
                                                              onsubmit="return confirm('اعتماد القسيمة؟ ستبدأ بتوليد رواتب شهرية تلقائياً.')">
                                                            @csrf
                                                            <button type="submit" class="dropdown-item"><i class="fa fa-check me-2 text-success"></i>اعتماد</button>
                                                        </form>
                                                    @endif
                                                @if ($canPrint)
                                                    <a class="dropdown-item" href="{{ route('salarySlip.print', $slip->id) }}" target="_blank">
                                                        <i class="fa fa-print me-2 text-info"></i>طباعة
                                                    </a>
                                                @endif
                                                @if ($rowDelete)
                                                    @if ($ssCanShow || $rowEdit || $rowApprove || $canPrint)
                                                        <div class="dropdown-divider"></div>
                                                    @endif
                                                    <form method="POST" action="{{ route('salarySlip.destroy', $slip->id) }}"
                                                          onsubmit="return confirm('حذف القسيمة وكل سجلاتها؟ لا يمكن التراجع.')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="dropdown-item text-danger"><i class="fa fa-trash me-2"></i>حذف</button>
                                                    </form>
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
                @if ($slips->hasPages())
                    <div class="stk-table-foot">
                        <p class="stk-table-foot__count">عرض {{ $slips->firstItem() }}–{{ $slips->lastItem() }} من {{ $slips->total() }}</p>
                        {{ $slips->links() }}
                    </div>
                @endif
            @else
                <div class="card-body">
                    <div class="stk-empty">
                        <div class="stk-empty__icon"><i class="feather icon-file-text"></i></div>
                        <h5 class="stk-empty__title">لا توجد قسائم</h5>
                        <p class="stk-empty__hint">{{ $hasFilters ? 'لم يطابق البحث أيّ قسيمة — جرّب معايير أوسع' : 'أنشئ قسيمةً لموظفٍ ضمن مسيرٍ نشط' }}</p>
                        @if ($hasFilters)
                            <a href="{{ route('salarySlip.index') }}" class="btn btn-outline-secondary"><i class="feather icon-rotate-ccw"></i> إزالة الفلاتر</a>
                        @else
                            @permission('payslips.create')
                                <a href="{{ route('salarySlip.create') }}" class="btn btn-primary"><i class="feather icon-plus"></i> قسيمة جديدة</a>
                            @endpermission
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
    @include('hr::components.table-menu')
    <script>
        $(function () {
            $('select.ps-s2').each(function () {
                if ($(this).hasClass('select2-hidden-accessible')) return;
                $(this).select2({ dir: 'rtl', width: '100%', allowClear: true, placeholder: $(this).data('placeholder') || 'الكل' });
            });
        });
    </script>
@endpush
