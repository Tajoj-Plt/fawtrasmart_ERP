{{--
    حقولُ الخصمية — مشتركةٌ بين الإنشاء والتعديل.

    المتغيّرات: $employees، $types، $typeAccounts (نوع ← «كود — اسم» حسابه أو null)،
    و$deduction (للتعديل وحده).
--}}
@php
    $d = $deduction ?? null;
    $selectedType = old('deduction_type', $d->deduction_type ?? '');
@endphp

<div class="stk-section">
    <div class="stk-section__head">
        <i class="feather icon-user"></i>
        <h6 class="stk-section__title">الموظف ونوع الخصمية</h6>
    </div>
    <div class="row">
        <div class="form-group col-md-6">
            <label for="employee_id" class="stk-req">الموظف</label>
            <select name="employee_id" id="employee_id" class="form-control select2 @error('employee_id') is-invalid @enderror" required>
                <option value="">— اختر الموظف —</option>
                @foreach ($employees as $emp)
                    <option value="{{ $emp->id }}" @selected((string) old('employee_id', $d->employee_id ?? '') === (string) $emp->id)>
                        {{ trim($emp->first_name . ' ' . $emp->middle_name) }}{{ $emp->nickname ? ' (' . $emp->nickname . ')' : '' }}
                    </option>
                @endforeach
            </select>
            @error('employee_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="form-group col-md-6">
            <label for="deduction_type" class="stk-req">نوع الخصمية
                <i class="feather icon-help-circle stk-tip" title="لكل نوع حسابه الدائن في «إعدادات الحسابات المحاسبية» (مجموعة «الرواتب والموظفين»). يُقيَّد عليه عند الاعتماد."></i>
            </label>
            <select name="deduction_type" id="deduction_type" class="form-control hr-s2 @error('deduction_type') is-invalid @enderror" required>
                <option value="">— اختر النوع —</option>
                @foreach ($types as $key => $label)
                    <option value="{{ $key }}" data-account="{{ $typeAccounts[$key] ?? '' }}" @selected($selectedType === $key)>{{ $label }}</option>
                @endforeach
            </select>
            @error('deduction_type')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            <span class="stk-help" id="deduction_account_hint"></span>
        </div>
    </div>
</div>

<div class="stk-section">
    <div class="stk-section__head">
        <i class="feather icon-dollar-sign"></i>
        <h6 class="stk-section__title">المبلغ والتاريخ</h6>
    </div>
    <div class="row">
        <div class="form-group col-md-6">
            <label for="amount" class="stk-req">المبلغ</label>
            <input type="number" name="amount" id="amount" step="0.01" min="0.01"
                   class="form-control @error('amount') is-invalid @enderror"
                   value="{{ old('amount', $d->amount ?? '') }}" required>
            @error('amount')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="form-group col-md-6">
            <label for="date" class="stk-req">تاريخ الخصمية
                <i class="feather icon-help-circle stk-tip" title="يُرحَّل قيدُ الاعتماد بهذا التاريخ إن كانت فترتُه مفتوحة، وإلا بتاريخ الاعتماد."></i>
            </label>
            <input type="date" name="date" id="date"
                   class="form-control @error('date') is-invalid @enderror"
                   value="{{ old('date', optional($d?->date)->format('Y-m-d') ?? now()->toDateString()) }}" required>
            @error('date')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
    </div>
</div>

<div class="stk-section">
    <div class="stk-section__head">
        <i class="feather icon-message-square"></i>
        <h6 class="stk-section__title">السبب والملاحظات</h6>
    </div>
    <div class="form-group">
        <label for="reason" class="stk-req">السبب</label>
        <textarea name="reason" id="reason" rows="3" maxlength="1000"
                  class="form-control @error('reason') is-invalid @enderror" required
                  placeholder="يظهر للموظف في إشعار الاعتماد">{{ old('reason', $d->reason ?? '') }}</textarea>
        @error('reason')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <div class="form-group mb-0">
        <label for="notes">ملاحظات داخلية</label>
        <textarea name="notes" id="notes" rows="2" maxlength="1000"
                  class="form-control @error('notes') is-invalid @enderror">{{ old('notes', $d->notes ?? '') }}</textarea>
        @error('notes')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
</div>

@push('scripts')
    <script>
        $(function () {
            // الحسابُ الذي سيُقيَّد عليه النوعُ المختار — قبل الحفظ لا بعد رفض الاعتماد
            function showAccount() {
                var acc = $('#deduction_type option:selected').data('account');
                var $hint = $('#deduction_account_hint');
                if (!$('#deduction_type').val()) { $hint.text(''); return; }
                if (acc) {
                    $hint.removeClass('text-danger').text('يُقيَّد عند الاعتماد على: ' + acc);
                } else {
                    $hint.addClass('text-danger').text('لا حسابَ مربوطٌ لهذا النوع ولا حسابَ عامّ — لن تُعتمد الخصمية حتى يُربط من «إعدادات الحسابات المحاسبية».');
                }
            }
            $('#deduction_type').on('change', showAccount);
            showAccount();
        });
    </script>
@endpush
