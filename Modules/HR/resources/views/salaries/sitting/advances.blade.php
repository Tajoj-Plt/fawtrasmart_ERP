@extends('hr::master')

@section('title', 'إعدادات السلف')

@section('content')
    @section('page-actions')
        <a href="{{ route('SalarySittings.index') }}" class="btn btn-outline-secondary">
            <i class="feather icon-arrow-right"></i> الإعدادات
        </a>
        <button type="submit" form="advanceSettingsForm" class="btn btn-primary">
            <i class="feather icon-save"></i> حفظ
        </button>
    @endsection

    @include('stock::components.page-head', [
        'title' => 'إعدادات السلف',
        'icon' => 'arrow-up-circle',
        'crumbs' => ['الإعدادات' => route('SalarySittings.index'), 'إعدادات السلف' => null],
    ])

    <div class="content-body">
        @include('layouts.alerts.error')
        @include('layouts.alerts.success')
        @include('stock::components.form-errors')

        @include('hr::components.guide', [
            'key' => 'hr-advance-settings',
            'title' => 'من يظهر «موظفاً معتمداً» في طلب السلفة؟',
            'lead' => 'يختار الموظفُ عند طلب السلفة معتمِداً يصله الطلب. والقائمةُ تُبنى من شرطين معاً — لا من مستخدمي النظام كلِّهم.',
            'blocks' => [
                'الشرطان' => [
                    'الصلاحية: أن يملك دورُه «الموافقة على طلبات السلف» — وهو ما يشترطه زرُّ الاعتماد نفسُه، فلا يُختار من يُردّ.',
                    'القسم: إن اخترت أقساماً هنا فلا يظهر إلّا موظفوها (قسمُه الأساسي أو عضويّتُه في القسم). وبلا أقسامٍ يكفي الشرطُ الأوّل.',
                ],
                'ما لا يتغيّر' => [
                    'لا يختار الموظفُ نفسَه، ولا يُقبل رقمُ معتمِدٍ من خارج القائمة ولو أُرسل يدوياً.',
                ],
                'الاعتماد من الإشعار' => [
                    'يصل المعتمِدَ بوبُ إشعارٍ بالطلب، وفيه «اعتماد» و«رفض». والاعتمادُ يسأله عن الخزينة ثمّ يصرف السلفة في الخطوة نفسِها — إن كان المفتاحُ أدناه مشغَّلاً ويملك صلاحية «صرف السلف».',
                    'أطفئ المفتاحَ إن أردت فصلَ القرار عن المال: المعتمِدُ يعتمد من الإشعار، والصرفُ من شاشة الطلب بخزينةٍ مختارة.',
                ],
            ],
        ])

        <form action="{{ route('SalarySittings.advances.update') }}" method="POST" id="advanceSettingsForm">
            @csrf
            <div class="card">
                <div class="card-body">
                    <div class="stk-section mb-0">
                        <div class="stk-section__head">
                            <i class="feather icon-users"></i>
                            <h6 class="stk-section__title">أقسام الاعتماد</h6>
                        </div>
                        <div class="form-group">
                            <label for="approver_department_ids">الأقسام التي يُختار منها المعتمِد
                                <i class="feather icon-help-circle stk-tip" title="تقرؤها شاشةُ «طلب سلفة»: قائمةُ «الموظف المعتمد» لا تعرض إلا من في هذه الأقسام ويملك صلاحية الموافقة على السلف، والحفظُ يرفض معتمِداً من خارجها. فارغةً = كلُّ من يملك الصلاحية."></i>
                            </label>
                            @php
                                // بعد فشل التحقّق يُقرأ ما أُرسل — ولو أُفرغت القائمة (لا يُرسَل حقلٌ فارغ)
                                $pickedDepartments = array_map('intval', (array) (session()->hasOldInput() ? old('approver_department_ids', []) : $selected));
                            @endphp
                            <select name="approver_department_ids[]" id="approver_department_ids" class="form-control hr-s2" multiple data-placeholder="كل الأقسام">
                                @foreach ($departments as $department)
                                    <option value="{{ $department->id }}" @selected(in_array((int) $department->id, $pickedDepartments, true))>
                                        {{ $department->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- الصرفُ من بوب الإشعار — عمودُ `payroll_settings.advance_popup_disburse` --}}
                        <div class="form-group mb-0">
                            <label class="stk-switch mb-0">
                                <input type="hidden" name="popup_disburse" value="0">
                                <input type="checkbox" name="popup_disburse" value="1" @checked($popupDisburse)>
                                <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                                <span class="stk-switch__label">
                                    <span class="stk-switch__title">السماح بصرف السلفة من بوب الإشعار</span>
                                    <span class="stk-switch__hint">
                                        مشغَّلاً: «اعتماد» في الإشعار يسأل عن الخزينة — والمعتمِدُ يختار «اعتماد وصرف» أو «اعتماد فقط».
                                        مطفأً: الاعتمادُ وحده، والصرفُ من شاشة الطلب. وصلاحيةُ «صرف السلف» شرطٌ في الحالين.
                                    </span>
                                </span>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="stk-form-foot">
                    <a href="{{ route('SalarySittings.index') }}" class="btn btn-outline-secondary">
                        <i class="feather icon-x"></i> إلغاء
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="feather icon-save"></i> حفظ الإعدادات
                    </button>
                </div>
            </div>
        </form>

        <div class="card stk-table-card">
            <div class="stk-table-head">
                <h6 class="stk-table-head__title">
                    <i class="feather icon-user-check"></i> المعتمِدون بحسب الإعداد المحفوظ
                    <span class="stk-count">{{ $approvers->count() }}</span>
                </h6>
            </div>
            @if ($approvers->isNotEmpty())
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>المستخدم</th>
                                <th>الموظف</th>
                                <th>القسم</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($approvers as $user)
                                <tr>
                                    <td>{{ $user->name }}</td>
                                    <td>{{ $user->employee->full_name ?? '—' }}</td>
                                    <td>{{ optional($departments->firstWhere('id', $user->employee->department_id ?? null))->name ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="stk-empty">
                    <div class="stk-empty__icon"><i class="feather icon-user-x"></i></div>
                    <h6 class="stk-empty__title">لا معتمِد</h6>
                    <p class="stk-empty__hint">لا أحد في الأقسام المختارة يملك صلاحية «الموافقة على طلبات السلف» — لن يستطيع الموظفون تقديم طلب.</p>
                </div>
            @endif
        </div>
    </div>
@endsection
