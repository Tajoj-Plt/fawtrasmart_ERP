{{--
    آخر التسجيلات في مكانٍ أو على جهاز — لشاشتَي العرض.
      punches    مجموعة AttendancePunch
      showKiosk  يُظهر عمود الجهاز (في شاشة المكان)
--}}
<div class="card stk-table-card">
    <div class="stk-table-head">
        <h6 class="stk-table-head__title">
            <i class="feather icon-clock"></i> آخر التسجيلات
            <span class="stk-count">{{ $punches->count() }}</span>
        </h6>
    </div>

    @if ($punches->isNotEmpty())
        <div class="table-responsive">
            <table class="table table-hover table-striped mb-0">
                <thead>
                    <tr>
                        <th>الموظف</th>
                        <th>النوع</th>
                        <th>الوقت</th>
                        @if (! empty($showKiosk))
                            <th>الجهاز</th>
                        @endif
                        <th>الطريقة</th>
                        <th class="text-center">النتيجة</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($punches as $punch)
                        @php $accepted = $punch->result === \App\Models\AttendancePunch::RESULT_ACCEPTED; @endphp
                        <tr>
                            <td>{{ $punch->employee->full_name ?? $punch->employee->first_name ?? '—' }}</td>
                            <td>{{ $punch->punch_type === \App\Models\AttendancePunch::TYPE_OUT ? 'انصراف' : 'حضور' }}</td>
                            <td class="stk-num">{{ optional($punch->punched_at)->format('Y-m-d H:i') }}</td>
                            @if (! empty($showKiosk))
                                <td>{{ $punch->kiosk->name ?? 'من الجوال' }}</td>
                            @endif
                            <td>{{ $punch->methodLabel() }}</td>
                            <td class="text-center">
                                <span class="stk-state stk-state--{{ $accepted ? 'on' : 'off' }}">{{ $accepted ? 'مقبول' : 'مرفوض' }}</span>
                                @if (! $accepted && $punch->rejection_reason)
                                    <div class="stk-help mt-25">{{ $punch->rejection_reason }}</div>
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
                <div class="stk-empty__icon"><i class="feather icon-clock"></i></div>
                <h5 class="stk-empty__title">لا توجد تسجيلات بعد</h5>
            </div>
        </div>
    @endif
</div>
