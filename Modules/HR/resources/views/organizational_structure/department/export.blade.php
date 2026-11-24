@extends('hr::master')

@section('title')
    تصدير الأقسام
@stop

@php
    $exportFields = [
        'id' => 'المعرف',
        'name' => 'الاسم',
        'short_name' => 'الاختصار',
        'status' => 'الحالة',
        'description' => 'الوصف',
        'managers' => 'المديرون',
        'created_at' => 'تاريخ الإنشاء',
        'updated_at' => 'تاريخ آخر تعديل',
    ];
    $checkedFields = (array) old('fields', array_keys($exportFields));
@endphp

@section('content')
<div class="content-body">
    @include('stock::components.page-head', [
        'title' => 'تصدير الأقسام',
        'icon' => 'download',
        'crumbs' => ['الرئيسية' => url('/'), 'الأقسام' => route('department.index'), 'تصدير' => null],
        'actions' => '<a href="'.route('department.index').'" class="btn btn-outline-secondary"><i class="feather icon-x"></i> إلغاء</a>'
            .'<button type="submit" form="export-form" class="btn btn-primary"><i class="feather icon-download"></i> تصدير Excel</button>',
    ])

    @include('layouts.alerts.error')

    <form id="export-form" action="{{ route('department.export') }}" method="POST">
        @csrf
        <div class="card">
            <div class="card-body">
                <div class="stk-section">
                    <div class="stk-section__head">
                        <i class="feather icon-columns"></i>
                        <h6 class="stk-section__title">الحقول المصدَّرة</h6>
                    </div>

                    <div class="custom-control custom-checkbox mb-2">
                        <input type="checkbox" class="custom-control-input" id="select-all" {{ count($checkedFields) === count($exportFields) ? 'checked' : '' }}>
                        <label class="custom-control-label font-weight-bold" for="select-all">تحديد الكل</label>
                    </div>

                    <div class="row">
                        @foreach ($exportFields as $fieldKey => $fieldLabel)
                            <div class="col-lg-4 col-md-6">
                                <div class="custom-control custom-checkbox mb-1">
                                    <input type="checkbox" class="custom-control-input checkbox-option" id="field_{{ $fieldKey }}"
                                           name="fields[]" value="{{ $fieldKey }}" {{ in_array($fieldKey, $checkedFields, true) ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="field_{{ $fieldKey }}">{{ $fieldLabel }}</label>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <span class="stk-help">يُصدَّر كلُّ الأقسام في ملفّ Excel واحد، بالأعمدة المحدَّدة وبالترتيب أعلاه.</span>
                </div>
            </div>
            <div class="stk-form-foot">
                <a href="{{ route('department.index') }}" class="btn btn-outline-secondary">
                    <i class="feather icon-x"></i> إلغاء
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="feather icon-download"></i> تصدير Excel
                </button>
            </div>
        </div>
    </form>
</div>
@endsection

@section('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const selectAllCheckbox = document.getElementById('select-all');
            const checkboxes = document.querySelectorAll('.checkbox-option');

            selectAllCheckbox.addEventListener('change', function () {
                const isChecked = this.checked;
                checkboxes.forEach(checkbox => {
                    checkbox.checked = isChecked;
                });
            });

            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function () {
                    if (!this.checked) {
                        selectAllCheckbox.checked = false;
                    } else if ([...checkboxes].every(chk => chk.checked)) {
                        selectAllCheckbox.checked = true;
                    }
                });
            });
        });
    </script>
@endsection
