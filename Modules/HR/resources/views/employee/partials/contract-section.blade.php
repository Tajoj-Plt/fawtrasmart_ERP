{{--
    «إنشاء عقد للموظف» — قسمٌ اختياري في شاشتَي إضافة الموظف وتعديله.

    على نمط «الخزينة والمستودع الافتراضي»: خانةُ تفعيل، وعند الحفظ يُنشأ
    العقدُ براتبه الأساسي في معاملة حفظ الموظف نفسِها — فلا يُعاد إدخال
    المسمّى والمستوى والتاريخ في شاشة العقود. البدلاتُ والاستقطاعات تُضاف
    بعدها من شاشة العقد («قوالب الرواتب» أُلغيت 2026-09-24).
    المنطقُ في `Modules\HR\Services\ContractService::validateEmployeeContract()`
    و`createForEmployee()`.

    @param  \App\Models\Employee|null  $contractEmployee  في التعديل: الموظف (لعرض عقوده القائمة)
--}}
@if (\Modules\HR\Services\ContractService::canCreateFromEmployeeForm())
@php
    $contractEmployee = $contractEmployee ?? null;
    $existingContracts = $contractEmployee
        ? \App\Models\Contract::where('employee_id', $contractEmployee->id)->orderByDesc('start_date')->get(['id', 'code', 'start_date', 'end_date'])
        : collect();
    $ctOn = (bool) old('contract.create', false);
    $ctUnit = (string) old('contract.duration_unit', '3');
    $ctCycle = (string) old('contract.receiving_cycle', '1');
    $ctCurrency = \Modules\HR\Services\ContractService::currency();
@endphp
<div class="card" id="employee-contract-card">
    <div class="stk-table-head">
        <h6 class="stk-table-head__title"><i class="feather icon-file-text"></i> عقد الموظف وبيانات الراتب</h6>
        <i class="feather icon-help-circle stk-tip" title="اختياري: فعّله لإنشاء عقد عملٍ للموظف مع حفظه — يأخذ المسمّى الوظيفي والمستوى من «معلومات وظيفة» أعلاه، وتاريخ الالتحاق من «تاريخ الالتحاق». يمكن تعديل العقد لاحقاً من شاشة العقود."></i>
    </div>
    <div class="card-body">
        @if ($existingContracts->isNotEmpty())
            <div class="alert alert-light mb-2">
                <i class="feather icon-info"></i> للموظف {{ $existingContracts->count() }} عقد:
                @foreach ($existingContracts as $ec)
                    <a href="{{ route('Contracts.show', $ec->id) }}" target="_blank">{{ $ec->code ?: '#'.$ec->id }}</a>
                    <span class="text-muted small" dir="ltr">({{ optional($ec->start_date)->format('Y-m-d') }}{{ $ec->end_date ? ' → '.$ec->end_date->format('Y-m-d') : '' }})</span>@if (! $loop->last)، @endif
                @endforeach
                — فعّل الخيار أدناه لإنشاء عقدٍ جديد (تجديد مثلاً).
            </div>
        @endif

        <label class="stk-switch mb-2" for="contract_create">
            <input type="checkbox" id="contract_create" name="contract[create]" value="1" {{ $ctOn ? 'checked' : '' }}>
            <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
            <span class="stk-switch__label">
                <span class="stk-switch__title">إنشاء عقد للموظف عند الحفظ</span>
                <span class="stk-switch__hint">يُنشأ العقد برقمٍ من «ترقيم المستندات» بالراتب الأساسي؛ وتُضاف البدلات والاستقطاعات من شاشة العقد.</span>
            </span>
        </label>

        <div id="contract-fields" class="{{ $ctOn ? '' : 'd-none' }}">
            <div class="row">
                <div class="form-group col-md-3">
                    <label for="contract_start_date" class="stk-req">بداية العقد</label>
                    <i class="feather icon-help-circle stk-tip" title="أول يوم لسريان العقد — يُقترح تاريخ الالتحاق."></i>
                    <input type="date" class="form-control" id="contract_start_date" name="contract[start_date]" value="{{ old('contract.start_date') }}">
                </div>
                <div class="form-group col-md-2">
                    <label for="contract_duration" class="stk-req">المدة</label>
                    <input type="number" min="1" step="1" class="form-control" id="contract_duration" name="contract[duration]" value="{{ old('contract.duration', 1) }}">
                </div>
                <div class="form-group col-md-2">
                    <label for="contract_duration_unit">الوحدة</label>
                    <select class="form-control hr-s2" id="contract_duration_unit" name="contract[duration_unit]">
                        <option value="1" {{ $ctUnit === '1' ? 'selected' : '' }}>يوم</option>
                        <option value="2" {{ $ctUnit === '2' ? 'selected' : '' }}>شهر</option>
                        <option value="3" {{ $ctUnit === '3' ? 'selected' : '' }}>سنة</option>
                    </select>
                </div>
                <div class="form-group col-md-2">
                    <label for="contract_end_preview">نهاية العقد</label>
                    <i class="feather icon-help-circle stk-tip" title="تُحسب من البداية والمدة (شاملاً يوم البداية) — للمراجعة فقط، والخادم يحسبها بالطريقة نفسها."></i>
                    <input type="text" class="form-control stk-locked" id="contract_end_preview" dir="ltr" readonly tabindex="-1" placeholder="—">
                </div>
                <div class="form-group col-md-3">
                    <label for="contract_probation_end_date">نهاية فترة التجربة</label>
                    <i class="feather icon-help-circle stk-tip" title="إن تُرك فارغاً تُحسب 90 يوماً من تاريخ الالتحاق."></i>
                    <input type="date" class="form-control" id="contract_probation_end_date" name="contract[probation_end_date]" value="{{ old('contract.probation_end_date') }}">
                </div>

                <div class="form-group col-md-3">
                    <label for="contract_basic_salary">الراتب الأساسي</label>
                    <i class="feather icon-help-circle stk-tip" title="الأجر الأساسي الشهري — يجب أن يكون أكبر من صفر. البدلات والاستقطاعات تُضاف بعد الحفظ من شاشة العقد."></i>
                    <input type="text" inputmode="decimal" dir="ltr" class="form-control" id="contract_basic_salary" name="contract[basic_salary]" value="{{ old('contract.basic_salary') }}" placeholder="0.00">
                </div>
                <div class="form-group col-md-2">
                    <label for="contract_receiving_cycle">دورة القبض</label>
                    <select class="form-control hr-s2" id="contract_receiving_cycle" name="contract[receiving_cycle]">
                        <option value="1" {{ $ctCycle === '1' ? 'selected' : '' }}>شهري</option>
                        <option value="2" {{ $ctCycle === '2' ? 'selected' : '' }}>أسبوعي</option>
                        <option value="5" {{ $ctCycle === '5' ? 'selected' : '' }}>مرة كل أسبوعين</option>
                        <option value="4" {{ $ctCycle === '4' ? 'selected' : '' }}>ربع سنوي</option>
                        <option value="3" {{ $ctCycle === '3' ? 'selected' : '' }}>سنوي</option>
                    </select>
                </div>
                <div class="form-group col-md-2">
                    <label for="contract_date_signed">تاريخ التوقيع</label>
                    <input type="date" class="form-control" id="contract_date_signed" name="contract[contract_date]" value="{{ old('contract.contract_date', now()->format('Y-m-d')) }}">
                </div>
                <div class="form-group col-md-2">
                    <label for="contract_currency">العملة</label>
                    <i class="feather icon-help-circle stk-tip" title="عملة النظام من الإعدادات ← العملات، ولا تُختار لكل عقد."></i>
                    <input type="text" class="form-control stk-locked" id="contract_currency" value="{{ $ctCurrency }}" readonly disabled>
                </div>
            </div>
            <small class="stk-help" id="contract-summary" aria-live="polite"></small>
        </div>
    </div>
</div>

@push('styles')
<style>#employee-contract-card .form-group > label { display: inline-block; }</style>
@endpush

@push('scripts')
<script>
(function ($) {
    'use strict';
    var CURRENCY = @json($ctCurrency);

    function num(v) { var n = parseFloat(String(v || '').replace(/[,\s]/g, '')); return isNaN(n) ? 0 : n; }
    function parseDate(v) { if (!v) return null; var p = v.split('-'); return p.length === 3 ? new Date(Date.UTC(+p[0], +p[1] - 1, +p[2])) : null; }
    function fmt(d) { return d.toISOString().slice(0, 10); }
    function addMonths(d, m) {
        var y = d.getUTCFullYear(), mo = d.getUTCMonth() + m, day = d.getUTCDate();
        var last = new Date(Date.UTC(y, mo + 1, 0)).getUTCDate();
        return new Date(Date.UTC(y, mo, Math.min(day, last)));
    }
    function hireInput() { return $('input[name="hire_date"]'); }

    function refresh() {
        var on = $('#contract_create').is(':checked');
        $('#contract-fields').toggleClass('d-none', !on);
        $('#contract_start_date, #contract_duration').prop('required', on);

        if (on && !$('#contract_start_date').val() && hireInput().val()) {
            $('#contract_start_date').val(hireInput().val());
        }

        var start = parseDate($('#contract_start_date').val());
        var n = parseInt($('#contract_duration').val(), 10);
        var unit = $('#contract_duration_unit').val();
        var end = null;
        if (start && n > 0) {
            end = unit === '1' ? new Date(start.getTime() + n * 86400000) : addMonths(start, unit === '2' ? n : n * 12);
            end = new Date(end.getTime() - 86400000);
        }
        $('#contract_end_preview').val(end ? fmt(end) : '');

        var basic = num($('#contract_basic_salary').val());
        $('#contract-summary').html(on
            ? 'مبلغ العقد (الأساسي): <strong dir="ltr">' + basic.toFixed(2) + '</strong> ' + CURRENCY
                + (basic <= 0 ? ' — <span class="text-danger">أدخل الراتب الأساسي.</span>' : '')
            : '');
    }

    $(document).on('change input', '#contract_create, #contract_start_date, #contract_duration, #contract_duration_unit, #contract_basic_salary', refresh);
    $(document).on('change', 'input[name="hire_date"]', function () {
        if ($('#contract_create').is(':checked') && !$('#contract_start_date').data('touched')) {
            $('#contract_start_date').val($(this).val());
            refresh();
        }
    });
    $(document).on('input', '#contract_start_date', function () { $(this).data('touched', true); });
    $(refresh);
})(jQuery);
</script>
@endpush
@endif
