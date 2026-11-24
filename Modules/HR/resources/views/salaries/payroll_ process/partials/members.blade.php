{{--
    جدولُ موظفي المسير — يُستعمل لتبويبَي «الموظفون» و«بلا قسيمة».
    يتوقّع من الشاشة الأمّ: $payroll و$slipByEmployee و$slipStates و$slipLabels
    و$canViewSlips و$canCreateSlip.
--}}
@if ($rows->isNotEmpty())
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>الموظف</th>
                    <th>القسم</th>
                    <th>المسمى الوظيفي</th>
                    <th>الفرع</th>
                    <th class="text-center">قسيمته في المسير</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $emp)
                    @php $own = $slipByEmployee->get($emp->id, collect()); @endphp
                    <tr>
                        <td>
                            <span class="stk-media__body">
                                <span class="stk-media__title">{{ trim($emp->first_name.' '.$emp->middle_name.' '.$emp->nickname) }}</span>
                                <span class="stk-media__sub stk-num">{{ $emp->employee_code ?: '#'.$emp->id }}</span>
                            </span>
                        </td>
                        <td>{{ optional($emp->department)->name ?? '—' }}</td>
                        <td>{{ optional($emp->job_title)->name ?? '—' }}</td>
                        <td>{{ optional($emp->branch)->name ?? '—' }}</td>
                        <td class="text-center">
                            @forelse ($own as $s)
                                @if ($canViewSlips)
                                    <a href="{{ route('salarySlip.show', $s->id) }}" class="stk-state stk-state--{{ $slipStates[$s->status] ?? 'neutral' }}">{{ $s->code ?: '#'.$s->id }} · {{ $slipLabels[$s->status] ?? $s->status }}</a>
                                @else
                                    <span class="stk-state stk-state--{{ $slipStates[$s->status] ?? 'neutral' }}">{{ $slipLabels[$s->status] ?? $s->status }}</span>
                                @endif
                            @empty
                                @if ($canCreateSlip)
                                    <a href="{{ route('salarySlip.create', ['payroll_id' => $payroll->id, 'employee_id' => $emp->id]) }}" class="btn btn-sm btn-outline-primary">
                                        <i class="feather icon-plus"></i> إنشاء قسيمة
                                    </a>
                                @else
                                    <span class="stk-state stk-state--off">لا توجد</span>
                                @endif
                            @endforelse
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@else
    <div class="card-body">
        <div class="stk-empty">
            <div class="stk-empty__icon"><i class="feather icon-users"></i></div>
            <h5 class="stk-empty__title">لا يوجد موظفون هنا</h5>
        </div>
    </div>
@endif
