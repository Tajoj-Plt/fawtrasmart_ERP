@extends('hr::master')

@section('title')
    المسميات الوظيفية
@stop

@section('page-actions')
    @permission('job_titles.create')
        <a href="{{ route('JobTitles.create') }}" class="btn btn-primary">
            <i class="fas fa-plus"></i>
            <span class="d-none d-md-inline">إضافة مسمى وظيفي</span>
        </a>
    @endpermission
    @if (canAny(['departments.index', 'org_chart.index']))
        <div class="btn-group">
            <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-ellipsis-h"></i>
                <span class="d-none d-md-inline">إجراءات</span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><h6 class="dropdown-header">الهيكل التنظيمي</h6></li>
                @permission('departments.index')
                    <li>
                        <a class="dropdown-item" href="{{ route('department.index') }}">
                            <i class="fas fa-layer-group me-2 text-primary"></i>الأقسام
                        </a>
                    </li>
                @endpermission
                @permission('org_chart.index')
                    <li>
                        <a class="dropdown-item" href="{{ route('hr.reports.employees.organization') }}">
                            <i class="fas fa-sitemap me-2 text-primary"></i>الهيكل التنظيمي (الشجرة)
                        </a>
                    </li>
                @endpermission
            </ul>
        </div>
    @endif
@endsection

@section('content')
<div class="content-body">
    @include('stock::components.page-head', [
        'title' => 'المسميات الوظيفية',
        'icon' => 'briefcase',
        'crumbs' => ['الرئيسية' => url('/'), 'المسميات الوظيفية' => null],
    ])

    @include('layouts.alerts.error')
    @include('layouts.alerts.success')

    {{-- ══════════ إرشادُ الشاشة ══════════ --}}
    @include('hr::components.guide', [
        'key' => 'hr-job-titles.index',
        'title' => 'ما هذه الشاشة، وما الذي يترتّب على ما فيها؟',
        'lead' => 'هذه المسمّياتُ الوظيفية. والمسمّى مرجعٌ تشير إليه ملفّاتُ الموظفين والعقود، وتُبنى عليه قواعدُ الحضور والإجازات — لا وسمٌ للعرض.',
        'blocks' => [
            'ما يترتّب على المسمّى' => [
                'يُختار في ملفّ الموظف وفي عقده، فيظهر في القوائم والتقارير وملفّات التصدير.',
                'وقواعدُ الحضور والعُطَل وسياساتُ الإجازات والورديّاتُ المخصَّصة تُخصَّص به: قاعدةٌ تُكتب «لهذا المسمّى» تسري على كلّ من يحمله.',
                'وكلُّ مسمّى يتبع قسماً، فتغييرُ قسمه ينقله في التقارير المبنيّة على الأقسام.',
            ],
            'التعديلُ والحذف' => [
                'تغييرُ الاسم يسري فوراً على كلّ من يحمله، إذ الملفّاتُ تشير إليه ولا تنسخه.',
                'والحذفُ يُرفَض ما دام المسمّى مستعمَلاً: الربطُ في قاعدة البيانات يحذف معه ملفّاتِ حامليه وعقودَهم وقواعدَه المخصَّصة. فعطّله بدلاً من ذلك.',
            ],
        ],
        'facts' => [
            'قواعدُ الحضور والإجازات تُخصَّص بالمسمّى',
            'مسمّى مستعمَلٌ لا يُحذف — عطّله',
        ],
    ])

    @include('hr::organizational_structure.partials._stats', ['statItems' => [
        ['label' => 'إجمالي المسميات', 'value' => $stats['total'], 'icon' => 'briefcase', 'tone' => 'primary'],
        ['label' => 'مسميات نشطة', 'value' => $stats['active'], 'icon' => 'check-circle', 'tone' => 'success'],
        ['label' => 'مسميات غير نشطة', 'value' => $stats['inactive'], 'icon' => 'pause-circle', 'tone' => 'muted'],
        ['label' => 'موظفون يحملون مسمى', 'value' => $stats['employees'], 'icon' => 'users', 'tone' => 'info'],
    ]])

    @php
        $filterKeys = ['name', 'status', 'department_id'];
        $hasFilters = collect(request()->only($filterKeys))->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty();
        $canShow = hasPermission('job_titles.show');
        $canEdit = hasPermission('job_titles.edit');
        $canDestroy = hasPermission('job_titles.destroy');
        $canDeptShow = hasPermission('departments.show');
    @endphp

    <div class="card stk-filters">
        <div class="card-body">
            <div class="stk-filters__head">
                <h6 class="stk-filters__title"><i class="feather icon-filter"></i> بحث وتصفية</h6>
                @if ($hasFilters)
                    <span class="stk-count">فلاتر مفعّلة</span>
                @endif
            </div>

            <form action="{{ route('JobTitles.index') }}" method="GET">
                <div class="row">
                    <div class="col-md-5">
                        <div class="form-group">
                            <label for="name">المسمى الوظيفي</label>
                            <input type="text" id="name" name="name" class="form-control" value="{{ request('name') }}" placeholder="ابحث بالاسم…">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="department_id">القسم</label>
                            <select id="department_id" name="department_id" class="form-control hr-s2">
                                <option value="">كل الأقسام</option>
                                @foreach ($departments as $department)
                                    <option value="{{ $department->id }}" {{ (string) request('department_id') === (string) $department->id ? 'selected' : '' }}>{{ $department->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="status">الحالة</label>
                            <select id="status" name="status" class="form-control hr-s2">
                                <option value="">الكل</option>
                                <option value="0" {{ request('status') === '0' ? 'selected' : '' }}>نشط</option>
                                <option value="1" {{ request('status') === '1' ? 'selected' : '' }}>غير نشط</option>
                            </select>
                        </div>
                    </div>
                </div>
                @if (request()->filled('per_page'))
                    <input type="hidden" name="per_page" value="{{ request('per_page') }}">
                @endif

                <div class="stk-filters__foot">
                    <a href="{{ route('JobTitles.index') }}" class="btn btn-outline-secondary">
                        <i class="feather icon-rotate-ccw"></i> إعادة تعيين
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="feather icon-search"></i> بحث
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card stk-table-card">
        @if ($titles->count() > 0)
            <div class="stk-table-head">
                <h6 class="stk-table-head__title">
                    <i class="feather icon-list"></i> قائمة المسميات الوظيفية
                    <span class="stk-count">{{ $titles->total() }}</span>
                </h6>
                @include('stock::components.per-page', [
                    'id' => 'jobTitlesPerPage',
                    'current' => request('per_page', 10),
                    'onchange' => "window.location.href='".url()->current().'?'.http_build_query(request()->except(['per_page', 'page']))."&per_page='+this.value",
                ])
            </div>

            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0" id="job-titles-table">
                    <thead>
                        <tr>
                            <th style="width:44px">#</th>
                            <th>المسمى الوظيفي</th>
                            <th>القسم</th>
                            <th class="text-center">الموظفون</th>
                            <th class="text-center">الحالة</th>
                            <th class="text-center" style="width:70px">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($titles as $index => $jobTitle)
                            @php $isActive = (int) $jobTitle->status === 0; @endphp
                            <tr>
                                <td class="stk-num text-muted">{{ $titles->firstItem() + $index }}</td>
                                <td>
                                    <div class="stk-media">
                                        <span class="stk-media__thumb"><i class="feather icon-briefcase"></i></span>
                                        <span class="stk-media__body">
                                            @if ($canShow)
                                                <a href="{{ route('JobTitles.show', $jobTitle->id) }}" class="stk-media__title">{{ $jobTitle->name }}</a>
                                            @else
                                                <span class="stk-media__title">{{ $jobTitle->name }}</span>
                                            @endif
                                            @if ($jobTitle->description)
                                                <span class="stk-media__sub">{{ \Illuminate\Support\Str::limit($jobTitle->description, 60) }}</span>
                                            @endif
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    @if ($jobTitle->department && $canDeptShow)
                                        <a href="{{ route('department.show', $jobTitle->department->id) }}">{{ $jobTitle->department->name }}</a>
                                    @else
                                        {{ $jobTitle->department->name ?? 'غير محدد' }}
                                    @endif
                                </td>
                                <td class="text-center stk-num">{{ (int) $jobTitle->employees_count }}</td>
                                <td class="text-center">
                                    <span class="stk-state stk-state--{{ $isActive ? 'on' : 'off' }}">{{ $isActive ? 'نشط' : 'غير نشط' }}</span>
                                </td>
                                <td class="text-center">
                                    @if ($canShow || $canEdit || $canDestroy)
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary" type="button"
                                                data-bs-toggle="dropdown" data-bs-display="static"
                                                aria-expanded="false" aria-label="خيارات">
                                                <i class="fa fa-ellipsis-v"></i>
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-end">
                                                @if ($canShow)
                                                    <a class="dropdown-item" href="{{ route('JobTitles.show', $jobTitle->id) }}">
                                                        <i class="fa fa-eye me-2 text-primary"></i>عرض
                                                    </a>
                                                @endif
                                                @if ($canEdit)
                                                    <a class="dropdown-item" href="{{ route('JobTitles.edit', $jobTitle->id) }}">
                                                        <i class="fa fa-edit me-2 text-success"></i>تعديل
                                                    </a>
                                                    <a class="dropdown-item" href="{{ route('JobTitles.updateStatus', $jobTitle->id) }}">
                                                        @if ($isActive)
                                                            <i class="fa fa-pause-circle me-2 text-warning"></i>تعطيل
                                                        @else
                                                            <i class="fa fa-play-circle me-2 text-success"></i>تفعيل
                                                        @endif
                                                    </a>
                                                @endif
                                                @if ($canDestroy)
                                                    @if ($canShow || $canEdit)
                                                        <div class="dropdown-divider"></div>
                                                    @endif
                                                    <a class="dropdown-item text-danger" href="#" data-toggle="modal" data-target="#modal_DELETE{{ $jobTitle->id }}">
                                                        <i class="fa fa-trash me-2"></i>حذف
                                                    </a>
                                                @endif
                                            </div>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($titles->hasPages())
                <div class="stk-table-foot">
                    <p class="stk-table-foot__count">عرض {{ $titles->firstItem() }}–{{ $titles->lastItem() }} من {{ $titles->total() }}</p>
                    {{ $titles->links() }}
                </div>
            @endif
        @else
            <div class="card-body">
                <div class="stk-empty">
                    <div class="stk-empty__icon"><i class="feather icon-briefcase"></i></div>
                    <h5 class="stk-empty__title">لا توجد مسميات وظيفية</h5>
                    <p class="stk-empty__hint">
                        @if ($hasFilters)
                            لم يطابق البحث أيّ مسمى — جرّب معايير أوسع
                        @else
                            أضف المسميات لتُختار في ملفّات الموظفين وعقودهم
                        @endif
                    </p>
                    @if ($hasFilters)
                        <a href="{{ route('JobTitles.index') }}" class="btn btn-outline-secondary"><i class="feather icon-rotate-ccw"></i> إزالة الفلاتر</a>
                    @elseif (hasPermission('job_titles.create'))
                        <a href="{{ route('JobTitles.create') }}" class="btn btn-primary"><i class="feather icon-plus"></i> إضافة مسمى وظيفي</a>
                    @endif
                </div>
            </div>
        @endif
    </div>

    @if ($canDestroy)
        @foreach ($titles as $jobTitle)
            @include('hr::organizational_structure.partials._delete_modal', [
                'modalId' => 'modal_DELETE'.$jobTitle->id,
                'heading' => 'حذف المسمى الوظيفي',
                'action' => route('JobTitles.delete', $jobTitle->id),
                'itemName' => $jobTitle->name,
                'subject' => 'المسمى الوظيفي',
                'usage' => [],
            ])
        @endforeach
    @endif
</div>
@endsection

@push('scripts')
@include('hr::components.table-menu')
@endpush
