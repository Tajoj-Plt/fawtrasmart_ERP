{{--
    لمن الموعد، ومن يعلم به، وهل يتكرّر — مشتركٌ بين الإنشاء والتعديل.

    • **الموظفون** إلزاميّون ومتعدّدون (`employee_ids[]` ← `appointment_employees`).
      كان «تعيين موظف» مربّعاً اختيارياً وقائمتُه فارغةٌ دائماً.
    • **المشاركة** تُرسل للعميل بريداً وإشعاراً وتُظهر الموعد في بوابته — كان
      المربّعُ يُرسَل ولا يُحفظ.
    • **التكرار** يولّد المواعيدَ التالية بمهمّة «توليد المواعيد المتكرّرة»؛
      الاسمُ `is_recurring` في النموذجين (كان `recurring` في الإنشاء فلا يُقرأ).

    المتغيّرات: $employees, $selectedEmployees, $share, $recurring,
    $recurrenceType, $recurrenceDate, $recurringJobActive, $isOccurrence
--}}
@php
    $selectedEmployees = array_map('intval', (array) $selectedEmployees);
    $isOccurrence = $isOccurrence ?? false;
@endphp

<div class="form-group">
    <label for="employee_ids">الموظفون المسؤولون عن الموعد <span class="text-danger">*</span></label>
    <div class="rsv-select-add">
        <select class="form-control select2 @error('employee_ids') is-invalid @enderror" id="employee_ids" name="employee_ids[]" multiple
            data-placeholder="اختر موظفاً أو أكثر">
            @foreach ($employees as $employee)
                <option value="{{ $employee->id }}" @selected(in_array((int) $employee->id, $selectedEmployees, true))>
                    {{ $employee->full_name }}
                </option>
            @endforeach
        </select>
        @permission('employees.create')
            @if (Route::has('employee.create'))
                <a href="{{ route('employee.create') }}" target="_blank" rel="noopener"
                    class="btn btn-outline-primary rsv-select-add__btn" title="إضافة موظف جديد">
                    <i class="feather icon-plus"></i>
                </a>
            @endif
        @endpermission
    </div>
    <small class="form-text text-muted">
        يظهر الموعد في مواعيد كل موظف محدد، ويصله إشعار عند الإسناد وتذكير قبل الموعد.
    </small>
    @error('employee_ids')
        <div class="text-danger small">{{ $message }}</div>
    @enderror
</div>

<div class="form-group">
    <div class="vs-checkbox-con vs-checkbox-primary">
        <input type="checkbox" id="share_with_client" name="share_with_client" value="1" @checked($share)>
        <span class="vs-checkbox"><span class="vs-checkbox--check"><i class="vs-icon feather icon-check"></i></span></span>
        <span><i class="fas fa-share-alt me-1"></i> مشاركة مع العميل</span>
    </div>
    <small class="form-text text-muted">
        يُرسَل للعميل بريد بتفاصيل الموعد (إن كان له بريد)، وإشعار، ويظهر الموعد في بوابة العميل.
    </small>
</div>

<div class="form-group mb-0">
    @if ($isOccurrence)
        <div class="alert alert-info mb-0 py-2">
            <i class="fas fa-redo me-1"></i>
            هذا الموعد مولَّد من موعد متكرر — التكرار يُعدَّل من الموعد الأصلي.
        </div>
    @else
        <div class="vs-checkbox-con vs-checkbox-primary">
            <input type="checkbox" id="is_recurring" name="is_recurring" value="1" onchange="toggleRecurringFields(this)" @checked($recurring)>
            <span class="vs-checkbox"><span class="vs-checkbox--check"><i class="vs-icon feather icon-check"></i></span></span>
            <span><i class="fas fa-redo me-1"></i> موعد متكرر</span>
        </div>

        <div id="recurring-fields" class="mt-3 row" style="{{ $recurring ? '' : 'display: none;' }}">
            <div class="col-md-6">
                <label for="recurrence_type">نوع التكرار <span class="text-danger">*</span></label>
                <select class="form-control select2" id="recurrence_type" name="recurrence_type">
                    @foreach (\App\Models\Appointment::RECURRENCE_LABELS as $value => $label)
                        <option value="{{ $value }}" @selected((int) $recurrenceType === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6">
                <label for="recurrence_date">تاريخ نهاية التكرار <span class="text-danger">*</span></label>
                <input type="date" class="form-control" id="recurrence_date" name="recurrence_date"
                    min="{{ date('Y-m-d', strtotime('+1 day')) }}" value="{{ $recurrenceDate }}">
            </div>
            <div class="col-12 mt-2">
                @if ($recurringJobActive)
                    <small class="form-text text-muted">
                        <i class="feather icon-clock"></i>
                        تُنشئ المهمة المجدولة «توليد المواعيد المتكررة» الموعد التالي تلقائياً قبل حلوله بأسبوع،
                        بنفس العميل والموظفين والوقت، حتى تاريخ النهاية.
                    </small>
                @else
                    <small class="form-text text-warning">
                        <i class="feather icon-alert-triangle"></i>
                        مهمة «توليد المواعيد المتكررة» غير مفعّلة في المهام المجدولة — لن تُنشأ المواعيد التالية حتى تُفعَّل.
                    </small>
                @endif
            </div>
        </div>
    @endif
</div>
