{{-- resources/views/hr/attendance/settings/employee_leave_balances/table_rows.blade.php --}}

@if(isset($balances) && !empty($balances) && $balances->count() > 0)
    @foreach($balances as $balance)
        <tr>
            {{-- لا قيدَ مرجعياً على employee_id/leave_type_id، فحذفُ موظفٍ أو نوع
                 إجازةٍ لا يمنعه شيء من ترك صفّ رصيدٍ يشير إلى صفٍّ لم يعد موجوداً. --}}
            <td>{{ $balance->employee->full_name ?? 'موظف محذوف' }} #{{ $balance->employee->id ?? $balance->employee_id }}</td>
            <td>
                <span class="badge" style="background-color: {{ $balance->leaveType->color ?? '#6c757d' }}; color: white;">
                    {{ $balance->leaveType->name ?? 'نوع إجازة محذوف' }}
                </span>
            </td>
            <td>{{ $balance->year }}</td>
            <td>{{ $balance->initial_balance }}</td>
            <td>{{ $balance->carried_forward }}</td>
            <td>{{ $balance->additional_balance }}</td>
            <td><strong>{{ $balance->getTotalAvailableBalance() }}</strong></td>
            <td>{{ $balance->used_balance }}</td>
            <td class="{{ $balance->getActualRemainingBalance() <= 5 ? 'text-danger' : 'text-success' }}">
                <strong>{{ $balance->getActualRemainingBalance() }}</strong>
            </td>
            <td>
                @php
                    $percentage = $balance->getTotalAvailableBalance() > 0
                        ? round(($balance->used_balance / $balance->getTotalAvailableBalance()) * 100, 1)
                        : 0;
                @endphp
                @if($percentage >= 90)
                    <span class="mr-1 bullet bullet-danger bullet-sm"></span><span class="mail-date">مكتمل تقريباً</span>
                @elseif($percentage >= 75)
                    <span class="mr-1 bullet bullet-warning bullet-sm"></span><span class="mail-date">مرتفع</span>
                @elseif($percentage >= 50)
                    <span class="mr-1 bullet bullet-info bullet-sm"></span><span class="mail-date">متوسط</span>
                @else
                    <span class="mr-1 bullet bullet-success bullet-sm"></span><span class="mail-date">منخفض</span>
                @endif
            </td>
            <td>
                <small>{{ $balance->updated_at->format('d/m/Y') }}</small><br>
                <small class="stk-help">{{ $balance->updated_at->format('H:i') }}</small>
            </td>
            <td class="text-center">
                @if (canAny(['leave_balances.show', 'leave_balances.edit', 'leave_balances.destroy']))
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-secondary" type="button"
                        data-bs-toggle="dropdown" data-bs-display="static"
                        aria-expanded="false" aria-label="خيارات">
                        <i class="fa fa-ellipsis-v"></i>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end">
                        @permission('leave_balances.show')
                            <a class="dropdown-item" href="{{ route('employee_leave_balances.show', $balance->id) }}">
                                <i class="fa fa-eye me-2 text-primary"></i>عرض التفاصيل
                            </a>
                        @endpermission
                        @permission('leave_balances.edit')
                            <a class="dropdown-item" href="{{ route('employee_leave_balances.edit', $balance->id) }}">
                                <i class="fa fa-edit me-2 text-success"></i>تعديل
                            </a>
                        @endpermission
                        @permission('leave_balances.destroy')
                        @if (canAny(['leave_balances.show', 'leave_balances.edit']))
                        <div class="dropdown-divider"></div>
                        @endif
                        @if ($balance->used_balance == 0)
                            <a class="dropdown-item text-danger" href="#" data-toggle="modal" data-target="#modal_DELETE{{ $balance->id }}">
                                <i class="fa fa-trash me-2"></i>حذف
                            </a>
                        @else
                            <span class="dropdown-item text-muted">
                                <i class="fa fa-lock me-2"></i>لا يمكن الحذف - الرصيد مُستخدم
                            </span>
                        @endif
                        @endpermission
                    </div>
                </div>
                @endif
            </td>
        </tr>

        @if($balance->used_balance == 0)
            <!-- Modal delete -->
            <div class="modal fade text-left" id="modal_DELETE{{ $balance->id }}" tabindex="-1" role="dialog"
                 aria-labelledby="myModalLabel{{ $balance->id }}" aria-hidden="true">
                <div class="modal-dialog modal-dialog-scrollable" role="document">
                    <div class="modal-content">
                        <div class="modal-header" style="background-color: var(--app-danger, #EA5455) !important;">
                            <h4 class="modal-title" id="myModalLabel{{ $balance->id }}" style="color: #FFFFFF">
                                حذف رصيد الإجازة
                            </h4>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true" style="color: var(--app-danger, #DC3545)">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <strong>
                                هل أنت متأكد من حذف رصيد إجازة "{{ $balance->leaveType->name ?? 'نوع إجازة محذوف' }}"
                                للموظف "{{ $balance->employee->full_name ?? 'موظف محذوف' }}"؟
                            </strong>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light waves-effect waves-light" data-dismiss="modal">
                                إلغاء
                            </button>
                            <button type="button" class="btn btn-danger waves-effect waves-light delete-confirm"
                                    data-id="{{ $balance->id }}">
                                تأكيد الحذف
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    @endforeach
@else
    <tr>
        <td colspan="12">
            <div class="alert alert-danger text-xl-center" role="alert">
                <p class="mb-0">
                    <i class="fa fa-calendar-times fa-2x mb-2"></i><br>
                    لا توجد أرصدة إجازات مطابقة للبحث !!
                </p>
            </div>
        </td>
    </tr>
@endif
