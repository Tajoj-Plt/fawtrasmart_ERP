{{-- تفاصيلُ الطلب — يشترك فيها وجهُ الطلب ووجهُ الاعتماد --}}
<table class="table table-borderless" style="font-size:.88rem;">
    <tr>
        <td style="width:220px;"><strong>الموظف</strong></td>
        <td>{{ $overtimeRequest->employee?->full_name ?? '—' }}</td>
    </tr>
    <tr>
        <td><strong>تاريخ العمل الإضافي</strong></td>
        <td>{{ $overtimeRequest->overtime_date->format('Y-m-d') }}</td>
    </tr>
    <tr>
        <td><strong>الدقائق المطلوبة</strong></td>
        <td>{{ $overtimeRequest->requested_minutes }} دقيقة</td>
    </tr>
    <tr>
        <td><strong>الدقائق المسجَّلة فعلاً</strong></td>
        <td>
            {{-- الواقعُ إلى جانب الطلب: المعتمِدُ يقارن قبل أن يقرّر --}}
            {{ $recordedMinutes }} دقيقة
            @if($recordedMinutes === 0)
                <span class="stk-help">(لم يُسجَّل إضافيٌّ على هذا اليوم بعد)</span>
            @elseif($recordedMinutes > $overtimeRequest->requested_minutes)
                <span class="text-warning">— أكثر ممّا طُلب</span>
            @endif
        </td>
    </tr>
    <tr>
        <td><strong>السبب</strong></td>
        <td>{{ $overtimeRequest->reason ?: '—' }}</td>
    </tr>
    <tr>
        <td><strong>الحالة</strong></td>
        <td>{{ $overtimeRequest->statusLabel() }}</td>
    </tr>
    @if($overtimeRequest->approved_minutes !== null)
        <tr>
            <td><strong>الدقائق المعتمَدة</strong></td>
            <td>{{ $overtimeRequest->approved_minutes }} دقيقة</td>
        </tr>
    @endif
    @if($overtimeRequest->rejection_reason)
        <tr>
            <td><strong>سبب الرفض</strong></td>
            <td class="text-danger">{{ $overtimeRequest->rejection_reason }}</td>
        </tr>
    @endif
    <tr>
        <td><strong>مقدّم الطلب</strong></td>
        <td>{{ $overtimeRequest->requestedBy?->name ?? 'النظام' }} — {{ $overtimeRequest->created_at?->format('Y-m-d H:i') }}</td>
    </tr>
    @if($overtimeRequest->approved_at)
        <tr>
            <td><strong>البتّ فيه</strong></td>
            <td>{{ $overtimeRequest->approvedBy?->name ?? '—' }} — {{ $overtimeRequest->approved_at->format('Y-m-d H:i') }}</td>
        </tr>
    @endif
    @if($overtimeRequest->admin_notes)
        <tr>
            <td><strong>ملاحظات المعتمِد</strong></td>
            <td>{{ $overtimeRequest->admin_notes }}</td>
        </tr>
    @endif
</table>
