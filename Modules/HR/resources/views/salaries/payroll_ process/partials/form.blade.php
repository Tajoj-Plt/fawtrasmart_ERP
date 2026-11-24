{{--
    نموذجُ المسير — مشتركٌ بين الإنشاء والتعديل.

    @param \App\Models\Payroll|null $payroll
    @param \Illuminate\Support\Collection $employees     موظفو قائمة «الاختيار» (نشطون، ضمن النطاق)
    @param \Illuminate\Support\Collection $departments
    @param \Illuminate\Support\Collection $jop_titles
    @param array $selectedEmployees  موظفو المسير المحفوظون (تعديل)
    @param array $lockedEmployees    موظفون لهم قسائم في المسير — لا يُفكّون عنه

    القيمُ كلُّها من `old()` أوّلاً — فخطأُ تحقّقٍ لا يُضيّع ما أُدخل، ولا
    اختيارَ الموظفين في Select2 ولا فرعَ الشجرة.
--}}
@php
    $isEdit = isset($payroll) && $payroll;
    $selection = (string) old('selection', $isEdit ? ((int) $payroll->select_emp_role === 2 ? 2 : 1) : 1);
    $oldEmployees = array_map('intval', (array) old('employee_id', $selectedEmployees ?? []));
    $lockedEmployees = $lockedEmployees ?? [];
    $oldDepartment = (string) old('department_id', $isEdit ? ($payroll->department_id ?? '') : '');
    $oldTitle = (string) old('jop_title_id', $isEdit ? ($payroll->jop_title_id ?? '') : '');
    $oldBranch = old('branch_id', $isEdit ? ($payroll->branch_id ?? '') : '');
    $oldCycle = (string) old('receiving_cycle', $isEdit ? ($payroll->receiving_cycle ?? '') : '1');
    $cycles = [1 => 'شهري', 2 => 'أسبوعي', 3 => 'سنوي', 4 => 'ربع سنوي', 5 => 'كل أسبوعين'];
@endphp

<style>
    .pp-mode { display:flex; gap:.75rem; align-items:flex-start; cursor:pointer; margin:0; height:100%;
        padding: var(--stk-4); border:1px solid var(--stk-line); border-radius: var(--stk-r); background: var(--app-surface);
        transition: border-color .15s ease, background .15s ease; }
    .pp-mode:hover { border-color: color-mix(in srgb, var(--app-primary) 45%, transparent); }
    .pp-mode.is-active { border-color: var(--app-primary); background: var(--app-primary-soft); }
    .pp-mode input { margin-top:.3rem; }
    .pp-mode__title { display:block; font-weight:700; color: var(--app-text); font-size:.9rem; }
    .pp-mode__hint { display:block; font-size:.78rem; color: var(--app-muted); margin-top:.15rem; }
    .pp-preview { max-height: 240px; overflow-y:auto; }
    .pp-preview .stk-tag { margin: 0 0 .3rem .3rem; }
</style>

<div class="card">
    <div class="stk-table-head">
        <h6 class="stk-table-head__title"><i class="feather icon-file-text"></i> بيانات المسير</h6>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="form-group col-md-6">
                <label for="pp_name" class="stk-req">اسم المسير</label>
                <input type="text" id="pp_name" class="form-control" name="name" maxlength="255"
                       value="{{ old('name', $isEdit ? $payroll->name : '') }}" placeholder="مثال: مسير رواتب {{ now()->translatedFormat('F Y') }}" required>
            </div>
            <div class="form-group col-md-3">
                <label for="pp_registration_date">تاريخ التسجيل</label>
                <input type="date" id="pp_registration_date" class="form-control" name="registration_date"
                       value="{{ old('registration_date', $isEdit ? optional($payroll->registration_date)->format('Y-m-d') : date('Y-m-d')) }}">
            </div>
            <div class="form-group col-md-3">
                <label for="pp_cycle">دورة القبض <i class="feather icon-help-circle stk-tip" title="وصفٌ للمسير يظهر في القائمة والطباعة — تكرارُ الصرف الفعليّ يُضبط في كل قسيمة"></i></label>
                <select id="pp_cycle" name="receiving_cycle" class="form-control hr-s2">
                    <option value="">— غير محدّدة —</option>
                    @foreach ($cycles as $k => $label)
                        <option value="{{ $k }}" {{ $oldCycle === (string) $k ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-md-3">
                <label for="pp_start_date">تاريخ البدء <i class="feather icon-help-circle stk-tip" title="لا تُنشأ قسيمةٌ تحت المسير بتاريخ بدايةٍ قبل هذا التاريخ"></i></label>
                <input type="date" id="pp_start_date" class="form-control" name="start_date"
                       value="{{ old('start_date', $isEdit ? optional($payroll->start_date)->format('Y-m-d') : '') }}">
            </div>
            <div class="form-group col-md-3">
                <label for="pp_end_date">تاريخ النهاية</label>
                <input type="date" id="pp_end_date" class="form-control" name="end_date"
                       value="{{ old('end_date', $isEdit ? optional($payroll->end_date)->format('Y-m-d') : '') }}">
                <small class="stk-help">فارغ = مسيرٌ مفتوح</small>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="stk-table-head">
        <h6 class="stk-table-head__title"><i class="feather icon-users"></i> الموظفون المشمولون</h6>
        <span class="stk-count" id="pp_count_badge" style="display:none"></span>
    </div>
    <div class="card-body">
        <div class="row form-group">
            <div class="col-md-6 mb-2">
                <label class="pp-mode {{ $selection === '1' ? 'is-active' : '' }}" id="card_rules">
                    <input type="radio" name="selection" value="1" {{ $selection === '1' ? 'checked' : '' }}>
                    <span>
                        <span class="pp-mode__title">اختيار بالقواعد</span>
                        <span class="pp-mode__hint">كلُّ موظفٍ نشطٍ في الفرع (وما تحته) والقسم والمسمّى — و«الكل» لا يُضيّق</span>
                    </span>
                </label>
            </div>
            <div class="col-md-6 mb-2">
                <label class="pp-mode {{ $selection === '2' ? 'is-active' : '' }}" id="card_specific">
                    <input type="radio" name="selection" value="2" {{ $selection === '2' ? 'checked' : '' }}>
                    <span>
                        <span class="pp-mode__title">اختيار موظفين محدَّدين</span>
                        <span class="pp-mode__hint">قائمةٌ تختارها بنفسك من الموظفين النشطين</span>
                    </span>
                </label>
            </div>
        </div>

        {{-- القواعد --}}
        <div id="rules_block" class="stk-section" style="{{ $selection === '1' ? '' : 'display:none' }}">
            <div class="stk-section__head">
                <i class="feather icon-sliders"></i>
                <h6 class="stk-section__title">قواعد الاختيار</h6>
            </div>
            <div class="row">
                @include('reports.partials.affiliation-tree', [
                    'treeNodes' => \Modules\HR\Support\BranchTree::treeNodes(array_filter([$isEdit ? $payroll->branch_id : null])),
                    'name' => 'branch_id',
                    'fieldId' => 'branch_id',
                    'value' => $oldBranch,
                    'showExact' => false,
                    'label' => 'تابع لـ (الفرع)',
                    'treeInputClass' => 'pp-rule',
                ])
                <div class="form-group col-md-6">
                    <label for="pp_department">القسم</label>
                    <select id="pp_department" class="form-control pp-rule hr-s2" name="department_id">
                        <option value="">كل الأقسام</option>
                        @foreach ($departments as $d)
                            <option value="{{ $d->id }}" {{ $oldDepartment === (string) $d->id ? 'selected' : '' }}>{{ $d->name }}</option>
                        @endforeach
                    </select>
                    <x-create-first
                        :count="count($departments ?? [])"
                        route="department.create"
                        permission="departments.create"
                        :plan="\App\Constants\planFeatures\HrPlanFeatures::DEPARTMENTS"
                        label="قسماً"
                        hint="الأقسام جزءٌ من الهيكل التنظيمي، ولا قسم مسجَّل بعد." />
                </div>
                <div class="form-group col-md-6">
                    <label for="pp_title">المسمى الوظيفي</label>
                    <select id="pp_title" class="form-control pp-rule hr-s2" name="jop_title_id">
                        <option value="">كل المسميات الوظيفية</option>
                        @foreach ($jop_titles as $j)
                            <option value="{{ $j->id }}" {{ $oldTitle === (string) $j->id ? 'selected' : '' }}>{{ $j->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="stk-choice mt-1" id="pp_preview_box">
                <div class="d-flex align-items-center justify-content-between flex-wrap" style="gap:.5rem">
                    <strong><i class="feather icon-eye"></i> من ستضمّهم القواعد</strong>
                    <span class="stk-count" id="pp_preview_count">…</span>
                </div>
                <div class="pp-preview mt-2" id="pp_preview_list"><span class="stk-help">جارٍ الحساب…</span></div>
            </div>
        </div>

        {{-- موظفون محدَّدون --}}
        <div id="specific_block" class="stk-section" style="{{ $selection === '2' ? '' : 'display:none' }}">
            <div class="stk-section__head">
                <i class="feather icon-user-check"></i>
                <h6 class="stk-section__title">الموظفون المختارون</h6>
            </div>
            <div class="form-group">
                <label for="pp_employees" class="stk-req">الموظفون</label>
                <select id="pp_employees" class="form-control pp-employee" name="employee_id[]" multiple data-placeholder="ابحث بالاسم أو الرقم أو القسم…">
                    @foreach ($employees as $emp)
                        <option value="{{ $emp->id }}"
                            data-name="{{ trim($emp->first_name.' '.$emp->middle_name) }}"
                            data-dept="{{ optional($emp->department)->name }}"
                            data-job="{{ optional($emp->job_title)->name }}"
                            data-branch="{{ optional($emp->branch)->name }}"
                            {{ in_array((int) $emp->id, $oldEmployees, true) ? 'selected' : '' }}>
                            #{{ $emp->id }} — {{ trim($emp->first_name.' '.$emp->middle_name) }}
                        </option>
                    @endforeach
                </select>
                <x-create-first
                    :count="count($employees ?? [])"
                    route="employee.create"
                    permission="employees.create"
                    :plan="\App\Constants\planFeatures\HrPlanFeatures::EMPLOYEES"
                    label="موظفاً"
                    hint="العملية تقع على موظف، ولا موظف مسجَّل بعد." />
                <small class="stk-help">يمكن اختيار عدّة موظفين — البحث بالاسم أو الرقم أو القسم أو الفرع</small>
            </div>
        </div>

        @if (! empty($lockedEmployees))
            <div class="alert alert-info mb-0 mt-2">
                <i class="feather icon-lock"></i>
                {{ count($lockedEmployees) }} موظفاً لهم قسائم في هذا المسير — يبقون فيه ولو لم تشملهم القواعد أو الاختيار الجديد.
            </div>
        @endif
    </div>

    <div class="stk-form-foot">
        @if (hasPermission($isEdit ? 'payroll.show' : 'payroll.index'))
            <a href="{{ $isEdit ? route('PayrollProcess.show', $payroll->id) : route('PayrollProcess.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-x"></i> إلغاء
            </a>
        @endif
        <button type="submit" class="btn btn-primary">
            <i class="feather icon-save"></i> {{ $isEdit ? 'حفظ التعديلات' : 'حفظ المسير' }}
        </button>
    </div>
</div>

@push('scripts')
    <script>
        (function () {
            var PREVIEW_URL = @json(route('PayrollProcess.previewEmployees'));

            function esc(s) { return $('<div>').text(s == null ? '' : String(s)).html(); }

            function formatEmployee(opt) {
                if (!opt.id) return opt.text;
                var $o = $(opt.element);
                var meta = [$o.data('dept'), $o.data('job'), $o.data('branch')].filter(Boolean).map(esc).join(' · ');
                return $('<div><strong>#' + esc(opt.id) + ' — ' + esc($o.data('name') || opt.text) + '</strong>' +
                    (meta ? '<div class="stk-help" style="margin:0">' + meta + '</div>' : '') + '</div>');
            }

            function matcher(params, data) {
                if ($.trim(params.term || '') === '') return data;
                if (!data.element) return null;
                var $o = $(data.element);
                var hay = [data.id, $o.data('name'), $o.data('dept'), $o.data('job'), $o.data('branch')].join(' ').toLowerCase();
                return hay.indexOf(params.term.toLowerCase()) >= 0 ? data : null;
            }

            function initEmployees() {
                var $s = $('#pp_employees');
                if (!$s.length || $s.hasClass('select2-hidden-accessible') || !$.fn.select2) return;
                $s.select2({
                    dir: 'rtl', width: '100%', allowClear: true,
                    placeholder: $s.data('placeholder'),
                    templateResult: formatEmployee,
                    templateSelection: function (opt) { return opt.id ? '#' + opt.id + ' — ' + ($(opt.element).data('name') || opt.text) : opt.text; },
                    matcher: matcher
                });
            }

            var previewTimer = null, previewXhr = null, submitting = false;
            function loadPreview() {
                clearTimeout(previewTimer);
                if (submitting) return;
                previewTimer = setTimeout(function () {
                    if (previewXhr) previewXhr.abort();
                    var params = {
                        branch_id: $('#branch_id').val() || '',
                        department_id: $('#pp_department').val() || '',
                        jop_title_id: $('#pp_title').val() || ''
                    };
                    $('#pp_preview_count').text('…');
                    previewXhr = $.getJSON(PREVIEW_URL, params);
                    previewXhr.done(function (res) {
                        $('#pp_preview_count').text(res.count + ' موظف');
                        $('#pp_count_badge').text(res.count + ' موظف').show();
                        if (!res.count) {
                            $('#pp_preview_list').html('<span class="text-danger small"><i class="feather icon-alert-triangle"></i> لا يوجد موظفٌ نشطٌ ينطبق على هذه القواعد — لن يُحفظ مسيرٌ فارغ.</span>');
                            return;
                        }
                        var html = res.employees.map(function (e) {
                            var meta = [e.department, e.job_title, e.branch].filter(Boolean).join(' · ');
                            return '<span class="stk-tag" title="' + esc(meta) + '">#' + esc(e.id) + ' ' + esc(e.name) + '</span>';
                        }).join('');
                        $('#pp_preview_list').html(html);
                    }).fail(function (xhr, status) {
                        if (status === 'abort') return;
                        $('#pp_preview_count').text('—');
                        $('#pp_preview_list').html('<span class="text-muted small">تعذّر حساب المعاينة — الحفظُ يحسبها على الخادم على كلّ حال.</span>');
                    });
                }, 200);
            }

            function toggle() {
                var rules = $('input[name="selection"][value="1"]').is(':checked');
                $('#rules_block').toggle(rules);
                $('#specific_block').toggle(!rules);
                $('#card_rules').toggleClass('is-active', rules);
                $('#card_specific').toggleClass('is-active', !rules);

                // الحقلُ المعطَّل لا يُرسَل: قواعدُ الوضع الآخر لا تختلط بطلب هذا
                $('.pp-rule').prop('disabled', !rules);
                $('#rules_block select').prop('disabled', !rules);
                $('#pp_employees').prop('disabled', rules).prop('required', !rules);

                if (rules) {
                    loadPreview();
                    $('#pp_count_badge').show();
                } else {
                    initEmployees();
                    var n = ($('#pp_employees').val() || []).length;
                    $('#pp_count_badge').text(n + ' موظف').toggle(n > 0);
                }
            }

            $(function () {
                /* طلبُ معاينةٍ يصل الخادمَ بعد الحفظ يستهلك رسائلَ الخطأ والقيمَ
                   القديمة المُمرَّرة للصفحة التالية — فيُلغى عند الإرسال. */
                $('#rules_block').closest('form').on('submit', function () {
                    submitting = true;
                    clearTimeout(previewTimer);
                    if (previewXhr) previewXhr.abort();
                });
                $(document).on('change', 'input[name="selection"]', toggle);
                $(document).on('change', '#branch_id, #pp_department, #pp_title', function () {
                    if ($('input[name="selection"][value="1"]').is(':checked')) loadPreview();
                });
                $(document).on('change', '#pp_employees', function () {
                    var n = ($(this).val() || []).length;
                    $('#pp_count_badge').text(n + ' موظف').toggle(n > 0);
                });
                // شجرةُ «تابع لـ» تُبنى بعد تحميل select2 — نُعيد ضبط التعطيل بعدها
                setTimeout(toggle, 0);
                setTimeout(toggle, 700);
            });
        })();
    </script>
@endpush
