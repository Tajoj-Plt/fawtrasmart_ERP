@extends('hr::master')

@section('title', 'مسيرات الرواتب')

@section('content')
    <div class="content-body">

        @section('page-actions')
            @permission('payroll.create')
                <a href="{{ route('PayrollProcess.create') }}" class="btn btn-primary">
                    <i class="fas fa-plus"></i> مسير رواتب جديد
                </a>
            @endpermission
        @endsection

        @include('stock::components.page-head', [
            'title' => 'مسيرات الرواتب',
            'icon' => 'layers',
            'sub' => 'مظلة زمنية تجمع الموظفين المشمولين — القسائمُ تُنشأ تحتها، والصرفُ في سجلات الصرف',
            'crumbs' => ['الرئيسية' => url('/'), 'مسيرات الرواتب' => null],
        ])

        {{-- ══════════ إرشادُ الشاشة ══════════ --}}
        @include('hr::components.guide', [
            'key' => 'hr-payroll-process.index',
            'title' => 'ما هذه الشاشة، وما الذي يترتّب على ما فيها؟',
            'lead' => 'المسيرُ يحدّد «من» و«متى»: قائمةُ موظفين محفوظةٌ فيه وفترةُ تغطية. لا يصرف مالاً ولا يكتب قيداً — القسائمُ تُنشأ تحته، والصرفاتُ تتولّد من القسائم.',
            'blocks' => [
                'من يدخل المسير' => [
                    'بالقواعد: كلُّ موظفٍ نشطٍ (أو تحت التجربة) في الفرع المختار **وما تحته** والقسمِ والمسمّى — و«الكل» في أيٍّ منها لا يُضيّق شيئاً.',
                    'أو بالاختيار: موظفون بأعيانهم. وفي الحالتين يُحفظ الموظفون في المسير لحظةَ الحفظ، فلا يتغيّر من فيه بإضافة موظفٍ لاحقاً إلا بالتعديل.',
                ],
                'دورة المسير' => [
                    'يُحفظ «مسودة»، واعتمادُه يجعله نشطاً فتُنشأ تحته القسائم. وإيقافُه يمنع توليد صرفاتٍ جديدة من قسائمه.',
                    'ولا يُحذف مسيرٌ له قسائم: يُوقَف.',
                ],
                'ما تراه' => [
                    'العدّاداتُ والقوائمُ محدودةٌ بنطاق صلاحيتك وبفرع الشريط العلويّ — فقد يختلف ما يراه مستخدمان.',
                ],
            ],
            'facts' => [
                'المسيرُ لا يصرف ولا يقيّد',
                'الفرعُ يشمل ما تحته',
                'تصديرُ Excel يطابق شاشةَ المسير صفّاً بصفّ',
            ],
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')
        @if (session('warning'))
            <div class="alert alert-warning">{{ session('warning') }}</div>
        @endif

        {{-- المقاييس --}}
        @php
            $statTotal = (int) collect($stats)->sum();
        @endphp
        <div class="card">
            <div class="stk-metrics">
                <div class="stk-metric">
                    <span class="stk-metric__icon"><i class="feather icon-layers"></i></span>
                    <span class="stk-metric__value">{{ $statTotal }}</span>
                    <span class="stk-metric__label">إجمالي المسيرات</span>
                </div>
                <div class="stk-metric stk-metric--success">
                    <span class="stk-metric__icon"><i class="feather icon-check-circle"></i></span>
                    <span class="stk-metric__value">{{ (int) ($stats['active'] ?? 0) }}</span>
                    <span class="stk-metric__label">نشطة (معتمدة)</span>
                </div>
                <div class="stk-metric stk-metric--warning">
                    <span class="stk-metric__icon"><i class="feather icon-clock"></i></span>
                    <span class="stk-metric__value">{{ (int) ($stats['draft'] ?? 0) }}</span>
                    <span class="stk-metric__label">مسودات</span>
                </div>
                <div class="stk-metric">
                    <span class="stk-metric__icon"><i class="feather icon-pause-circle"></i></span>
                    <span class="stk-metric__value">{{ (int) ($stats['inactive'] ?? 0) }}</span>
                    <span class="stk-metric__label">موقوفة</span>
                </div>
            </div>
        </div>

        {{-- البحث والتصفية --}}
        @php
            $filterKeys = ['name', 'status', 'employee_id', 'department_id', 'jop_title_id', 'start_date', 'end_date', 'registration_date_from', 'registration_date_to', 'branch_id'];
            $hasFilters = collect($filterKeys)->contains(fn ($k) => filled(request($k)));
            $advancedOpen = collect(['jop_title_id', 'start_date', 'end_date', 'registration_date_from', 'registration_date_to', 'branch_id'])->contains(fn ($k) => filled(request($k)));
        @endphp
        <div class="card stk-filters">
            <div class="card-body">
                <div class="stk-filters__head">
                    <h6 class="stk-filters__title"><i class="feather icon-filter"></i> بحث وتصفية</h6>
                    @if ($hasFilters)
                        <span class="stk-count">فلاتر مفعّلة</span>
                    @endif
                </div>

                <form method="GET" action="{{ route('PayrollProcess.index') }}">
                    <div class="row">
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="f_name">اسم المسير</label>
                                <input type="text" id="f_name" name="name" class="form-control" value="{{ request('name') }}" placeholder="اكتب للبحث…">
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="f_status">الحالة</label>
                                <select id="f_status" name="status" class="form-control hr-s2">
                                    <option value="">الكل</option>
                                    @foreach (\App\Models\Payroll::statuses() as $key => $label)
                                        <option value="{{ $key }}" {{ request('status') === $key ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="f_department">القسم</label>
                                <select id="f_department" name="department_id" class="form-control hr-s2">
                                    <option value="">الكل</option>
                                    @foreach ($departments as $d)
                                        <option value="{{ $d->id }}" {{ (string) request('department_id') === (string) $d->id ? 'selected' : '' }}>{{ $d->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="f_employee">الموظف <i class="feather icon-help-circle stk-tip" title="المسيرات التي تضمّ أحد الموظفين المختارين"></i></label>
                                <select id="f_employee" class="form-control pp-s2" name="employee_id[]" multiple data-placeholder="الكل">
                                    @foreach ($employees as $emp)
                                        <option value="{{ $emp->id }}" {{ in_array($emp->id, array_map('intval', (array) request('employee_id', []))) ? 'selected' : '' }}>
                                            {{ trim($emp->first_name.' '.$emp->middle_name) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="collapse {{ $advancedOpen ? 'show' : '' }}" id="ppAdvanced">
                        <div class="row">
                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label for="f_title">المسمى الوظيفي</label>
                                    <select id="f_title" name="jop_title_id" class="form-control hr-s2">
                                        <option value="">الكل</option>
                                        @foreach ($jobTitles as $j)
                                            <option value="{{ $j->id }}" {{ (string) request('jop_title_id') === (string) $j->id ? 'selected' : '' }}>{{ $j->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label for="f_start">بداية الفترة (من)</label>
                                    <input type="date" id="f_start" name="start_date" class="form-control" value="{{ request('start_date') }}">
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label for="f_end">نهاية الفترة (إلى)</label>
                                    <input type="date" id="f_end" name="end_date" class="form-control" value="{{ request('end_date') }}">
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label for="f_reg_from">تاريخ التسجيل (من – إلى)</label>
                                    <div class="d-flex" style="gap:.4rem">
                                        <input type="date" id="f_reg_from" name="registration_date_from" class="form-control" value="{{ request('registration_date_from') }}">
                                        <input type="date" name="registration_date_to" class="form-control" value="{{ request('registration_date_to') }}" aria-label="تاريخ التسجيل إلى">
                                    </div>
                                </div>
                            </div>
                            @include('reports.partials.affiliation-tree', [
                                'treeNodes' => \Modules\HR\Support\BranchTree::treeNodes(),
                                'name' => 'branch_id',
                                'showExact' => false,
                                'label' => 'تابع لـ',
                            ])
                        </div>
                    </div>

                    <div class="stk-filters__foot">
                        <a class="btn btn-outline-secondary" data-toggle="collapse" data-bs-toggle="collapse" href="#ppAdvanced" role="button" aria-expanded="{{ $advancedOpen ? 'true' : 'false' }}">
                            <i class="feather icon-sliders"></i> بحث متقدم
                        </a>
                        <a href="{{ route('PayrollProcess.index') }}" class="btn btn-outline-secondary">
                            <i class="feather icon-rotate-ccw"></i> إعادة تعيين
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="feather icon-search"></i> بحث
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- القائمة --}}
        <div class="card stk-table-card">
            @if ($payrolls->count() > 0)
                <div class="stk-table-head">
                    <h6 class="stk-table-head__title">
                        <i class="feather icon-list"></i> قائمة المسيرات
                        <span class="stk-count">{{ $payrolls->total() }}</span>
                    </h6>
                    <label class="stk-perpage mb-0">
                        عرض
                        <select class="form-control form-control-sm stk-perpage__select d-inline-block w-auto"
                            onchange="window.location.href='{{ url()->current() }}?{{ http_build_query(request()->except(['per_page', 'page'])) }}&per_page='+this.value;">
                            @foreach ([10, 25, 50, 100] as $n)
                                <option value="{{ $n }}" {{ (int) request('per_page', 10) === $n ? 'selected' : '' }}>{{ $n }}</option>
                            @endforeach
                        </select>
                        <span class="stk-perpage__unit">سجل</span>
                    </label>
                </div>

                @include('hr::salaries.payroll_ process.partials.table', ['payrolls' => $payrolls])

                @if ($payrolls->hasPages())
                    <div class="stk-table-foot">
                        <p class="stk-table-foot__count">
                            عرض {{ $payrolls->firstItem() }}–{{ $payrolls->lastItem() }} من {{ $payrolls->total() }}
                        </p>
                        {{ $payrolls->links() }}
                    </div>
                @endif
            @else
                <div class="card-body">
                    <div class="stk-empty">
                        <div class="stk-empty__icon"><i class="feather icon-layers"></i></div>
                        <h5 class="stk-empty__title">لا توجد مسيرات رواتب</h5>
                        <p class="stk-empty__hint">
                            @if ($hasFilters)
                                لم يطابق البحث أيّ مسير — جرّب معايير أوسع
                            @else
                                ابدأ بإنشاء أول مسير رواتب يجمع الموظفين تحت فترة تغطية واحدة
                            @endif
                        </p>
                        @if ($hasFilters)
                            <a href="{{ route('PayrollProcess.index') }}" class="btn btn-outline-secondary">
                                <i class="feather icon-rotate-ccw"></i> إزالة الفلاتر
                            </a>
                        @else
                            @permission('payroll.create')
                                <a href="{{ route('PayrollProcess.create') }}" class="btn btn-primary">
                                    <i class="feather icon-plus"></i> إنشاء مسير
                                </a>
                            @endpermission
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
    @include('hr::components.table-menu')
    <script>
        $(function () {
            $('select.pp-s2').each(function () {
                if ($(this).hasClass('select2-hidden-accessible')) return;
                $(this).select2({ dir: 'rtl', width: '100%', allowClear: true, placeholder: $(this).data('placeholder') || 'الكل' });
            });
        });
    </script>
@endpush
