{{--
    صفُّ حسابٍ واحد في «حسابات الرواتب» — للقراءة.

    المدخلات:
      $row     — صفٌّ من `PayrollAccounts::rows()` (+ `short` و`child` من المتحكّم)
      $hint    — (اختياري) ما يقيّده الحساب، يظهر في «?»
      $canEdit — هل يرى المستخدمُ «إعدادات الحسابات المحاسبية»
--}}
@php
    $editUrl = $canEdit ? \App\Support\PayrollAccounts::editUrl($row['key']) : null;
@endphp
<div class="pa-row" id="pa-key-{{ $row['key'] }}">
    <div class="pa-row__label">
        <span>{{ $row['short'] ?? $row['label'] }}</span>
        @if (! empty($hint))
            <i class="feather icon-help-circle stk-tip" title="{{ $hint }}"></i>
        @endif
    </div>
    <div class="pa-row__value">
        @if ($row['state'] === 'mapped' || $row['state'] === 'inactive')
            <span class="stk-state {{ $row['state'] === 'mapped' ? 'stk-state--on' : 'stk-state--off' }}">{{ $row['account']->code ?? '—' }}</span>
            <span class="pa-row__name">{{ $row['account']->name }}</span>
            @if ($row['state'] === 'inactive')
                <span class="badge badge-danger">الإعداد موقوف — لا يُقيَّد عليه</span>
            @elseif (! $row['account']->is_active)
                <span class="stk-state stk-state--warn">معطَّل في الشجرة</span>
            @endif
        @else
            <span class="badge badge-danger"><i class="feather icon-alert-triangle"></i> غير مربوط</span>
            @if ($editUrl)
                <a href="{{ $editUrl }}" class="pa-row__fix">اربطه</a>
            @endif
        @endif
    </div>
</div>
