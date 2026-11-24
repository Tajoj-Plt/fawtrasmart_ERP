{{--
    جدولُ المسيرات. العدّاداتُ (`members_count`، `slips_count`،
    `running_slips_count`، `slips_net`) تأتي من الاستعلام نفسِه مقيَّدةً بنطاق
    المستخدم — لا استعلامين لكلّ صفّ كما كان.
--}}
@php
    $ppStates = [
        'active' => ['on', 'نشط'],
        'draft' => ['warn', 'مسودة'],
        'inactive' => ['off', 'موقوف'],
    ];
    $ppCycles = [1 => 'شهري', 2 => 'أسبوعي', 3 => 'سنوي', 4 => 'ربع سنوي', 5 => 'كل أسبوعين'];
    $ppCanShow = hasPermission('payroll.show');
    $ppCanEdit = hasPermission('payroll.edit');
    $ppCanDelete = hasPermission('payroll.destroy');
@endphp
<div class="table-responsive">
    <table class="table table-hover mb-0">
        <thead>
            <tr>
                <th>المسير</th>
                <th>الفترة</th>
                <th>النطاق</th>
                <th class="text-center">الموظفون</th>
                <th class="text-center">القسائم <i class="feather icon-help-circle stk-tip" title="القسائم السارية (معتمدة/نشطة) من إجمالي قسائم المسير"></i></th>
                <th class="text-end">صافي القسائم</th>
                <th class="text-center">الحالة</th>
                <th class="text-center" style="width:70px">إجراءات</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($payrolls as $payroll)
                @php
                    $state = $ppStates[$payroll->status ?? 'active'] ?? ['neutral', $payroll->status_label];
                    $scope = (int) $payroll->select_emp_role === 2
                        ? 'موظفون محدَّدون'
                        : implode(' · ', [
                            $payroll->branch->name ?? 'كل الفروع',
                            $payroll->department->name ?? 'كل الأقسام',
                            $payroll->jobTitle->name ?? 'كل المسميات',
                        ]);
                @endphp
                <tr>
                    <td>
                        <span class="stk-media__body">
                            @if ($ppCanShow)
                                <a href="{{ route('PayrollProcess.show', $payroll->id) }}" class="stk-media__title">{{ $payroll->name }}</a>
                            @else
                                <span class="stk-media__title">{{ $payroll->name }}</span>
                            @endif
                            <span class="stk-media__sub">
                                #{{ $payroll->id }}
                                @if ($payroll->receiving_cycle && isset($ppCycles[$payroll->receiving_cycle]))
                                    · {{ $ppCycles[$payroll->receiving_cycle] }}
                                @endif
                            </span>
                        </span>
                    </td>
                    <td class="stk-num">
                        @if ($payroll->start_date || $payroll->end_date)
                            {{ optional($payroll->start_date)->format('Y-m-d') ?? '—' }}
                            <i class="feather icon-arrow-left mx-1 text-muted small"></i>
                            {{ optional($payroll->end_date)->format('Y-m-d') ?? 'مفتوح' }}
                        @else
                            <span class="text-muted">مفتوح</span>
                        @endif
                    </td>
                    <td><small>{{ $scope }}</small></td>
                    <td class="text-center stk-num">{{ (int) $payroll->members_count }}</td>
                    <td class="text-center">
                        @if ((int) $payroll->slips_count === 0)
                            <span class="stk-state stk-state--off">لا توجد</span>
                        @else
                            <span class="stk-state stk-state--neutral stk-num">{{ (int) $payroll->running_slips_count }} / {{ (int) $payroll->slips_count }}</span>
                        @endif
                    </td>
                    <td class="text-end stk-num">{{ \App\Support\CurrencySettings::format((float) $payroll->slips_net) }}</td>
                    <td class="text-center">
                        <span class="stk-state stk-state--{{ $state[0] }}">{{ $state[1] }}</span>
                    </td>
                    <td class="text-center">
                        @php $ppRowDelete = $ppCanDelete && (int) $payroll->slips_count === 0; @endphp
                        @if ($ppCanShow || $ppCanEdit || $ppRowDelete)
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-secondary" type="button"
                                data-bs-toggle="dropdown" data-bs-display="static"
                                aria-expanded="false" aria-label="خيارات">
                                <i class="fa fa-ellipsis-v"></i>
                            </button>
                            <div class="dropdown-menu dropdown-menu-end">
                                @if ($ppCanShow)
                                    <a class="dropdown-item" href="{{ route('PayrollProcess.show', $payroll->id) }}">
                                        <i class="fa fa-eye me-2 text-primary"></i>عرض
                                    </a>
                                @endif
                                @if ($ppCanEdit)
                                    <a class="dropdown-item" href="{{ route('PayrollProcess.edit', $payroll->id) }}">
                                        <i class="fa fa-edit me-2 text-success"></i>تعديل
                                    </a>
                                @endif
                                @if ($ppRowDelete)
                                    @if ($ppCanShow || $ppCanEdit)
                                        <div class="dropdown-divider"></div>
                                    @endif
                                    <a class="dropdown-item text-danger" href="#" data-toggle="modal" data-bs-toggle="modal"
                                        data-target="#delModal{{ $payroll->id }}" data-bs-target="#delModal{{ $payroll->id }}">
                                        <i class="fa fa-trash me-2"></i>حذف
                                    </a>
                                @endif
                            </div>
                        </div>
                        @endif

                        @if ($ppRowDelete)
                            <div class="modal fade" id="delModal{{ $payroll->id }}" tabindex="-1" role="dialog" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered" role="document">
                                    <div class="modal-content text-right">
                                        <div class="modal-header">
                                            <h5 class="modal-title">حذف مسير الرواتب</h5>
                                            <button type="button" class="close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="إغلاق"><span aria-hidden="true">&times;</span></button>
                                        </div>
                                        <div class="modal-body">
                                            <p class="mb-1">سيُحذف المسير <strong>{{ $payroll->name }}</strong> ويُفكّ ارتباطه بموظفيه.</p>
                                            <p class="stk-empty__hint mb-0">لا يمكن التراجع عن هذا الإجراء.</p>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-outline-secondary" data-dismiss="modal" data-bs-dismiss="modal">إلغاء</button>
                                            <form method="POST" action="{{ route('PayrollProcess.destroy', $payroll->id) }}" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-danger"><i class="feather icon-trash-2"></i> حذف نهائي</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
