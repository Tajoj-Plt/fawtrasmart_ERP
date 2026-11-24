@extends('hr::master')

@section('title')
    أدارة الموظفين
@stop

@section('page-actions')
    @permission('employees.export')
        <div class="btn-group">
            <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="employeeActionsDropdown"
                data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-ellipsis-h"></i>
                <span class="d-none d-md-inline">إجراءات</span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="employeeActionsDropdown">
                <li><h6 class="dropdown-header">التصدير</h6></li>
                <li>
                    <a class="dropdown-item" href="{{ route('employee.export_view') }}">
                        <i class="fas fa-file-export me-2 text-success"></i>تصدير الموظفين
                    </a>
                </li>
                {{-- ورابطُ «المؤرشفون» معلَّقٌ: لا شيء يُؤرشَف الآن، والشاشةُ ومسارُها
                     باقيان — يُعاد بندَ «الإدارة» هنا بصلاحية employees.archive. --}}
            </ul>
        </div>
    @endpermission
    @permission('employees.create')
        <a href="{{ route('employee.create') }}" class="btn btn-primary">
            <i class="fas fa-plus"></i>
            <span class="d-none d-md-inline">إضافة موظف</span>
        </a>
    @endpermission
@endsection

@section('content')
    {{-- ترويسةُ نظام التصميم — كانت مكتوبةً يدوياً بستّة مستويات
         من `div` وفتاتِ تنقّلٍ رابطُه الأوّل فارغ. --}}
    @include('stock::components.page-head', [
        'title' => 'أدارة الموظفين',
        'crumbs' => ['الرئيسيه' => null, 'عرض' => null],
    ])

    <div class="content-body">
        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        {{-- ══════════ إرشادُ الشاشة ══════════ --}}
        @include('hr::components.guide', [
            'key' => 'hr-employees.index',
            'title' => 'ما هذه الشاشة، وما الذي يترتّب على ما فيها؟',
            'lead' => 'هذه سجلّاتُ الموظفين. كلُّ سطرٍ ملفُّ موظفٍ تتفرّع عنه بقيّةُ الوحدة: عقدُه وحضورُه وإجازاتُه وراتبُه وسلفُه — فما يُضبَط هنا يُقرأ هناك.',
            'blocks' => [
                'ما تراه في السطر' => [
                    'الدورُ الوظيفي هو مصدرُ صلاحيات الموظف في النظام كلِّه، لا وسمٌ للعرض. وتغييرُه من «تعديل» يغيّر ما يراه ويفعله فور دخوله التالي.',
                    'وسمُ «موظف» أو «مستخدم» ليس مسمّىً وظيفياً: «مستخدم» وحده هو من تُنشأ له خزينةٌ ومستودعٌ افتراضيّان.',
                    'الحالةُ الوظيفية تُميّز «موقوف» و«مستقيل» و«منتهي الخدمة» — وليست «نشط/غير نشط» فحسب. وهي التي تُقصي الموظفَ من احتساب الرواتب.',
                ],
                'ما لا يظهر في الشاشة ويترتّب على الإضافة' => [
                    'يُنشأ للموظف **حسابُ دخولٍ** في جدول المستخدمين ببريده. فبريدُ الموظف هو بريدُ دخوله، ولا يتكرّر بين موظفَين ولا مع حسابٍ قائم.',
                    'ويُنشأ له في شجرة الحسابات **حسابُ ذمّةٍ وحسابُ سلف** — فتصير له ذمّةٌ محاسبيّة يُقيَّد عليها راتبُه وسلفُه.',
                    'ويُصرَف له **رقمُ موظفٍ** من «تسلسل أرقام المستندات» عند الحفظ لا عند فتح الشاشة، فلا تنقطع سلسلةُ الأرقام بنموذجٍ فُتح وتُرك.',
                    'ومن كان نوعُه «مستخدم» أُنشئت له خزينةٌ ومستودعٌ افتراضيّان إن أُشِّر عليهما — ولهما أثرٌ محاسبيٌّ قائم.',
                ],
                'الفرعُ والصلاحية' => [
                    'الفرعُ الأساسي يُلحَق تلقائياً بكلّ آبائه في الشجرة، فمن كان في فرعٍ رأى ما فوقه من مستويات.',
                    'وما تراه أنت في هذه القائمة محدودٌ بنطاق صلاحيتك أنت — فقد يختلف عددُ الصفوف بين مستخدمَين.',
                ],
            ],
            'facts' => [
                'الإضافةُ تُنشئ حسابَ دخولٍ وحسابَين محاسبيَّين',
                'رقمُ الموظف يُستهلَك عند الحفظ لا عند فتح النموذج',
            ],
        ])

        <div class="card">
            <div class="card-body">
                <form class="form" method="GET" action="{{ route('employee.index') }}">
                    <div class="form-body row">
                        <div class="form-group col-md-3">
                            <input type="text" class="form-control" placeholder="البحث بالاسم أو الرقم التعريفي أو البريد الإلكتروني"
                                name="search" value="{{ request('search') }}">
                        </div>

                        <div class="form-group col-md-3">
                            <select name="status" class="form-control">
                                <option value="">أختر الحالة</option>
                                @foreach ($employment_statuses as $st)
                                    <option value="{{ $st->value }}" {{ (string) request('status') === (string) $st->value ? 'selected' : '' }}>
                                        {{ $st->label() }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group col-md-3">
                            <select name="type" class="form-control">
                                <option value="">أختر النوع</option>
                                <option value="1" {{ (string) request('type') === '1' ? 'selected' : '' }}>موظف</option>
                                <option value="2" {{ (string) request('type') === '2' ? 'selected' : '' }}>مستخدم</option>
                            </select>
                        </div>

                        <div class="form-group col-md-3">
                            <select name="role" class="form-control">
                                <option value="">أختر الدور الوظيفي</option>
                                @foreach ($job_roles as $jobRole)
                                    <option value="{{ $jobRole->id }}" {{ (string) request('role') === (string) $jobRole->id ? 'selected' : '' }}>
                                        {{ $jobRole->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <!-- Hidden Div -->
                    <div class="collapse {{ request()->hasAny(['job_type_id', 'job_level_id', 'department_id', 'manager_id', 'job_title_id']) ? 'show' : '' }}" id="advancedSearchForm">
                        <div class="form-body row">
                            <div class="form-group col-md-3">
                                <select name="job_type_id" class="form-control">
                                    <option value="">أختر نوع الوظيفة</option>
                                    @foreach ($job_types as $jobType)
                                        <option value="{{ $jobType->id }}" {{ (string) request('job_type_id') === (string) $jobType->id ? 'selected' : '' }}>
                                            {{ $jobType->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="form-group col-md-3">
                                <select name="job_level_id" class="form-control">
                                    <option value="">أختر المستوى الوظيفي</option>
                                    @foreach ($job_levels as $jobLevel)
                                        <option value="{{ $jobLevel->id }}" {{ (string) request('job_level_id') === (string) $jobLevel->id ? 'selected' : '' }}>
                                            {{ $jobLevel->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="form-group col-md-3">
                                <select name="department_id" class="form-control">
                                    <option value="">أختر القسم</option>
                                    @foreach ($departments as $department)
                                        <option value="{{ $department->id }}" {{ (string) request('department_id') === (string) $department->id ? 'selected' : '' }}>
                                            {{ $department->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="form-group col-md-3">
                                <select name="manager_id" class="form-control">
                                    <option value="">البحث بواسطة المدير المباشر</option>
                                    @foreach ($managers as $manager)
                                        <option value="{{ $manager->id }}" {{ (string) request('manager_id') === (string) $manager->id ? 'selected' : '' }}>
                                            {{ trim($manager->first_name.' '.$manager->middle_name) }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="form-group col-md-3">
                                <select name="job_title_id" class="form-control">
                                    <option value="">أختر المسمى الوظيفي</option>
                                    @foreach ($job_titles as $jobTitle)
                                        <option value="{{ $jobTitle->id }}" {{ (string) request('job_title_id') === (string) $jobTitle->id ? 'selected' : '' }}>
                                            {{ $jobTitle->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary mr-1 waves-effect waves-light">بحث</button>

                        <a class="btn btn-outline-secondary ml-2 mr-2 waves-effect waves-light collapsed"
                            data-toggle="collapse" data-target="#advancedSearchForm" aria-expanded="false">
                            <i class="bi bi-sliders"></i> بحث متقدم
                        </a>
                        <a href="{{ route('employee.index') }}" class="btn btn-outline-danger waves-effect waves-light">الغاء الفلترة</a>
                    </div>
                </form>
            </div>

        </div>

        <div class="card">
            <div class="card-body">
                @if (isset($employees) && !empty($employees) && count($employees) > 0)
                    <!-- اختيار عدد السجلات في الأعلى -->
                    <div class="d-flex justify-content-start align-items-center mb-2 gap-3">
                        <label style="margin-bottom: 0;">
                            عرض
                            <select name="per_page" class="form-select form-select-sm d-inline-block w-auto" onchange="window.location.href='{{ url()->current() }}?{{ http_build_query(request()->except('per_page')) }}&per_page='+this.value;">
                                <option value="10" {{ request('per_page', 10) == 10 ? 'selected' : '' }}>10</option>
                                <option value="25" {{ request('per_page', 10) == 25 ? 'selected' : '' }}>25</option>
                                <option value="50" {{ request('per_page', 10) == 50 ? 'selected' : '' }}>50</option>
                                <option value="100" {{ request('per_page', 10) == 100 ? 'selected' : '' }}>100</option>
                            </select>
                            سجل
                        </label>
                    </div>

                    <!-- الجدول -->
                    <div class="table-responsive">
                        <table class="table table-hover zero-configuration dataTable" id="employees-table">
                            <thead>
                                <tr>
                                    <th width="10%">رقم الموظف</th>
                                    <th width="20%">الاسم</th>
                                    <th width="15%">الدور الوظيفي</th>
                                    <th width="15%">المسمى الوظيفي</th>
                                    <th width="15%">قسم</th>
                                    <th width="15%">فرع</th>
                                    <th width="10%">الحالة</th>
                                    <th width="10%">إجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($employees as $employee)
                                    <tr>
                                        {{-- الرقم إن وُجد؛ والموظفون السابقون للترقيم بلا رقم --}}
                                        <td>
                                            @if ($employee->employee_code)
                                                <code>{{ $employee->employee_code }}</code>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar mr-1">
                                                    {{-- كان الشرطُ على `$employee->photo` — لا عمودَ بهذا
                                                         الاسم ولا خاصّيةَ، فيعود دائماً عدماً وتُعرَض الحروفُ
                                                         أبداً. والعمودُ `employee_photo`، ومسارُه
                                                         `assets/uploads/employee` لا `storage`. --}}
                                                    @if ($employee->employee_photo)
                                                        <img src="{{ asset('assets/uploads/employee/'.$employee->employee_photo) }}"
                                                            alt="صورة الموظف" width="40" height="40"
                                                            class="rounded-circle" style="object-fit: cover;">
                                                    @else
                                                        <div class="avatar bg-light-primary">
                                                            <div class="avatar-content">
                                                                {{ substr($employee->full_name, 0, 1) }}
                                              
                                                            </div>
                                                        </div>
                                                    @endif
                                                </div>
                                                <div class="d-flex flex-column">
                                                    <span class="font-weight-bolder">{{ $employee->full_name }}</span>
                                                    <small class="stk-help">#{{ $employee->id }}</small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="font-weight-bolder">{{ $employee->job_role->name ?? 'غير محدد' }}</span>
                                            <br>
                                            <span class="badge {{ $employee->employee_type == 1 ? 'badge-primary' : 'badge-secondary' }}">
                                                @if ($employee->employee_type == 1)
                                                    موظف
                                                @else
                                                    مستخدم
                                                @endif
                                            </span>
                                        </td>
                                        <td>{{ optional($employee->job_title)->name ?? 'غير محدد' }}</td>
                                        <td>{{ optional($employee->department)->name ?? 'غير محدد' }}</td>
                                        <td>{{ optional($employee->branch)->name ?? 'غير محدد' }}</td>
                                        <td>
                                            {{-- الحالةُ من النوع: «موقوف» و«مستقيل» و«منتهي الخدمة»
                                                 كانت تُعرَض «غير نشط» واحدة --}}
                                            @php $st = $employee->employmentStatus(); @endphp
                                            <span class="badge badge-{{ $st->badge() }}">{{ $st->label() }}</span>
                                        </td>
                                        <td>
                                            @if (canAny(['employees.show', 'employees.edit', 'employee_accounts.password']))
                                                <div class="dropdown">
                                                    <button class="btn btn-sm btn-outline-secondary" type="button" id="dropdownMenuButton{{ $employee->id }}"
                                                        data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false" aria-label="خيارات">
                                                        <i class="fa fa-ellipsis-v"></i>
                                                    </button>
                                                    <div class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownMenuButton{{ $employee->id }}">
                                                        @permission('employees.show')
                                                            <a class="dropdown-item" href="{{ route('employee.show', $employee->id) }}">
                                                                <i class="fa fa-eye me-2 text-primary"></i>عرض
                                                            </a>
                                                        @endpermission
                                                        @permission('employees.edit')
                                                            <a class="dropdown-item" href="{{ route('employee.edit', $employee->id) }}">
                                                                <i class="fa fa-edit me-2 text-success"></i>تعديل
                                                            </a>
                                                        @endpermission
                                                        @permission('employee_accounts.password')
                                                            <a class="dropdown-item" href="#" data-toggle="modal"
                                                                data-target="#ChangePassword{{ $employee->id }}">
                                                                <i class="fa fa-lock me-2 text-info"></i>تغيير كلمة المرور
                                                            </a>
                                                        @endpermission
                                                        @permission('employees.archive')
                                                            {{-- «أرشفة» معلَّقةٌ بطلبٍ لا محذوفة: المسار
                                                                 (`employee.delete`) والمودالُ أسفلَ الصفحة
                                                                 وشاشةُ الأرشيف باقيةٌ كما هي، فرفعُ التعليق
                                                                 وحدَه يُعيد البند إلى القائمة.
                                                            <div class="dropdown-divider"></div>
                                                            <a class="dropdown-item text-danger" href="#"
                                                                data-toggle="modal" data-target="#modal_DELETE{{ $employee->id }}">
                                                                <i class="fa fa-archive me-2"></i>أرشفة
                                                            </a>
                                                            --}}
                                                        @endpermission
                                                        @permission('employee_accounts.password')
                                                            <a class="dropdown-item"
                                                                href="{{ route('employee.send_email', $employee->id) }}">
                                                                <i class="fa fa-paper-plane me-2 text-primary"></i>إرسال بيانات الدخول بالبريد
                                                            </a>
                                                        @endpermission
                                                    </div>
                                                </div>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <!-- الباجينيشن ومعلومات الصفحة -->
                    <div class="d-flex justify-content-between align-items-center mt-3 gap-3">
                        <!-- الباجينيشن في أقصى اليسار -->
                      

                        <!-- معلومات الصفحة في أقصى اليمين -->
                        <div class="pagination-info">
                            صفحة {{ $employees->currentPage() }} من {{ $employees->lastPage() }}
                            (عرض {{ $employees->firstItem() }} إلى {{ $employees->lastItem() }} من {{ $employees->total() }} سجل)
                        </div>
                          <nav aria-label="Page navigation">
                            <ul class="pagination mb-0">
                                @if ($employees->onFirstPage())
                                    <li class="page-item disabled"><span class="page-link">&laquo;</span></li>
                                @else
                                    <li class="page-item"><a class="page-link" href="{{ $employees->previousPageUrl() }}">&laquo;</a></li>
                                @endif
                                @for ($i = 1; $i <= $employees->lastPage(); $i++)
                                    <li class="page-item {{ $employees->currentPage() == $i ? 'active' : '' }}">
                                        <a class="page-link" href="{{ $employees->url($i) }}">{{ $i }}</a>
                                    </li>
                                @endfor
                                @if ($employees->hasMorePages())
                                    <li class="page-item"><a class="page-link" href="{{ $employees->nextPageUrl() }}">&raquo;</a></li>
                                @else
                                    <li class="page-item disabled"><span class="page-link">&raquo;</span></li>
                                @endif
                            </ul>
                        </nav>
                    </div>
                @else
                    <div class="alert alert-info text-center">
                        <i class="feather icon-info me-2"></i>
                        لا يوجد موظفين مسجلين حتى الآن
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Modals -->
    @foreach ($employees as $employee)
        {{--
            مودالُ الأرشفة معلَّقٌ بطلبٍ لا محذوف — كزرِّه في القائمة أعلاه.
            وتعليقُه لازمٌ لا تجميل: `route('employee.archive')` مسارٌ معلَّق،
            وبناؤه يرمي `RouteNotFoundException` فتسقط الشاشةُ كلُّها.
            فرفعُ التعليق هنا وعن سطر المسار يُعيد الإجراء.

        <div class="modal fade" id="modal_DELETE{{ $employee->id }}" tabindex="-1"
            aria-labelledby="deleteModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title" id="deleteModalLabel">أرشفة الموظف</h5>
                        <button type="button" class="btn-close" data-dismiss="modal"
                            aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>هل أنت متأكد من أرشفة الموظف
                            <strong>{{ $employee->full_name }}</strong>؟</p>
                        <p class="text-muted mb-0">
                            تُخرَج بياناتُه من القوائم ولا يُمحى منها شيء — عقودُه وحضورُه
                            ورواتبُه كما هي، وتُستردّ من شاشة المؤرشفين. ويُقطع دخولُه للنظام.
                        </p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary"
                            data-dismiss="modal">إلغاء</button>
                        @permission('employees.archive')
                            <form action="{{ route('employee.archive', $employee->id) }}"
                                method="POST">
                                @csrf
                                <button type="submit" class="btn btn-danger">تأكيد الأرشفة</button>
                            </form>
                        @endpermission
                    </div>
                </div>
            </div>
        </div>
        --}}

        <!-- Modal change password -->
        <div class="modal fade" id="ChangePassword{{ $employee->id }}" tabindex="-1"
            aria-labelledby="changePasswordLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title" id="changePasswordLabel">تغيير كلمة المرور
                        </h5>
                        <button type="button" class="btn-close" data-dismiss="modal"
                            aria-label="Close"></button>
                    </div>
                    @permission('employee_accounts.password')
                        <form action="{{ route('employee.updatePassword', $employee->id) }}"
                            method="POST">
                            @csrf
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label for="password{{ $employee->id }}"
                                        class="form-label">كلمة المرور الجديدة</label>
                                    <input type="password" class="form-control"
                                        id="password{{ $employee->id }}" name="password"
                                        required>
                                    @error('password')
                                        <div class="invalid-feedback d-block">{{ $message }}
                                        </div>
                                    @enderror
                                </div>
                                <div class="mb-3">
                                    <label for="password_confirmation{{ $employee->id }}"
                                        class="form-label">تأكيد كلمة المرور</label>
                                    <input type="password" class="form-control"
                                        id="password_confirmation{{ $employee->id }}"
                                        name="password_confirmation" required>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary"
                                    data-dismiss="modal">إلغاء</button>
                                <button type="submit" class="btn btn-primary">حفظ
                                    التغييرات</button>
                            </div>
                        </form>
                    @endpermission
                </div>
            </div>
        </div>
    @endforeach

    @include('hr::components.table-menu')
@endsection