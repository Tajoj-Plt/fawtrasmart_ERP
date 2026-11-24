{{--
    اختيار مالكٍ من ملّاك العقارات (لا من ملفّ العملاء). الوسائط: $name، $owners،
    $selected، $required، $placeholder، $id.
--}}
@php
    $fieldId = $id ?? $name;
    $current = (string) old($name, $selected ?? '');
@endphp
<select name="{{ $name }}" id="{{ $fieldId }}" class="form-control select2" @if (! empty($required)) data-required="1" @endif>
    <option value="">{{ $placeholder ?? 'اختر المالك…' }}</option>
    @foreach ($owners as $owner)
        <option value="{{ $owner->id }}" @selected($current === (string) $owner->id)>{{ $owner->name }}@if ($owner->code) — {{ $owner->code }}@endif</option>
    @endforeach
</select>
@permission('realestate.owners.create')
    <small class="text-muted"><a href="{{ route('realestate.owners.create') }}" target="_blank" rel="noopener"><i class="feather icon-user-plus"></i> مالك جديد</a> — ثم أعد تحميل الصفحة ليظهر في القائمة</small>
@endpermission
