@extends('hr::master')

@section('title', 'سداد التأمينات الاجتماعية')

@section('content')
    @php
        $money = fn ($v) => \App\Support\CurrencySettings::format($v);
        // $showOverview / $showStatement / $canRemit من المتحكّم: كلُّ قسمٍ بصلاحيته، وأرقامُ المنشأة لنطاق كلِّ الموظفين وحده
        $totalOutstanding = round($employeeOutstanding + $employerOutstanding, 2);
        $stateLabels = ['paid' => ['on', 'مسدَّد'], 'partial' => ['warn', 'سداد جزئي'], 'unpaid' => ['off', 'غير مسدَّد']];
        $exportQuery = array_filter(['month' => $filters['month'], 'employee_id' => $filters['employee_id']]);
        $firstUnpaid = $months->firstWhere('state', '!=', 'paid');
    @endphp

    <div class="content-body">
        @include('stock::components.page-head', [
            'title' => 'سداد التأمينات الاجتماعية',
            'icon' => 'send',
            'sub' => 'ما حُجز من الرواتب لجهة التأمينات، وما سُدِّد منه، وكشفُ الاشتراكات لكلّ موظف',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'إعدادات الرواتب' => route('SalarySittings.index'),
                'سداد التأمينات' => null,
            ],
            'actions' => hasPermission('insurance.settings')
                ? '<a href="'.route('hr.gosi.settings').'" class="btn btn-outline-secondary"><i class="feather icon-settings"></i> إعدادات التأمينات</a>'
                : '',
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        @include('hr::components.guide', [
            'key' => 'hr-gosi.remittances',
            'title' => 'من أين يأتي المستحق، وما الذي يفعله السداد؟',
            'lead' => 'كلُّ صرفِ راتبٍ يحجز حصةَ الموظف ويُقيّد حصةَ المنشأة في ذمّتين مستحقّتين لجهة التأمينات. السدادُ هنا يُقفلهما نقداً من خزينة، بقيدٍ محاسبي وسند صرف.',
            'blocks' => [
                'الرصيدان أعلى الشاشة' => [
                    'رصيدا حسابَي الذمّة في دليل الحسابات — ما حُجز ولم يُسدَّد بعد، من كلّ الصرفات.',
                    'ولا يُسدَّد أكثرُ منهما: الزيادةُ تقلب الذمّةَ دَيناً على الجهة.',
                ],
                'موقف الأشهر والكشف' => [
                    'موقفُ الشهر يقارن ما حُجز في صرفات الشهر بما سُجّل من سدادٍ «عن» ذلك الشهر.',
                    'الكشفُ صفٌّ لكلّ صرفة راتبٍ فيها تأمينات، من الصرفات المصروفة فعلاً وغير المعكوسة — ضمن نطاق الموظفين المسموح لك برؤيتهم.',
                ],
                'القيد عند السداد' => [
                    'مدين ذمّة حصة الموظف + مدين ذمّة حصة المنشأة / دائن الخزينة، مع سند صرف يُربط بالسداد.',
                    'رصيدُ الخزينة يُحتسب من الدفتر تحت قفل، وحارسُ الفترة المحاسبية يمنع القيد في فترةٍ مقفلة.',
                ],
            ],
            'facts' => [
                'لا يُسدَّد أكثرُ مما حُجز',
                'كلُّ سدادٍ = قيد + سند صرف',
                'الكشفُ من الصرفات الفعلية',
            ],
        ])

        @if ($showOverview)
        {{-- ══════════ الرصيدان ══════════ --}}
            <div class="stk-metrics">
                <div class="stk-metric stk-metric--warning" title="{{ optional($employeeAccount)->name }}">
                    <span class="stk-metric__icon"><i class="feather icon-user"></i></span>
                    <span class="stk-metric__label">حصة الموظفين المحجوزة</span>
                    <span class="stk-metric__value">{{ $money($employeeOutstanding) }}</span>
                    <span class="stk-metric__note">{{ $employeeAccount ? $employeeAccount->code.' — '.$employeeAccount->name : 'الحساب غير مربوط' }}</span>
                </div>
                <div class="stk-metric stk-metric--warning" title="{{ optional($employerAccount)->name }}">
                    <span class="stk-metric__icon"><i class="feather icon-home"></i></span>
                    <span class="stk-metric__label">حصة المنشأة المستحقة</span>
                    <span class="stk-metric__value">{{ $money($employerOutstanding) }}</span>
                    <span class="stk-metric__note">{{ $employerAccount ? $employerAccount->code.' — '.$employerAccount->name : 'الحساب غير مربوط' }}</span>
                </div>
                <div class="stk-metric {{ $totalOutstanding > 0.009 ? 'stk-metric--danger' : 'stk-metric--success' }}">
                    <span class="stk-metric__icon"><i class="feather icon-{{ $totalOutstanding > 0.009 ? 'alert-circle' : 'check-circle' }}"></i></span>
                    <span class="stk-metric__label">إجمالي المستحق للسداد</span>
                    <span class="stk-metric__value">{{ $money($totalOutstanding) }}</span>
                    <span class="stk-metric__note">{{ ($gosiSettings['enabled'] ?? false) ? 'الاحتساب مفعّل' : 'الاحتساب معطّل في الإعدادات' }}</span>
                </div>
            </div>

        <div class="row">
            {{-- ══════════ تسجيل دفعة ══════════ --}}
            @if ($canRemit)
                <div class="col-lg-5 col-12">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title"><i class="feather icon-send"></i> تسجيل دفعة سداد</h4>
                        </div>
                        <form id="gosi_remit_form" action="{{ route('hr.gosi.remit') }}" method="POST">
                            @csrf
                            <div class="card-body">
                                @if ($errors->any())
                                    <div class="stk-form-errors alert alert-danger" role="alert">
                                        <div class="stk-form-errors__head"><i class="feather icon-alert-circle"></i> لم يُسجَّل السداد:</div>
                                        <ul class="stk-form-errors__list mb-0">
                                            @foreach ($errors->all() as $error)
                                                <li>{{ $error }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="employee_amount">
                                                حصة الموظفين
                                                <i class="feather icon-help-circle stk-tip" title="لا تتجاوز المحجوز: {{ number_format($employeeOutstanding, 2) }}"></i>
                                            </label>
                                            <input type="number" step="0.01" min="0" max="{{ number_format($employeeOutstanding, 2, '.', '') }}"
                                                   id="employee_amount" name="employee_amount"
                                                   class="form-control @error('employee_amount') is-invalid @enderror"
                                                   value="{{ old('employee_amount', number_format($employeeOutstanding, 2, '.', '')) }}">
                                            @error('employee_amount')
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="employer_amount">
                                                حصة المنشأة
                                                <i class="feather icon-help-circle stk-tip" title="لا تتجاوز المستحق: {{ number_format($employerOutstanding, 2) }}"></i>
                                            </label>
                                            <input type="number" step="0.01" min="0" max="{{ number_format($employerOutstanding, 2, '.', '') }}"
                                                   id="employer_amount" name="employer_amount"
                                                   class="form-control @error('employer_amount') is-invalid @enderror"
                                                   value="{{ old('employer_amount', number_format($employerOutstanding, 2, '.', '')) }}">
                                            @error('employer_amount')
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="period_month">
                                                عن شهر
                                                <i class="feather icon-help-circle stk-tip" title="به يُطابَق السداد بما حُجز في «موقف الأشهر»"></i>
                                            </label>
                                            <input type="month" id="period_month" name="period_month" required
                                                   class="form-control @error('period_month') is-invalid @enderror"
                                                   value="{{ old('period_month', optional($firstUnpaid)->period_month ?? now()->subMonth()->format('Y-m')) }}">
                                            @error('period_month')
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="treasury_id">خزينة السداد</label>
                                            <select name="treasury_id" id="treasury_id" required
                                                    class="form-control select2 @error('treasury_id') is-invalid @enderror">
                                                <option value="">اختر الخزينة</option>
                                                @foreach ($treasuries as $treasury)
                                                    <option value="{{ $treasury->id }}" {{ (string) old('treasury_id') === (string) $treasury->id ? 'selected' : '' }}>
                                                        {{ $treasury->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            @error('treasury_id')
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                            @if ($treasuries->isEmpty())
                                                <small class="stk-help text-danger">لا خزينة أو حساب بنكي متاح لك للصرف.</small>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="form-group">
                                            <label for="reference">رقم الحوالة / الإيصال</label>
                                            <input type="text" id="reference" name="reference" maxlength="191"
                                                   class="form-control @error('reference') is-invalid @enderror"
                                                   value="{{ old('reference') }}" placeholder="مرجع السداد لدى الجهة">
                                            @error('reference')
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="form-group mb-0">
                                            <label for="notes">ملاحظات</label>
                                            <input type="text" id="notes" name="notes" maxlength="500"
                                                   class="form-control @error('notes') is-invalid @enderror"
                                                   value="{{ old('notes') }}">
                                            @error('notes')
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="stk-form-foot">
                                <span class="stk-help mt-0 mr-auto ml-auto">الإجمالي: <strong id="remit_total">{{ $money($totalOutstanding) }}</strong></span>
                                <button type="submit" class="btn btn-success" {{ $totalOutstanding < 0.01 || $treasuries->isEmpty() ? 'disabled' : '' }}>
                                    <i class="feather icon-check"></i> تسجيل السداد
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif

            {{-- ══════════ موقف الأشهر ══════════ --}}
            <div class="{{ $canRemit ? 'col-lg-7' : 'col-12' }} col-12">
                <div class="card stk-table-card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title">
                            <i class="feather icon-calendar"></i> موقف الأشهر
                            <i class="feather icon-help-circle stk-tip" title="ما حُجز في صرفات كلّ شهر مقابل ما سُجّل من سدادٍ عن الشهر نفسه — آخر 12 شهراً"></i>
                        </h6>
                    </div>
                    @if ($months->isNotEmpty())
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>الشهر</th>
                                        <th class="text-center">موظفون</th>
                                        <th class="text-end">المحجوز</th>
                                        <th class="text-end">المسدَّد</th>
                                        <th class="text-end">المتبقي</th>
                                        <th class="text-center">الحالة</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($months as $m)
                                        <tr>
                                            <td>
                                                @if ($showStatement)
                                                    <a href="{{ route('hr.gosi.remittances', ['month' => $m->period_month]) }}#statement" class="stk-num">{{ $m->period_month }}</a>
                                                @else
                                                    <span class="stk-num">{{ $m->period_month }}</span>
                                                @endif
                                            </td>
                                            <td class="text-center stk-num">{{ $m->employees }}</td>
                                            <td class="text-end stk-num" title="الموظفون {{ number_format($m->employee_share, 2) }} + المنشأة {{ number_format($m->employer_share, 2) }}">{{ $money($m->due) }}</td>
                                            <td class="text-end stk-num">{{ $money($m->paid) }}</td>
                                            <td class="text-end stk-num {{ $m->remaining > 0.009 ? 'text-danger' : '' }}">{{ $money($m->remaining) }}</td>
                                            <td class="text-center">
                                                <span class="stk-state stk-state--{{ $stateLabels[$m->state][0] }}">{{ $stateLabels[$m->state][1] }}</span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="card-body">
                            <div class="stk-empty">
                                <div class="stk-empty__icon"><i class="feather icon-calendar"></i></div>
                                <h5 class="stk-empty__title">لا اشتراكات بعد</h5>
                                <p class="stk-empty__hint">تظهر الأشهر هنا بعد أول صرف راتبٍ تُحتسب فيه التأمينات</p>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
        @endif

        @if ($showStatement)
        {{-- ══════════ كشف الاشتراكات ══════════ --}}
        <div class="card stk-filters" id="statement">
            <div class="card-body">
                <div class="stk-filters__head">
                    <h6 class="stk-filters__title"><i class="feather icon-filter"></i> كشف الاشتراكات</h6>
                    @if ($filters['month'] || $filters['employee_id'])
                        <span class="stk-count">فلاتر مفعّلة</span>
                    @endif
                </div>
                <form method="GET" action="{{ route('hr.gosi.remittances') }}" data-no-ux>
                    <div class="row">
                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="filter_month">الشهر</label>
                                <input type="month" id="filter_month" name="month" class="form-control" value="{{ $filters['month'] }}">
                            </div>
                        </div>
                        <div class="col-md-5 col-sm-6">
                            <div class="form-group">
                                <label for="filter_employee">الموظف</label>
                                <select id="filter_employee" name="employee_id" class="form-control select2">
                                    <option value="">كل الموظفين</option>
                                    @foreach ($employees as $emp)
                                        <option value="{{ $emp->id }}" {{ (string) $filters['employee_id'] === (string) $emp->id ? 'selected' : '' }}>
                                            {{ trim($emp->first_name.' '.$emp->middle_name) }}{{ $emp->employee_code ? ' — '.$emp->employee_code : '' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="stk-filters__foot">
                        <a href="{{ route('hr.gosi.remittances') }}#statement" class="btn btn-outline-secondary">
                            <i class="feather icon-rotate-ccw"></i> إعادة تعيين
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="feather icon-search"></i> عرض
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card stk-table-card">
            <div class="stk-table-head">
                <h6 class="stk-table-head__title">
                    <i class="feather icon-users"></i> الاشتراكات حسب الموظف والصرفة
                    <span class="stk-count">{{ (int) ($statementTotals->runs ?? 0) }}</span>
                </h6>
                @if ((int) ($statementTotals->runs ?? 0) > 0)
                    <div class="stk-actions">
                        <a href="{{ route('hr.gosi.remittances', $exportQuery + ['export' => 'xlsx']) }}" class="btn btn-outline-success" title="تصدير Excel">
                            <i class="fas fa-file-excel"></i> Excel
                        </a>
                        <a href="{{ route('hr.gosi.remittances', $exportQuery + ['export' => 'pdf']) }}" class="btn btn-outline-danger" title="تصدير PDF">
                            <i class="fas fa-file-pdf"></i> PDF
                        </a>
                    </div>
                @endif
            </div>
            @if ($statementRows->count() > 0)
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0">
                        <thead>
                            <tr>
                                <th>الموظف</th>
                                <th>الفترة</th>
                                <th>الصرفة</th>
                                <th class="text-end">حصة الموظف</th>
                                <th class="text-end">حصة المنشأة</th>
                                <th class="text-end">الإجمالي</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($statementRows as $row)
                                <tr>
                                    <td>
                                        <span class="font-weight-bolder">{{ $row->employee_name ?: '—' }}</span>
                                        @if ($row->employee_code)
                                            <small class="stk-help mt-0">{{ $row->employee_code }}</small>
                                        @endif
                                    </td>
                                    <td class="stk-num">{{ $row->period_month }}</td>
                                    <td>
                                        @if (Route::has('payroll-runs.show') && hasPermission('payroll.show'))
                                            <a href="{{ route('payroll-runs.show', $row->run_id) }}">{{ $row->run_code ?: '#'.$row->run_id }}</a>
                                        @else
                                            {{ $row->run_code ?: '#'.$row->run_id }}
                                        @endif
                                        <small class="stk-help mt-0">{{ $row->disbursed_at ? substr((string) $row->disbursed_at, 0, 10) : '' }}</small>
                                    </td>
                                    <td class="text-end stk-num">{{ $money($row->employee_share) }}</td>
                                    <td class="text-end stk-num">{{ $money($row->employer_share) }}</td>
                                    <td class="text-end stk-num"><strong>{{ $money((float) $row->employee_share + (float) $row->employer_share) }}</strong></td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="stk-total-row">
                                <th colspan="3">الإجمالي ({{ (int) $statementTotals->employees }} موظف)</th>
                                <th class="text-end stk-num">{{ $money($statementTotals->employee_share) }}</th>
                                <th class="text-end stk-num">{{ $money($statementTotals->employer_share) }}</th>
                                <th class="text-end stk-num">{{ $money((float) $statementTotals->employee_share + (float) $statementTotals->employer_share) }}</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                @if ($statementRows->hasPages())
                    <div class="stk-table-foot">
                        <p class="stk-table-foot__count">عرض {{ $statementRows->firstItem() }}–{{ $statementRows->lastItem() }} من {{ $statementRows->total() }}</p>
                        {{ $statementRows->fragment('statement')->links() }}
                    </div>
                @endif
            @else
                <div class="card-body">
                    <div class="stk-empty">
                        <div class="stk-empty__icon"><i class="feather icon-users"></i></div>
                        <h5 class="stk-empty__title">لا اشتراكات مطابقة</h5>
                        <p class="stk-empty__hint">
                            @if ($filters['month'] || $filters['employee_id'])
                                لا صرفة رواتب فيها تأمينات بهذه المعايير
                            @else
                                تظهر هنا كلُّ صرفة راتبٍ حُجزت فيها التأمينات
                            @endif
                        </p>
                    </div>
                </div>
            @endif
        </div>
        @endif

        @if ($showOverview)
        {{-- ══════════ دفعات السداد ══════════ --}}
        <div class="card stk-table-card">
            <div class="stk-table-head">
                <h6 class="stk-table-head__title">
                    <i class="feather icon-list"></i> دفعات السداد
                    <span class="stk-count">{{ $remittances->total() }}</span>
                </h6>
            </div>
            @if ($remittances->count() > 0)
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>عن شهر</th>
                                <th class="text-end">حصة الموظفين</th>
                                <th class="text-end">حصة المنشأة</th>
                                <th class="text-end">الإجمالي</th>
                                <th>الخزينة</th>
                                <th>المرجع</th>
                                <th>القيد / السند</th>
                                <th>بواسطة</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($remittances as $r)
                                <tr>
                                    <td class="stk-num">{{ $r->id }}</td>
                                    <td class="stk-num">{{ $r->period_month ?? '—' }}</td>
                                    <td class="text-end stk-num">{{ $money($r->employee_amount) }}</td>
                                    <td class="text-end stk-num">{{ $money($r->employer_amount) }}</td>
                                    <td class="text-end stk-num"><strong>{{ $money($r->total_amount) }}</strong></td>
                                    <td>{{ optional($r->treasury)->name ?? '—' }}</td>
                                    <td>{{ $r->reference ?: '—' }}</td>
                                    <td>
                                        <span class="stk-num">{{ optional($r->journalEntry)->reference_number ?? '—' }}</span>
                                        @if ($r->expense)
                                            <small class="stk-help mt-0">سند {{ $r->expense->code }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        {{ optional($r->payer)->name ?? 'النظام' }}
                                        <small class="stk-help mt-0 stk-num">{{ optional($r->paid_at)->format('Y-m-d H:i') }}</small>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($remittances->hasPages())
                    <div class="stk-table-foot">
                        <p class="stk-table-foot__count">عرض {{ $remittances->firstItem() }}–{{ $remittances->lastItem() }} من {{ $remittances->total() }}</p>
                        {{ $remittances->links() }}
                    </div>
                @endif
            @else
                <div class="card-body">
                    <div class="stk-empty">
                        <div class="stk-empty__icon"><i class="feather icon-send"></i></div>
                        <h5 class="stk-empty__title">لا دفعات سداد بعد</h5>
                        <p class="stk-empty__hint">كلُّ سدادٍ يُسجَّل هنا مع قيده وسند صرفه</p>
                    </div>
                </div>
            @endif
        </div>
        @endif
    </div>
@endsection

@push('scripts')
    <script>
        $(function () {
            if ($.fn.select2) {
                $('#treasury_id, #filter_employee').select2({ width: '100%', dir: 'rtl' });
            }

            var fmt = new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            function refreshTotal() {
                var a = parseFloat($('#employee_amount').val()) || 0;
                var b = parseFloat($('#employer_amount').val()) || 0;
                $('#remit_total').text(fmt.format(a + b));
            }
            $('#employee_amount, #employer_amount').on('input', refreshTotal);
        });
    </script>
@endpush
