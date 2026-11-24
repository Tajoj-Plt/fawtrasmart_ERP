@extends('hr::master')

@section('title', 'تفاصيل العمولة')

@section('content')
    @php
        $money = fn ($v) => \App\Support\CurrencySettings::format($v);
        $target = $commission->salesTarget;
        $isWeekly = optional($target)->commission_period === 'weekly';
        $paidAt = $commission->direct_paid_at ?? $commission->settled_date;
        $pending = ! $commission->is_settled && ! $commission->paid_directly && $journal && (int) $journal->status !== 1;

        // الشريحةُ التي صُرفت بها: العمولة = المحقق × نسبة الشريحة، فالمحققُ يُستخرج منها
        $targetAmount = (float) ($assignment->target_amount ?? optional($target)->target_value ?? 0);
        $reached = null;
        $achieved = null;
        foreach ($tiers->sortByDesc('achievement_percentage') as $tier) {
            if ((float) $tier->commission_percentage <= 0) {
                continue;
            }
            $candidate = (float) $commission->commission_amount / ((float) $tier->commission_percentage / 100);
            $pct = $targetAmount > 0 ? $candidate / $targetAmount * 100 : 0;
            if ($pct + 0.0001 >= (float) $tier->achievement_percentage) {
                $reached = $tier;
                $achieved = $candidate;
                break;
            }
        }
        if (! $reached && $tiers->isEmpty() && optional($target)->commission_percentage > 0) {
            $achieved = (float) $commission->commission_amount / ((float) $target->commission_percentage / 100);
        }
        $achievedPct = ($achieved !== null && $targetAmount > 0) ? $achieved / $targetAmount * 100 : null;
    @endphp

    @section('page-actions')
        @php
            $commissionPrintRoute = collect(['hr.commissions.print', 'hr.commission.print', 'hr.commissions-report.print'])
                ->first(fn ($r) => \Illuminate\Support\Facades\Route::has($r));
            $showExpense = $expense && hasPermission('account.expenses.view');
            $showJournal = $journal && hasPermission('account.journal.view');
            $showTargets = hasPermission('sales.targets.settle');
        @endphp
        {{-- قالبُ سند العمولة يقرأ تسويةَ أهداف المبيعات وحدها --}}
        @if (($kind ?? 'sales') === 'sales' && $commissionPrintRoute && hasPermission('commissions.print'))
            <a href="{{ route($commissionPrintRoute, $commission->id) }}" target="_blank" class="btn btn-outline-primary">
                <i class="fas fa-print"></i> طباعة
            </a>
        @endif
        @if ($showExpense || $showJournal || $showTargets)
            <div class="btn-group">
                <button type="button" class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-ellipsis-h"></i> المزيد
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    @if ($showExpense)
                        <li><a class="dropdown-item" href="{{ route('expenses.show', $expense->id) }}" target="_blank"><i class="fas fa-file-invoice-dollar me-2 text-success"></i>سند الصرف {{ $expense->code }}</a></li>
                    @endif
                    @if ($showJournal)
                        <li><a class="dropdown-item" href="{{ route('journal.show', $journal->id) }}" target="_blank"><i class="fas fa-book me-2 text-info"></i>القيد #{{ $journal->id }}</a></li>
                    @endif
                    @if ($showTargets)
                        <li><a class="dropdown-item" href="{{ route(($kind ?? 'sales') === 'collections' ? 'collections-targets.index' : 'sales-targets.index') }}"><i class="fas fa-bullseye me-2 text-primary"></i>{{ ($kind ?? 'sales') === 'collections' ? 'أهداف التحصيلات' : 'أهداف المبيعات' }} وتسويتها</a></li>
                    @endif
                </ul>
            </div>
        @endif
    @endsection

    @include('stock::components.page-head', [
        'title' => 'عمولة #' . $commission->id . ' — ' . ($employee->full_name ?? ''),
        'icon' => 'percent',
        'crumbs' => [
            'الرئيسية' => route('hr.department.dashboard'),
            'سجل العمولات' => route('hr.commissions-report.index', ['kind' => $kind ?? 'sales']),
            'تفاصيل' => null,
        ],
    ])

    <div class="content-body">
        <div class="stk-metrics">
            <div class="stk-metric stk-metric--success">
                <span class="stk-metric__icon"><i class="feather icon-dollar-sign"></i></span>
                <span class="stk-metric__label">مبلغ العمولة</span>
                <span class="stk-metric__value">{{ $money($commission->commission_amount) }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-target"></i></span>
                <span class="stk-metric__label">هدف الموظف</span>
                <span class="stk-metric__value">{{ $targetAmount > 0 ? $money($targetAmount) : '—' }}</span>
            </div>
            <div class="stk-metric stk-metric--info">
                <span class="stk-metric__icon"><i class="feather icon-trending-up"></i></span>
                <span class="stk-metric__label">المحقَّق
                    <i class="feather icon-help-circle stk-tip" title="مستخرجٌ من العمولة ونسبة الشريحة التي صُرفت بها — والرقمُ الأصليّ في بيان سند الصرف أدناه."></i></span>
                <span class="stk-metric__value">{{ $achieved !== null ? $money($achieved) : '—' }}</span>
            </div>
            <div class="stk-metric stk-metric--warning">
                <span class="stk-metric__icon"><i class="feather icon-percent"></i></span>
                <span class="stk-metric__label">نسبة الإنجاز</span>
                <span class="stk-metric__value stk-num">{{ $achievedPct !== null ? number_format($achievedPct, 1).'%' : '—' }}</span>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-6">
                <div class="card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-dollar-sign"></i> العمولة وصرفها</h6>
                        @if ($pending)
                            <span class="stk-state stk-state--warn">بانتظار اعتماد القيد</span>
                        @else
                            <span class="stk-state stk-state--on">{{ $commission->paid_directly ? 'صرف مباشر' : 'مصروفة' }}</span>
                        @endif
                    </div>
                    <div class="card-body">
                        <dl class="stk-def">
                            <div class="stk-def__row"><dt>الهدف</dt><dd>{{ $target->name ?? '—' }} <span class="text-muted">({{ $isWeekly ? 'أسبوعية' : 'شهرية' }})</span></dd></div>
                            <div class="stk-def__row"><dt>الفترة</dt><dd>{{ optional($commission->from_date)->format('Y-m-d') }} ← {{ optional($commission->to_date)->format('Y-m-d') }}</dd></div>
                            <div class="stk-def__row"><dt>تاريخ الصرف</dt><dd>{{ $paidAt ? \Carbon\Carbon::parse($paidAt)->format('Y-m-d H:i') : '—' }}</dd></div>
                            <div class="stk-def__row"><dt>بواسطة</dt><dd>{{ $commission->settler->name ?? '—' }}</dd></div>
                            <div class="stk-def__row">
                                <dt>الشريحة</dt>
                                <dd>
                                    @if ($reached)
                                        {{ rtrim(rtrim(number_format($reached->achievement_percentage, 2), '0'), '.') }}% فأكثر ← {{ rtrim(rtrim(number_format($reached->commission_percentage, 2), '0'), '.') }}% من المحقَّق
                                    @elseif ($tiers->isEmpty() && optional($target)->commission_percentage > 0)
                                        نسبة ثابتة {{ $target->commission_percentage }}% من المحقَّق
                                    @else
                                        —
                                    @endif
                                </dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>سند الصرف</dt>
                                <dd>
                                    @if ($expense)
                                        @permission('account.expenses.view')
                                            <a href="{{ route('expenses.show', $expense->id) }}" target="_blank" class="stk-num">{{ $expense->code }}</a>
                                        @else
                                            <span class="stk-num">{{ $expense->code }}</span>
                                        @endpermission
                                    @else
                                        —
                                    @endif
                                </dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>القيد</dt>
                                <dd>
                                    @if ($journal)
                                        @permission('account.journal.view')
                                            <a href="{{ route('journal.show', $journal->id) }}" target="_blank" class="stk-num">#{{ $journal->id }}</a>
                                        @else
                                            <span class="stk-num">#{{ $journal->id }}</span>
                                        @endpermission
                                        <span class="text-muted">— {{ (int) $journal->status === 1 ? 'معتمد' : 'بانتظار الاعتماد' }}</span>
                                    @else
                                        —
                                    @endif
                                </dd>
                            </div>
                            @if ($commission->notes)
                                <div class="stk-def__row"><dt>ملاحظات</dt><dd>{{ $commission->notes }}</dd></div>
                            @endif
                        </dl>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-user"></i> الموظف</h6>
                    </div>
                    <div class="card-body">
                        @if ($employee)
                            <dl class="stk-def">
                                <div class="stk-def__row"><dt>الاسم</dt><dd>{{ $employee->full_name }}</dd></div>
                                <div class="stk-def__row"><dt>الرقم الوظيفي</dt><dd class="stk-num">{{ $employee->employee_code ?? ($employee->job_number ?? '—') }}</dd></div>
                                <div class="stk-def__row"><dt>القسم</dt><dd>{{ $employee->department->name ?? '—' }}</dd></div>
                            </dl>
                        @else
                            <div class="stk-empty">
                                <div class="stk-empty__icon"><i class="feather icon-user-x"></i></div>
                                <h6 class="stk-empty__title">بيانات الموظف غير متوفرة</h6>
                            </div>
                        @endif
                    </div>
                </div>

                @if ($tiers->isNotEmpty())
                    <div class="card stk-table-card">
                        <div class="stk-table-head">
                            <h6 class="stk-table-head__title"><i class="feather icon-layers"></i> شرائح الهدف</h6>
                        </div>
                        <div class="table-responsive">
                            <table class="table mb-0">
                                <thead>
                                    <tr>
                                        <th>نسبة الإنجاز</th>
                                        <th>نسبة العمولة</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($tiers as $tier)
                                        <tr class="{{ $reached && $reached->id === $tier->id ? 'table-success' : '' }}">
                                            <td>{{ $tier->achievement_percentage }}% فأكثر</td>
                                            <td>{{ $tier->commission_percentage }}%</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        @if ($journal)
            <div class="card stk-table-card">
                <div class="stk-table-head">
                    <h6 class="stk-table-head__title"><i class="feather icon-book-open"></i> قيد الصرف</h6>
                </div>
                <div class="card-body pb-0">
                    <p class="stk-help mt-0">{{ $journal->description }}</p>
                </div>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>الحساب</th>
                                <th class="text-center">مدين</th>
                                <th class="text-center">دائن</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($journal->details as $line)
                                <tr>
                                    <td>{{ $line->account->name ?? '—' }}</td>
                                    <td class="text-center">{{ (float) $line->debit > 0 ? $money($line->debit) : '—' }}</td>
                                    <td class="text-center">{{ (float) $line->credit > 0 ? $money($line->credit) : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
@endsection
