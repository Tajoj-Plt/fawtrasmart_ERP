{{--
    نموذجُ القسيمة — مشتركٌ بين الإنشاء والتعديل.

    @param \App\Models\SalarySlip|null $slip
    @param \Illuminate\Support\Collection $payrolls        (إنشاء) المسيرات النشطة
    @param \Illuminate\Support\Collection $employees       (إنشاء) صفوفُ موظفي المسير المختار
    @param \Illuminate\Support\Collection $additionItems / $deductionItems
    @param \Illuminate\Support\Collection $treasuries
    @param string|null $nextSlipCode

    **لا يضيع شيءٌ بخطأ تحقّق:** كلُّ حقلٍ من `old()`، والمسيرُ والموظفُ في
    Select2 يعودان مختارَين، وصفوفُ البنود تُبنى من `old('addition_type')` وأخواتها.
--}}
@php
    $isEdit = isset($slip) && $slip;
    $currency = \App\Support\CurrencySettings::code();

    $catalogOf = fn ($items) => $items->map(fn ($i) => [
        'id' => $i->id, 'name' => $i->name, 'amount' => (float) $i->amount, 'formula' => $i->calculation_formula,
    ])->values();
    $psCatalog = ['addition' => $catalogOf($additionItems), 'deduction' => $catalogOf($deductionItems)];

    /* بنودُ القسيمة المحفوظة (تعديل) تُطابَق مع البند المعرَّف باسمه. */
    $savedRows = ['addition' => [], 'deduction' => []];
    if ($isEdit) {
        foreach (['addition' => [\App\Models\SalaryItem::TYPE_ADDITION, $additionItems], 'deduction' => [\App\Models\SalaryItem::TYPE_DEDUCTION, $deductionItems]] as $prefix => [$type, $catalog]) {
            $savedRows[$prefix] = $slip->salaryItems->where('type', $type)->map(fn ($i) => [
                'id' => (string) optional($catalog->firstWhere('name', $i->name))->id,
                'amount' => (float) $i->amount,
                'formula' => $i->calculation_formula,
            ])->values()->all();
        }
    }

    /* صفوفُ البنود: من `old()` بعد خطأ تحقّق، وإلّا المحفوظة. */
    $rowsFor = function (string $prefix) use ($savedRows) {
        if (! is_array(old($prefix.'_type'))) {
            return $savedRows[$prefix];
        }

        $amounts = old($prefix.'_amount', []);
        $formulas = old($prefix.'_calculation_formula', []);

        return collect(old($prefix.'_type', []))->map(fn ($t, $k) => [
            'id' => (string) $t,
            'amount' => $amounts[$k] ?? '',
            'formula' => $formulas[$k] ?? '',
        ])->values()->all();
    };

    $additionRows = $rowsFor('addition');
    $deductionRows = $rowsFor('deduction');

    $oldPayrollId = (string) old('payroll_id', $isEdit ? $slip->payroll_id : request('payroll_id'));
    $oldEmployeeId = (string) old('employee_id', $isEdit ? $slip->employee_id : request('employee_id'));
    $basicValue = old('basic_amount', $isEdit ? (float) $slip->basic_amount : '');
@endphp

<style>
    .ps-items .form-control { min-width: 110px; }
    .ps-items td { vertical-align: middle; }
    .ps-basic-row { background: color-mix(in srgb, var(--app-warning) 7%, transparent); }
    /* اللوحة ثلثُ العرض: قائمةُ «العقد» عمودٌ واحد (لا شبكةَ عمودين كـstk-def--compact
       على الشاشات الواسعة)، وإلا ضاق عمودُ القيمة إلى صفرٍ فانكسر النصّ حرفاً حرفاً. */
    .ps-emp-panel { margin-top: .75rem; }
    .ps-emp-panel .row > [class*="col-"] { margin-bottom: .75rem; }
    .ps-emp-panel .stk-def--compact { grid-template-columns: 1fr !important; }
    .ps-emp-panel .stk-def__row { grid-template-columns: 120px minmax(0, 1fr); }
    .ps-emp-panel .stk-def dd { overflow-wrap: anywhere; }
    .ps-emp-panel .stk-def__row:first-child { padding-top: .3rem; }
    .ps-mini { width: 100%; font-size: .82rem; }
    .ps-mini th, .ps-mini td { padding: .35rem .5rem; border-bottom: 1px solid var(--stk-line-soft); }
</style>

{{-- ══════════ المسير والموظف ══════════ --}}
<div class="card">
    <div class="stk-table-head">
        <h6 class="stk-table-head__title"><i class="fa fa-stream"></i> مسير الرواتب والموظف</h6>
    </div>
    <div class="card-body">
        <div class="row">
            @if ($isEdit)
                <div class="form-group col-md-3">
                    <label for="salary_slip_code">رقم القسيمة</label>
                    <input type="text" class="form-control stk-num" id="salary_slip_code" value="{{ $slip->code ?: '#'.$slip->id }}" readonly disabled>
                </div>
            @else
                {{-- حقلُ الرقم في شاشة الإنشاء نفسِها (معاينةٌ بلا name) --}}
                @stack('ps-code-field')
            @endif

            @if ($isEdit)
                <div class="form-group col-md-4">
                    <label>مسير الرواتب</label>
                    <input type="text" class="form-control" value="{{ $slip->payroll ? $slip->payroll->name.' (#'.$slip->payroll->id.')' : '—' }}" readonly disabled>
                </div>
                <div class="form-group col-md-5">
                    <label>الموظف <i class="feather icon-help-circle stk-tip" title="الموظف والمسير لا يتغيّران بالتعديل: القسيمة اتفاقٌ لموظفٍ بعينه وصرفاتُها السابقة مقيَّدةٌ عليه"></i></label>
                    <input type="text" class="form-control" value="{{ $slip->employee ? trim($slip->employee->first_name.' '.$slip->employee->middle_name.' '.$slip->employee->nickname) : '—' }}" readonly disabled>
                    <input type="hidden" id="employee_id" value="{{ $slip->employee_id }}">
                </div>
            @else
                <div class="form-group col-md-4">
                    <label for="payroll_id" class="stk-req">مسير الرواتب</label>
                    <select name="payroll_id" id="payroll_id" class="form-control ps-s2" required data-placeholder="— اختر مسير الرواتب —">
                        <option value=""></option>
                        @foreach ($payrolls as $pr)
                            <option value="{{ $pr->id }}"
                                    data-from="{{ optional($pr->start_date)->toDateString() }}"
                                    data-to="{{ optional($pr->end_date)->toDateString() }}"
                                    {{ $oldPayrollId === (string) $pr->id ? 'selected' : '' }}>
                                {{ $pr->name }} (#{{ $pr->id }})
                            </option>
                        @endforeach
                    </select>
                    <x-create-first
                        :count="count($payrolls ?? [])"
                        route="PayrollProcess.create"
                        permission="payroll.create"
                        :plan="\App\Constants\planFeatures\HrPlanFeatures::PAYROLL"
                        label="مسيّر رواتب"
                        hint="القسيمة تتبع مسيّراً، ولا مسيّر نشطٌ بعد." />
                    <small class="stk-help" id="payroll_period_hint">المسيرات النشطة (المعتمدة) وحدها</small>
                </div>
                <div class="form-group col-md-5">
                    <label for="employee_id" class="stk-req">الموظف <i class="feather icon-help-circle stk-tip" title="تُملأ القائمة بموظفي المسير المختار وحدهم — الموظفون النشطون ضمن نطاقك"></i></label>
                    <select name="employee_id" id="employee_id" class="form-control ps-s2" required data-placeholder="— اختر المسير أولاً —" {{ $employees->isEmpty() && $oldPayrollId === '' ? 'disabled' : '' }}>
                        <option value=""></option>
                        @foreach ($employees as $row)
                            <option value="{{ $row['id'] }}" {{ $oldEmployeeId === (string) $row['id'] ? 'selected' : '' }}>
                                {{ $row['name'] }}{{ $row['code'] ? ' — '.$row['code'] : '' }}{{ $row['department'] ? ' · '.$row['department'] : '' }}{{ $row['has_slip'] ? ' (له قسيمة)' : '' }}
                            </option>
                        @endforeach
                    </select>
                    <small class="stk-help" id="employee_count_hint"></small>
                </div>
            @endif
        </div>

        <div id="employee_status_info"></div>
        <div id="employee_details" class="ps-emp-panel" style="display:none;">
            <div class="row">
                <div class="col-md-4" id="contract_info"></div>
                <div class="col-md-4" id="advances_info"></div>
                <div class="col-md-4" id="deductions_info"></div>
            </div>
        </div>
    </div>
</div>

{{-- ══════════ الفترة وإعدادات الصرف ══════════ --}}
<div class="card">
    <div class="stk-table-head">
        <h6 class="stk-table-head__title"><i class="feather icon-calendar"></i> فترة القسيمة وإعدادات الصرف</h6>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="form-group col-md-3">
                <label for="start_date" class="stk-req">تاريخ بداية القسيمة</label>
                <input type="date" id="start_date" name="start_date" class="form-control" required
                       value="{{ old('start_date', $isEdit ? optional($slip->start_date)->format('Y-m-d') : date('Y-m-01')) }}">
            </div>
            <div class="form-group col-md-3">
                <label for="end_date">تاريخ نهاية القسيمة <i class="feather icon-help-circle stk-tip" title="فارغ = قسيمةٌ مفتوحة تنتهي مع العقد إن وُجد"></i></label>
                <input type="date" id="end_date" name="end_date" class="form-control"
                       value="{{ old('end_date', $isEdit ? optional($slip->end_date)->format('Y-m-d') : '') }}">
            </div>
            <div class="form-group col-md-2">
                <label for="frequency">التكرار</label>
                @php $freq = old('frequency', $isEdit ? $slip->frequency : 'monthly'); @endphp
                <select id="frequency" name="frequency" class="form-control hr-s2">
                    <option value="monthly" {{ $freq === 'monthly' ? 'selected' : '' }}>شهري</option>
                    <option value="weekly" {{ $freq === 'weekly' ? 'selected' : '' }}>أسبوعي</option>
                </select>
            </div>
            <div class="form-group col-md-2">
                <label for="scheduled_payment_day">يوم الصرف <i class="feather icon-help-circle stk-tip" title="من 1 إلى 28 — فارغ = يوم الموظف أو الافتراضي من إعدادات الرواتب"></i></label>
                <input type="number" id="scheduled_payment_day" name="scheduled_payment_day" min="1" max="28" class="form-control stk-num"
                       value="{{ old('scheduled_payment_day', $isEdit ? $slip->scheduled_payment_day : '') }}">
            </div>
            <div class="form-group col-md-2">
                <label for="treasury_id">الخزينة</label>
                @php $oldTreasury = (string) old('treasury_id', $isEdit ? $slip->treasury_id : ''); @endphp
                <select id="treasury_id" name="treasury_id" class="form-control ps-s2" data-placeholder="خزينة الموظف الافتراضية">
                    <option value=""></option>
                    @foreach ($treasuries as $t)
                        <option value="{{ $t->id }}" {{ $oldTreasury === (string) $t->id ? 'selected' : '' }}>{{ $t->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-12 mb-0">
                <label for="notes">ملاحظات</label>
                <textarea id="notes" name="notes" class="form-control" rows="2">{{ old('notes', $isEdit ? $slip->notes : '') }}</textarea>
            </div>
        </div>
    </div>
</div>

{{-- ══════════ بنود الاستحقاق ══════════ --}}
@foreach ([
    ['prefix' => 'addition', 'title' => 'الاستحقاقات', 'icon' => 'plus-circle', 'rows' => $additionRows, 'catalog' => $additionItems, 'button' => 'إضافة بند استحقاق (بدل/علاوة/مكافأة)'],
    ['prefix' => 'deduction', 'title' => 'الخصومات اليدوية', 'icon' => 'minus-circle', 'rows' => $deductionRows, 'catalog' => $deductionItems, 'button' => 'إضافة بند خصم'],
] as $block)
    <div class="card stk-table-card">
        <div class="stk-table-head">
            <h6 class="stk-table-head__title">
                <i class="feather icon-{{ $block['icon'] }}"></i> {{ $block['title'] }}
                <span class="stk-count" id="{{ $block['prefix'] }}Count">0</span>
            </h6>
            @if ($block['prefix'] === 'deduction')
                <small class="stk-help m-0">السلفُ والخصمياتُ المعتمدةُ تُخصم تلقائياً وقت الصرف — لا تُدخلها هنا</small>
            @endif
        </div>
        <div class="table-responsive">
            <table class="table mb-0 ps-items" id="{{ $block['prefix'] }}Table">
                <thead>
                    <tr>
                        <th>البند</th>
                        <th>الصيغة الحسابية <i class="feather icon-help-circle stk-tip" title="اختيارية — مثال: basic * 0.1 (basic = الراتب الأساسي). المبلغ المكتوب يتقدّم عليها"></i></th>
                        <th style="width:190px">المبلغ ({{ $currency }})</th>
                        <th style="width:60px"></th>
                    </tr>
                </thead>
                <tbody id="{{ $block['prefix'] }}TableBody">
                    @if ($block['prefix'] === 'addition')
                        <tr class="ps-basic-row">
                            <td>
                                <strong><i class="feather icon-key"></i> الراتب الأساسي</strong>
                                <small class="stk-help m-0" id="basic_hint">يُحمَّل من العقد الساري — وبلا عقدٍ يكفي بندُ استحقاقٍ بمبلغ</small>
                            </td>
                            <td><span class="text-muted small">—</span></td>
                            <td>
                                <div class="form-group mb-0">
                                    <input type="number" step="0.01" min="0" class="form-control stk-num" name="basic_amount" id="basic_amount"
                                           placeholder="0.00" value="{{ $basicValue }}">
                                </div>
                            </td>
                            <td class="text-center"><i class="feather icon-lock text-muted" title="بند رئيسي"></i></td>
                        </tr>
                    @endif
                    @foreach ($block['rows'] as $row)
                        <tr class="dynamic-row">
                            <td>
                                <select name="{{ $block['prefix'] }}_type[]" class="form-control item-select hr-s2">
                                    <option value="">— اختر بنداً —</option>
                                    @foreach ($block['catalog'] as $ci)
                                        <option value="{{ $ci->id }}" data-amount="{{ (float) $ci->amount }}" data-formula="{{ $ci->calculation_formula }}"
                                            {{ (string) $row['id'] === (string) $ci->id ? 'selected' : '' }}>{{ $ci->name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td><input type="text" name="{{ $block['prefix'] }}_calculation_formula[]" class="form-control formula-input" value="{{ $row['formula'] }}" placeholder="مثال: basic * 0.1"></td>
                            <td><input type="number" step="0.01" min="0" name="{{ $block['prefix'] }}_amount[]" class="form-control amount-input stk-num" value="{{ $row['amount'] }}" placeholder="0.00"></td>
                            <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger remove-row" title="حذف" aria-label="حذف"><i class="feather icon-trash-2"></i></button></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="stk-form-foot justify-content-start">
            <button type="button" class="btn btn-outline-secondary btn-sm add-row" data-prefix="{{ $block['prefix'] }}">
                <i class="feather icon-plus"></i> {{ $block['button'] }}
            </button>
            @if ($block['catalog']->isEmpty())
                <x-create-first
                    :count="0"
                    route="SalaryItems.create"
                    permission="salary_items.create"
                    :plan="\App\Constants\planFeatures\HrPlanFeatures::SALARY_ITEMS"
                    label="بنداً"
                    hint="بنود الراتب تُعرَّف في إعدادات الرواتب، ولا بند بعد." />
            @endif
        </div>
    </div>
@endforeach

{{-- ══════════ الملخّص ══════════ --}}
<div class="card">
    <div class="stk-table-head">
        <h6 class="stk-table-head__title"><i class="fa fa-calculator"></i> ملخّص الراتب</h6>
        <small class="stk-help m-0">الصافي = الأساسي + الاستحقاقات − الخصومات اليدوية</small>
    </div>
    <div class="stk-metrics">
        <div class="stk-metric"><span class="stk-metric__value stk-num" id="sumBasic">0.00</span><span class="stk-metric__label">الأساسي</span></div>
        <div class="stk-metric stk-metric--success"><span class="stk-metric__value stk-num">+<span id="sumAdditions">0.00</span></span><span class="stk-metric__label">الاستحقاقات</span></div>
        <div class="stk-metric"><span class="stk-metric__value stk-num" id="sumTotal">0.00</span><span class="stk-metric__label">الإجمالي</span></div>
        <div class="stk-metric stk-metric--danger"><span class="stk-metric__value stk-num">−<span id="sumDeductions">0.00</span></span><span class="stk-metric__label">الخصومات</span></div>
        <div class="stk-metric stk-metric--success"><span class="stk-metric__value stk-num" id="sumNet">0.00</span><span class="stk-metric__label">الصافي ({{ $currency }})</span></div>
    </div>
    <div class="stk-form-foot">
        @if (hasPermission($isEdit ? 'payslips.show' : 'payslips.index'))
            <a href="{{ $isEdit ? route('salarySlip.show', $slip->id) : route('salarySlip.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-x"></i> إلغاء
            </a>
        @endif
        <button type="submit" class="btn btn-primary"><i class="feather icon-save"></i> {{ $isEdit ? 'حفظ التعديلات' : 'حفظ كمسودة' }}</button>
    </div>
</div>

@push('scripts')
    <script>
        (function () {
            var CATALOG = @json($psCatalog);
            var EMPLOYEE_DATA_URL = @json(route('salarySlip.employeePayrollData', ['employeeId' => '__ID__']));
            var PAYROLL_EMPLOYEES_URL = @json(route('salarySlip.payrollEmployees', ['payrollId' => '__ID__']));
            var IS_EDIT = @json($isEdit);
            var BASIC_TOUCHED = @json(old('basic_amount') !== null || $isEdit);

            function esc(s) { return $('<div>').text(s == null ? '' : String(s)).html(); }
            function money(n) { return Number(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }

            function initSelect2($scope) {
                if (!$.fn.select2) return;
                ($scope || $(document)).find('select.ps-s2').each(function () {
                    if ($(this).hasClass('select2-hidden-accessible')) return;
                    $(this).select2({ dir: 'rtl', width: '100%', allowClear: true, placeholder: $(this).data('placeholder') || '' });
                });
            }

            // ── البنود ──
            function rowHtml(prefix) {
                var opts = '<option value="">— اختر بنداً —</option>';
                CATALOG[prefix].forEach(function (o) {
                    opts += '<option value="' + o.id + '" data-amount="' + (o.amount || 0) + '" data-formula="' + esc(o.formula || '') + '">' + esc(o.name) + '</option>';
                });
                return '<tr class="dynamic-row">' +
                    '<td><select name="' + prefix + '_type[]" class="form-control item-select hr-s2">' + opts + '</select></td>' +
                    '<td><input type="text" name="' + prefix + '_calculation_formula[]" class="form-control formula-input" placeholder="مثال: basic * 0.1"></td>' +
                    '<td><input type="number" step="0.01" min="0" name="' + prefix + '_amount[]" class="form-control amount-input stk-num" placeholder="0.00"></td>' +
                    '<td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger remove-row" title="حذف" aria-label="حذف"><i class="feather icon-trash-2"></i></button></td>' +
                    '</tr>';
            }

            function evalFormula(formula, basic) {
                var expr = String(formula || '').replace(/basic_amount|basic_salary|basic/g, String(basic));
                if (!/^[\d+\-*/().\s]+$/.test(expr)) return null;
                try { var v = (new Function('return ' + expr))(); return isFinite(v) ? v : null; } catch (e) { return null; }
            }

            function sumOf(prefix) {
                var total = 0;
                $('#' + prefix + 'TableBody .dynamic-row').each(function () {
                    if (!$(this).find('.item-select').val()) return;
                    total += parseFloat($(this).find('.amount-input').val()) || 0;
                });
                return total;
            }

            function recalc() {
                var basic = parseFloat($('#basic_amount').val()) || 0;
                var add = sumOf('addition'), ded = sumOf('deduction');
                $('#sumBasic').text(money(basic));
                $('#sumAdditions').text(money(add));
                $('#sumDeductions').text(money(ded));
                $('#sumTotal').text(money(basic + add));
                $('#sumNet').text(money(basic + add - ded));
                $('#additionCount').text($('#additionTableBody .dynamic-row').length + 1);
                $('#deductionCount').text($('#deductionTableBody .dynamic-row').length);
            }

            // ── بيانات الموظف ──
            function loadEmployee(empId) {
                var $status = $('#employee_status_info'), $details = $('#employee_details');
                if (!empId) { $details.hide(); $status.empty(); return; }

                $status.html('<div class="stk-help"><i class="fa fa-spinner fa-spin"></i> جارٍ تحميل بيانات الموظف…</div>');
                $.getJSON(EMPLOYEE_DATA_URL.replace('__ID__', empId)).done(function (data) {
                    if (!data.success) { $status.html('<div class="alert alert-danger small mb-0">' + esc(data.error || 'تعذّر التحميل') + '</div>'); return; }

                    var warn = [];
                    if (!data.has_payable_account) warn.push('لا يملك الموظف حساب مستحقات راتب — لا تُحفظ القسيمة حتى يُربط من بطاقة الموظف.');
                    $status.html(warn.length ? '<div class="alert alert-warning small mt-2 mb-2">' + warn.map(esc).join('<br>') + '</div>' : '');

                    var c = '<dl class="stk-def stk-def--compact"><div class="stk-def__row"><dt>العقد</dt><dd>';
                    if (data.contract) {
                        c += '#' + esc(data.contract.id) + ' — ' + money(data.contract.basic_salary) + '<br><small class="text-muted">' + esc(data.contract.start_date || '—') + ' ← ' + esc(data.contract.end_date || 'مفتوح') + '</small>';
                        if (!BASIC_TOUCHED || !$('#basic_amount').val()) { $('#basic_amount').val(data.contract.basic_salary); }
                        $('#basic_hint').text('من العقد الساري #' + data.contract.id + ' — يمكنك تعديله');
                    } else {
                        c += '<span class="stk-state stk-state--warn">بلا عقد ساري</span>';
                        $('#basic_hint').text('الموظف بلا عقد: الأساسيّ اختياري — يكفي بندُ استحقاقٍ بمبلغ ويُحسب الراتب منه');
                    }
                    c += '</dd></div><div class="stk-def__row"><dt>حساب المستحقات</dt><dd>' + (data.has_payable_account ? esc(data.payable_account_name || '✓') : '<span class="text-danger">غير مرتبط</span>') + '</dd></div>';
                    c += '<div class="stk-def__row"><dt>يوم الصرف الافتراضي</dt><dd>' + esc(data.effective_payment_day) + '</dd></div></dl>';
                    $('#contract_info').html(c);

                    var a = '<h6 class="stk-section__title mb-1"><i class="fa fa-hand-holding-usd"></i> السلف غير المسوّاة</h6>';
                    if (data.advances && data.advances.length) {
                        a += '<table class="ps-mini"><tbody>' + data.advances.map(function (x) {
                            return '<tr><td>' + esc(x.name) + '</td><td class="stk-num">' + money(x.amount) + '</td></tr>';
                        }).join('') + '</tbody></table><small class="stk-help">تُخصم تلقائياً عند الصرف</small>';
                    } else { a += '<small class="stk-help">لا توجد سلف غير مسوّاة</small>'; }
                    $('#advances_info').html(a);

                    var d = '<h6 class="stk-section__title mb-1"><i class="feather icon-minus-circle"></i> الخصميات المعتمدة</h6>';
                    if (data.deductions && data.deductions.length) {
                        d += '<table class="ps-mini"><tbody>' + data.deductions.map(function (x) {
                            return '<tr><td>' + esc(x.type) + '</td><td class="stk-num">' + money(x.remaining) + '</td></tr>';
                        }).join('') + '</tbody></table><small class="stk-help">تُخصم تلقائياً عند أول صرف</small>';
                    } else { d += '<small class="stk-help">لا توجد خصميات معلّقة</small>'; }
                    $('#deductions_info').html(d);

                    $details.show();
                    recalc();
                }).fail(function (xhr) {
                    var msg = (xhr.responseJSON && xhr.responseJSON.error) || 'تعذّر تحميل بيانات الموظف';
                    $status.html('<div class="alert alert-danger small mb-0">' + esc(msg) + '</div>');
                });
            }

            // ── موظفو المسير ──
            function loadPayrollEmployees(payrollId, keepId) {
                var $emp = $('#employee_id');
                var $opt = $('#payroll_id option:selected');
                $('#payroll_period_hint').text(payrollId
                    ? 'فترة المسير: ' + ($opt.data('from') || 'مفتوحة') + ' ← ' + ($opt.data('to') || 'مفتوحة')
                    : 'المسيرات النشطة (المعتمدة) وحدها');

                if (!payrollId) {
                    $emp.empty().append('<option value=""></option>').prop('disabled', true).trigger('change.select2');
                    $('#employee_count_hint').text('');
                    loadEmployee(null);
                    return;
                }

                $emp.prop('disabled', true);
                $('#employee_count_hint').html('<i class="fa fa-spinner fa-spin"></i> جارٍ جلب موظفي المسير…');
                $.getJSON(PAYROLL_EMPLOYEES_URL.replace('__ID__', payrollId)).done(function (res) {
                    $emp.empty().append('<option value=""></option>');
                    res.employees.forEach(function (e) {
                        var label = e.name + (e.code ? ' — ' + e.code : '') + (e.department ? ' · ' + e.department : '') + (e.has_slip ? ' (له قسيمة)' : '');
                        $emp.append($('<option></option>').attr('value', e.id).text(label).prop('selected', String(e.id) === String(keepId)));
                    });
                    $emp.prop('disabled', res.employees.length === 0).trigger('change.select2');
                    $('#employee_count_hint').text(res.employees.length
                        ? res.employees.length + ' موظفاً في هذا المسير'
                        : 'لا موظفَ نشطٌ ضمن نطاقك في هذا المسير');
                    loadEmployee($emp.val());
                }).fail(function () {
                    $('#employee_count_hint').text('تعذّر جلب موظفي المسير');
                });
            }

            $(function () {
                initSelect2();

                $(document).on('click', '.add-row', function () {
                    var prefix = $(this).data('prefix');
                    $('#' + prefix + 'TableBody').append(rowHtml(prefix));
                    recalc();
                });
                $(document).on('click', '.remove-row', function () { $(this).closest('tr').remove(); recalc(); });
                $(document).on('change', '.item-select', function () {
                    var $o = $(this).find('option:selected'), $tr = $(this).closest('tr');
                    var formula = $o.data('formula');
                    if (formula) {
                        $tr.find('.formula-input').val(formula);
                        var v = evalFormula(formula, parseFloat($('#basic_amount').val()) || 0);
                        if (v !== null) $tr.find('.amount-input').val(v.toFixed(2));
                    } else if ($o.data('amount') !== undefined && $o.val()) {
                        $tr.find('.amount-input').val($o.data('amount'));
                    }
                    recalc();
                });
                $(document).on('input', '.formula-input', function () {
                    var v = evalFormula($(this).val(), parseFloat($('#basic_amount').val()) || 0);
                    if (v !== null) $(this).closest('tr').find('.amount-input').val(v.toFixed(2));
                    recalc();
                });
                $(document).on('input change', '#basic_amount', function () {
                    BASIC_TOUCHED = true;
                    var basic = parseFloat($(this).val()) || 0;
                    $('.dynamic-row').each(function () {
                        var f = $(this).find('.formula-input').val();
                        if (!f) return;
                        var v = evalFormula(f, basic);
                        if (v !== null) $(this).find('.amount-input').val(v.toFixed(2));
                    });
                    recalc();
                });
                $(document).on('input', '.amount-input', recalc);

                if (!IS_EDIT) {
                    $('#payroll_id').on('change', function () { loadPayrollEmployees($(this).val(), null); });
                    $('#employee_id').on('change', function () { loadEmployee($(this).val()); });
                    if ($('#payroll_id').val()) { loadPayrollEmployees($('#payroll_id').val(), @json($oldEmployeeId)); }
                } else {
                    loadEmployee($('#employee_id').val());
                }

                // مبلغٌ موجبٌ شرطُ الحفظ: أساسيٌّ أو بند استحقاق — يُعلَّم الحقلُ كأخطاء التحقّق
                $('#basic_amount').closest('form').on('submit', function (e) {
                    var basic = parseFloat($('#basic_amount').val()) || 0;
                    if (basic > 0 || sumOf('addition') > 0) return;
                    e.preventDefault();
                    var el = document.getElementById('basic_amount');
                    if (window.HrFormUx) {
                        window.HrFormUx.mark(el, 'أدخل الراتب الأساسي أو أضف بند استحقاقٍ واحداً على الأقل بمبلغ.');
                        window.HrFormUx.focus(el);
                    } else { el.focus(); }
                });

                recalc();
            });
        })();
    </script>
@endpush
