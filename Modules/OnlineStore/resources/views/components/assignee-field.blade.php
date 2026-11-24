{{--
    حقل «المسند إليه» — مخازن المتجر (متعدّد) والموردون (واحد).

    قاعدة الرؤية (قرار المالك): يرى الموظفُ ما أُسند إليه وإلى من يتبعه إدارياً،
    ومن كان ضمن الفرع الرئيسي أو يملك «كل البيانات» يرى الكل. فمن يُسنَد إليه
    الشيءُ هو من يراه — انظر Modules\OnlineStore\Support\StoreAssignmentScope.

    @props:
      name       اسم الحقل (assigned_employee_ids أو employee_id)
      employees  الموظفون الذين يحقّ للمستخدم الإسنادُ إليهم
      selected   معرّفاتٌ محدَّدة مسبقاً
      multiple   true للمخازن، false للمورد
      label      عنوان الحقل
      help       سطر شارح
--}}
@php
    $multiple = $multiple ?? true;
    $selected = array_map('intval', (array) ($selected ?? []));
    $fieldId = 'assignee_'.preg_replace('/[^a-z0-9_]/i', '_', $name);
    $errorKey = $name;
@endphp

<div class="form-group col-12">
    <label for="{{ $fieldId }}">{{ $label ?? 'المسند إليه' }}</label>
    @if ($multiple)
        <input type="hidden" name="assignees_present" value="1">
    @endif
    <select id="{{ $fieldId }}" name="{{ $name }}{{ $multiple ? '[]' : '' }}"
            class="form-control select2 @error($errorKey) is-invalid @enderror"
            @if ($multiple) multiple @endif data-placeholder="اختر الموظفين">
        @if (! $multiple && ($allowEmpty ?? true))
            <option value="">— بلا مسند —</option>
        @endif
        @foreach ($employees as $employee)
            <option value="{{ $employee->id }}" @selected(in_array((int) $employee->id, $selected, true))>{{ $employee->full_name }}</option>
        @endforeach
    </select>
    @isset($help)
        <span class="stk-help">{{ $help }}</span>
    @endisset
    @error($errorKey)
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
</div>
@once
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (window.jQuery && jQuery.fn.select2) {
                jQuery('select[id^="assignee_"]').select2({ dir: 'rtl', width: '100%' });
            }
        });
    </script>
@endonce
