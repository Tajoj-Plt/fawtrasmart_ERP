@extends('hr::master')

@section('title', 'ربط قائمة العطل')

@section('content')
<div class="hr-hints">
<div class="content-body">
    @include('stock::components.page-head', [
        'title' => 'ربط «'.$holiday_list->name.'»',
        'icon' => 'link',
        'sub' => 'على من تسري هذه العطل — قسم، أو مسمى وظيفي، أو فرع، أو موظفون بأعينهم.',
        'crumbs' => [
            'الرئيسية' => url('/'),
            'قوائم العطل' => route('holiday_lists.index'),
            $holiday_list->name => route('holiday_lists.show', $holiday_list->id),
            'الربط' => null,
        ],
        'actions' => '<a href="'.route('holiday_lists.show', $holiday_list->id).'" class="btn btn-outline-secondary"><i class="feather icon-arrow-right"></i> رجوع للقائمة</a>',
    ])

    @include('layouts.alerts.error')
    @include('layouts.alerts.success')

    @if ($assignments->isEmpty())
        <div class="alert alert-info">
            <i class="feather icon-users"></i>
            لا يوجد ربط — هذه العطل <strong>تسري على كل الموظفين</strong>. أضف ربطاً فقط إن كانت لفئةٍ بعينها.
        </div>
    @else
        <div class="alert alert-warning">
            <i class="feather icon-filter"></i>
            هذه العطل تسري فقط على من ينطبق عليه <strong>أيٌّ</strong> من الربط أدناه. احذف كل الربط لتعود لكل الموظفين.
        </div>
    @endif

    <div class="row">
        <div class="col-lg-5 col-12">
            <div class="card">
                <div class="card-header"><h4 class="card-title"><i class="feather icon-plus-circle"></i> إضافة ربط</h4></div>
                <div class="card-body">
                    @if ($errors->any())
                        <div class="stk-form-errors alert alert-danger" role="alert">
                            <ul class="stk-form-errors__list mb-0">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('holiday_lists.add_holyday_employees', $holiday_list->id) }}" id="assignForm">
                        @csrf
                        @php $target = old('target', 'department'); @endphp

                        <label class="form-label stk-req">اربط حسب</label>
                        <div class="btn-group w-100 mb-2 hl-targets" role="group">
                            @foreach (['department' => ['قسم', 'layers'], 'job_title' => ['مسمى وظيفي', 'briefcase'], 'branch' => ['فرع', 'home'], 'employees' => ['موظفون', 'user']] as $key => [$label, $icon])
                                <input type="radio" class="btn-check d-none" name="target" id="target_{{ $key }}" value="{{ $key }}" @checked($target === $key)>
                                <label class="btn btn-outline-primary {{ $target === $key ? 'active' : '' }}" for="target_{{ $key }}" data-target="{{ $key }}">
                                    <i class="feather icon-{{ $icon }}"></i> {{ $label }}
                                </label>
                            @endforeach
                        </div>

                        <div class="hl-pane form-group" data-pane="department">
                            <label class="form-label">الأقسام</label>
                            <select name="department_ids[]" class="form-control hl-s2" multiple data-placeholder="اختر قسماً أو أكثر">
                                @foreach ($departments as $item)
                                    <option value="{{ $item->id }}" @selected(in_array($item->id, (array) old('department_ids', [])))>{{ $item->name }}</option>
                                @endforeach
                            </select>
                            <small class="stk-help">كل موظفي القسم — ومن يُنقل إليه لاحقاً.</small>
                        </div>

                        <div class="hl-pane form-group" data-pane="job_title">
                            <label class="form-label">المسميات الوظيفية</label>
                            <select name="job_title_ids[]" class="form-control hl-s2" multiple data-placeholder="اختر مسمى أو أكثر">
                                @foreach ($job_titles as $item)
                                    <option value="{{ $item->id }}" @selected(in_array($item->id, (array) old('job_title_ids', [])))>{{ $item->name }}</option>
                                @endforeach
                            </select>
                            <small class="stk-help">كل من يحمل المسمى في أي قسم.</small>
                        </div>

                        <div class="hl-pane form-group" data-pane="branch">
                            <label class="form-label">الفروع</label>
                            <select name="branch_ids[]" class="form-control hl-s2" multiple data-placeholder="اختر فرعاً أو أكثر">
                                @foreach ($branches as $item)
                                    <option value="{{ $item->id }}" @selected(in_array($item->id, (array) old('branch_ids', [])))>{{ $item->name }}</option>
                                @endforeach
                            </select>
                            <small class="stk-help">كل موظفي الفرع.</small>
                        </div>

                        <div class="hl-pane form-group" data-pane="employees">
                            <label class="form-label">الموظفون</label>
                            <select name="employee_ids[]" class="form-control hl-s2" multiple data-placeholder="ابحث بالاسم">
                                @foreach ($employees as $emp)
                                    <option value="{{ $emp->id }}" @selected(in_array($emp->id, (array) old('employee_ids', [])))>
                                        {{ $emp->full_name }}{{ $emp->department ? ' — '.$emp->department->name : '' }}
                                    </option>
                                @endforeach
                            </select>
                            <small class="stk-help">موظفون بأعينهم، بغضّ النظر عن قسمهم.</small>
                        </div>

                        <button type="submit" class="btn btn-primary w-100"><i class="feather icon-check"></i> إضافة الربط</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-7 col-12">
            <div class="card stk-table-card">
                <div class="stk-table-head">
                    <h6 class="stk-table-head__title">
                        <i class="feather icon-link"></i> الربط الحالي
                        <span class="stk-count">{{ $assignments->count() }}</span>
                    </h6>
                </div>

                @if ($assignments->isNotEmpty())
                    <div class="table-responsive">
                        <table class="table table-hover table-striped mb-0">
                            <thead>
                                <tr>
                                    <th>النوع</th>
                                    <th>على من</th>
                                    <th class="text-center">الموظفون</th>
                                    <th class="text-center" style="width:60px"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($assignments as $item)
                                    <tr>
                                        <td><span class="stk-state stk-state--{{ $item['count'] > 0 ? 'on' : 'off' }}">{{ $item['label'] }}</span></td>
                                        <td>
                                            @if ($item['type'] === 'employees')
                                                <div class="d-flex flex-wrap" style="gap:6px">
                                                    @foreach ($item['employees'] as $emp)
                                                        <form method="POST" action="{{ route('holiday_lists.remove_assignment', [$holiday_list->id, $item['row']->id]) }}" class="m-0">
                                                            @csrf @method('DELETE')
                                                            <input type="hidden" name="employee_id" value="{{ $emp->id }}">
                                                            <span class="badge badge-light-primary">
                                                                {{ $emp->full_name }}
                                                                <button type="submit" class="btn btn-link p-0 ms-25 text-danger" title="إزالة" onclick="return confirm('إزالة {{ $emp->full_name }} من هذه القائمة؟')">×</button>
                                                            </span>
                                                        </form>
                                                    @endforeach
                                                </div>
                                            @else
                                                {{ $item['value'] }}
                                            @endif
                                        </td>
                                        <td class="text-center stk-num">{{ $item['count'] }}</td>
                                        <td class="text-center">
                                            <form method="POST" action="{{ route('holiday_lists.remove_assignment', [$holiday_list->id, $item['row']->id]) }}" class="m-0"
                                                  onsubmit="return confirm('حذف هذا الربط؟')">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="حذف الربط"><i class="feather icon-trash-2"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="card-body">
                        <div class="stk-empty">
                            <div class="stk-empty__icon"><i class="feather icon-users"></i></div>
                            <h5 class="stk-empty__title">تسري على كل الموظفين</h5>
                            <p class="stk-empty__hint">لا يوجد ربط. أضف قسماً أو مسمى أو فرعاً أو موظفين لتقصرها عليهم.</p>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
</div>
@include('components.hint-tips')
@endsection

@push('scripts')
<script>
(function () {
    const panes = document.querySelectorAll('.hl-pane');
    const show = (target) => {
        panes.forEach(p => { p.style.display = p.dataset.pane === target ? '' : 'none'; });
        document.querySelectorAll('.hl-targets label').forEach(l => l.classList.toggle('active', l.dataset.target === target));
    };
    document.querySelectorAll('input[name="target"]').forEach(r => r.addEventListener('change', () => show(r.value)));
    show((document.querySelector('input[name="target"]:checked') || {}).value || 'department');

    if (window.jQuery && jQuery.fn.select2) {
        jQuery('.hl-s2').each(function () {
            jQuery(this).select2({ dir: 'rtl', width: '100%', placeholder: this.dataset.placeholder || 'اختر', closeOnSelect: false });
        });
    }
})();
</script>
@endpush
