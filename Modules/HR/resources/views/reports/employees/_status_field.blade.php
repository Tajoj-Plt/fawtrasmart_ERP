{{--
    حقلُ «الحالة الوظيفية» بالحالات الستّ ومجموعاتها — داخل `.ag-filter-grid`.

    @param string $statusName     اسمُ الحقل (الافتراض `status`)
    @param string $statusDefault  القيمةُ حين لا يُرسل الحقل (الافتراض: الكل)
    @param string $statusLabel
    @param array  $statusOptions  من المتحكّم (`sharedViewData`)
--}}
@php
    $sfName = $statusName ?? 'status';
    $sfValue = (string) request($sfName, $statusDefault ?? '');
@endphp
<div class="rpt-field">
    <label for="{{ $sfName }}">{{ $statusLabel ?? 'الحالة الوظيفية' }}</label>
    <select name="{{ $sfName }}" id="{{ $sfName }}" class="form-control">
        @foreach ($statusOptions as $value => $label)
            <option value="{{ $value }}" @selected($sfValue === (string) $value)>{{ $label }}</option>
        @endforeach
    </select>
</div>
