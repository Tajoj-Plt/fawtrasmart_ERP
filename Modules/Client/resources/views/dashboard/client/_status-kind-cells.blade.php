{{-- خلايا «نشط / موقوف / أخرى / الإجمالي» لصفٍّ من جداول «تابع لـ».
     كلُّ رقمٍ يفتح عملاءه: `data-segment-params` يضيف `status_kind` إلى طلب القائمة.
     @param string $segment  @param int $active  @param int $stopped  @param int $other  @param int $total --}}
<td class="cd-num" data-segment="{{ $segment }}" data-segment-params='{"status_kind":"active"}' role="button" tabindex="0" style="color: var(--cd-success); font-weight: 700;">{{ number_format($active) }}</td>
<td class="cd-num" data-segment="{{ $segment }}" data-segment-params='{"status_kind":"stopped"}' role="button" tabindex="0" style="color: var(--cd-danger); font-weight: 700;">{{ number_format($stopped) }}</td>
<td class="cd-num" data-segment="{{ $segment }}" data-segment-params='{"status_kind":"other"}' role="button" tabindex="0" style="color: var(--cd-muted);">{{ number_format($other) }}</td>
<td class="cd-num" data-segment="{{ $segment }}" role="button" tabindex="0" style="font-weight: 700;">{{ number_format($total) }}</td>
