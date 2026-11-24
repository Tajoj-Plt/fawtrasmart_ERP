@extends('hr::master')

@section('title', 'إضافة قائمة عطلات')

@section('content')
<div class="hr-hints">
@include('hr::attendance.settings.holiday._form_style')

@php
    $canCreate = hasPermission('holidays.create');
    $cancelLink = hasPermission('holidays.index')
        ? '<a href="'.route('holiday_lists.index').'" class="btn btn-outline-secondary"><i class="feather icon-x"></i> إلغاء</a>'
        : '';
@endphp

<div class="content-body">
    @include('stock::components.page-head', [
        'title' => 'إضافة قائمة عطلات',
        'icon' => 'calendar',
        'crumbs' => ['الرئيسية' => url('/'), 'قوائم العطلات' => route('holiday_lists.index'), 'إضافة' => null],
        'actions' => $canCreate
            ? $cancelLink.'<button type="submit" form="holidayForm" class="btn btn-primary"><i class="feather icon-check"></i> حفظ القائمة</button>'
            : $cancelLink,
    ])

    @include('layouts.alerts.error')

    @permission('holidays.create')
        <form method="POST" action="{{ route('holiday_lists.store') }}" id="holidayForm">
            @csrf

            @if ($errors->any())
                <div class="stk-form-errors alert alert-danger" role="alert">
                    <div class="stk-form-errors__head">
                        <i class="feather icon-alert-circle"></i> لم تُحفظ القائمة — راجع ما يلي:
                    </div>
                    <ul class="stk-form-errors__list mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="card">
                <div class="card-header"><h4 class="card-title"><i class="feather icon-info"></i> البيانات الأساسية</h4></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 col-xl-4">
                            <div class="form-group mb-0">
                                <label class="form-label stk-req">اسم القائمة</label>
                                <input type="text" name="name" class="form-control" placeholder="مثال: عطلات 2026 الرسمية" value="{{ old('name') }}" required>
                                @error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                <small class="stk-help">أضف عطلات بأنواع مختلفة: ثابتة / أسبوعية / شهرية / سنوية.</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="card-title"><i class="feather icon-calendar"></i> العطلات</h4>
                    <span class="badge bg-secondary" id="holidaysCount">1 عطلة</span>
                </div>
                <div class="card-body">
                    <div id="holidaysContainer"></div>
                    <button type="button" id="addRowBtn" class="btn btn-outline-primary btn-sm mt-1">
                        <i class="feather icon-plus"></i> إضافة عطلة أخرى
                    </button>
                </div>
            </div>

            <div class="card">
                <div class="stk-form-foot">
                    @permission('holidays.index')
                        <a href="{{ route('holiday_lists.index') }}" class="btn btn-outline-secondary"><i class="feather icon-x"></i> إلغاء</a>
                    @endpermission
                    <button type="submit" class="btn btn-primary"><i class="feather icon-check"></i> حفظ القائمة</button>
                </div>
            </div>
        </form>
    @endpermission
</div>
</div>
@include('components.hint-tips')
@endsection

@push('scripts')
<script>
let rowIndex = 0;

const MONTHS = {!! json_encode(\App\Models\Holiday::months(), JSON_UNESCAPED_UNICODE) !!};

function getRowTemplate(index) {
    let monthOpts = '';
    for (const [num, name] of Object.entries(MONTHS)) {
        monthOpts += `<option value="${num}">${name}</option>`;
    }
    return `
    <div class="hf-row" data-index="${index}">
        <div class="row-head">
            <span class="row-num">${index + 1}</span>
            <button type="button" class="hf-remove-btn" onclick="removeRow(this)"><i class="feather icon-trash-2"></i></button>
        </div>
        <div class="row">
            <div class="col-md-6 col-xl-3">
                <div class="hf-field">
                    <label class="form-label stk-req">اسم العطلة</label>
                    <input type="text" name="named[${index}]" class="form-control" placeholder="مثال: اليوم الوطني / الجمعة الأسبوعية" required>
                </div>
            </div>
            <div class="col-md-6 col-xl-4">
                <label class="form-label">نوع التكرار</label>
                <div class="hf-type-tabs">
                    <label class="hf-type-tab active" data-type="once">
                        <i class="feather icon-flag"></i> تاريخ ثابت
                        <input type="radio" name="recurrence_type[${index}]" value="once" checked hidden>
                    </label>
                    <label class="hf-type-tab" data-type="weekly">
                        <i class="feather icon-refresh-cw"></i> أسبوعي
                        <input type="radio" name="recurrence_type[${index}]" value="weekly" hidden>
                    </label>
                    <label class="hf-type-tab" data-type="monthly">
                        <i class="feather icon-calendar"></i> شهري
                        <input type="radio" name="recurrence_type[${index}]" value="monthly" hidden>
                    </label>
                    <label class="hf-type-tab" data-type="yearly">
                        <i class="feather icon-star"></i> سنوي
                        <input type="radio" name="recurrence_type[${index}]" value="yearly" hidden>
                    </label>
                </div>
            </div>
            <div class="col-xl-5">
                <div class="row type-fields">
                    <div class="col-md-8 field-once">
                        <div class="hf-field">
                            <label class="form-label">التاريخ</label>
                            <input type="date" name="holiday_date[${index}]" class="form-control">
                        </div>
                    </div>
                    <div class="col-md-8 field-weekly" style="display:none;">
                        <div class="hf-field">
                            <label class="form-label">اليوم</label>
                            <select name="recurrence_day_of_week[${index}]" class="form-select">
                                <option value="0">الأحد</option><option value="1">الاثنين</option>
                                <option value="2">الثلاثاء</option><option value="3">الأربعاء</option>
                                <option value="4">الخميس</option><option value="5" selected>الجمعة</option>
                                <option value="6">السبت</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-8 field-monthly" style="display:none;">
                        <div class="hf-field">
                            <label class="form-label">اليوم من الشهر (1-31)</label>
                            <input type="number" name="recurrence_day_of_month[${index}]" class="form-control" min="1" max="31">
                        </div>
                    </div>
                    <div class="col-md-8 field-yearly" style="display:none;">
                        <div class="row">
                            <div class="col-6 hf-field">
                                <label class="form-label">الشهر</label>
                                <select name="recurrence_month[${index}]" class="form-select">${monthOpts}</select>
                            </div>
                            <div class="col-6 hf-field">
                                <label class="form-label">اليوم</label>
                                <input type="number" name="recurrence_yearly_day[${index}]" class="form-control" min="1" max="31">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="hf-field">
                            <label class="form-label">مدفوعة الأجر؟</label>
                            <div class="form-check form-switch mt-2">
                                <input type="checkbox" name="is_paid[${index}]" value="1" class="form-check-input" checked>
                                <label class="form-check-label">نعم</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>`;
}

function updateCount() {
    const count = document.querySelectorAll('.hf-row').length;
    document.getElementById('holidaysCount').textContent = count + ' عطلة';
    document.querySelectorAll('.hf-row').forEach((row, i) => {
        row.querySelector('.row-num').textContent = i + 1;
    });
}

function removeRow(btn) {
    const rows = document.querySelectorAll('.hf-row');
    if (rows.length === 1) {
        alert('يجب إبقاء عطلة واحدة على الأقل');
        return;
    }
    btn.closest('.hf-row').remove();
    updateCount();
}

function addRow() {
    const container = document.getElementById('holidaysContainer');
    container.insertAdjacentHTML('beforeend', getRowTemplate(rowIndex));
    rowIndex++;
    updateCount();
}

document.getElementById('addRowBtn').addEventListener('click', addRow);

document.addEventListener('click', function(e) {
    const tab = e.target.closest('.hf-type-tab');
    if (!tab) return;

    e.preventDefault();
    const row = tab.closest('.hf-row');
    row.querySelectorAll('.hf-type-tab').forEach(t => t.classList.remove('active'));
    tab.classList.add('active');

    const radio = tab.querySelector('input[type=radio]');
    if (radio) radio.checked = true;

    const type = tab.dataset.type;
    row.querySelectorAll('.type-fields > [class*=field-]').forEach(f => f.style.display = 'none');
    const target = row.querySelector('.field-' + type);
    if (target) target.style.display = '';
});

// أضف الصف الافتراضي عند التحميل
addRow();
</script>
@endpush
