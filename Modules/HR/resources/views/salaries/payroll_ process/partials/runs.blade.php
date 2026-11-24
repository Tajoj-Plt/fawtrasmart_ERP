{{--
    صرفاتُ المسير — المعلّقةُ أوّلاً. «صرف» لما حلّ يومُ صرفه، و«صرف مبكر» بتأكيدٍ
    صريح لما لم يحلّ، وكلاهما إلى `payroll-runs.disburse` نفسِه ثمّ العودةُ إلى هنا.
    متغيّراتٌ من الشاشة الأم: $runs، $canPayRuns، $canViewRuns، $fmt، $pStatus.
--}}
@if ($runs->isNotEmpty())
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>الرقم</th>
                    <th>الموظف</th>
                    <th>الفترة</th>
                    <th>يوم الصرف</th>
                    <th class="text-end">الصافي</th>
                    <th class="text-center">المرحلة</th>
                    <th class="text-center" style="width:80px">إجراءات</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($runs as $run)
                    @php
                        $runEmp = $run->employee;
                        $payNow = $canPayRuns && $run->isPending() && $run->canBeDisbursed();
                        $payEarly = $canPayRuns && ! $payNow && $run->isPending() && $run->canBeDisbursedEarly();
                        $runBlock = $run->isPending() && ! $payNow && ! $payEarly ? $run->disbursementBlockReason() : null;
                    @endphp
                    <tr>
                        <td class="stk-num">{{ $run->code ?: '#'.$run->id }}</td>
                        <td>{{ $runEmp ? trim($runEmp->first_name.' '.$runEmp->middle_name) : '#'.$run->employee_id }}</td>
                        <td class="stk-num"><small>{{ $run->period_label }}</small></td>
                        <td class="stk-num">{{ optional($run->scheduled_date)->format('Y-m-d') ?? '—' }}</td>
                        <td class="text-end stk-num">
                            @if ($run->isDisbursed())
                                {{ $fmt($run->net_salary) }}
                            @elseif ($run->slip)
                                <span class="text-muted" title="صافي القسيمة — يُعاد احتسابه لحظة الصرف">{{ $fmt($run->slip->net_salary) }}</span>
                            @else
                                —
                            @endif
                        </td>
                        <td class="text-center">
                            <span class="stk-state stk-state--{{ $run->stage_tone }}">{{ $run->stage_label }}</span>
                            @if ($runBlock)
                                <i class="feather icon-info stk-tip" title="{{ $runBlock }}"></i>
                            @endif
                        </td>
                        <td class="text-center">
                            @if ($canViewRuns || $payNow || $payEarly)
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary" type="button"
                                        data-bs-toggle="dropdown" data-bs-display="static"
                                        aria-expanded="false" aria-label="خيارات">
                                        <i class="fa fa-ellipsis-v"></i>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        @if ($canViewRuns)
                                            <a class="dropdown-item" href="{{ route('payroll-runs.show', $run->id) }}">
                                                <i class="fa fa-eye me-2 text-primary"></i>عرض سجل الصرف
                                            </a>
                                        @endif
                                        @if ($payNow)
                                            <form method="POST" action="{{ route('payroll-runs.disburse', $run->id) }}"
                                                  onsubmit="return confirm('صرف الراتب للفترة {{ $run->period_from->format('Y-m') }} من الخزينة الافتراضية؟');">
                                                @csrf
                                                <input type="hidden" name="back" value="1">
                                                <button type="submit" class="dropdown-item"><i class="fas fa-money-bill-wave me-2 text-success"></i>صرف</button>
                                            </form>
                                        @elseif ($payEarly)
                                            <form method="POST" action="{{ route('payroll-runs.disburse', $run->id) }}"
                                                  onsubmit="return confirm('صرف مبكر: يوم صرف هذه الصرفة {{ optional($run->scheduled_date)->format('Y-m-d') }} لم يحل بعد. تأكيد الصرف الآن؟');">
                                                @csrf
                                                <input type="hidden" name="back" value="1">
                                                <input type="hidden" name="early" value="1">
                                                <button type="submit" class="dropdown-item"><i class="fas fa-forward me-2 text-warning"></i>صرف مبكر</button>
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
@else
    <div class="card-body">
        <div class="stk-empty">
            <div class="stk-empty__icon"><i class="fas fa-money-bill-wave"></i></div>
            <h5 class="stk-empty__title">لا صرفات في هذا المسير بعد</h5>
            <p class="stk-empty__hint">
                {{ $pStatus !== 'active'
                    ? 'اعتمد المسير: تُنشأ صرفات الفترة الجارية لقسائمه المعتمدة.'
                    : 'تُنشأ الصرفات لقسائم المسير المعتمدة — من «المزيد ← تحديث صرفات الفترة» أو تلقائياً.' }}
            </p>
        </div>
    </div>
@endif
