@extends('hr::master')

@section('title')
    أنواع الوظائف
@stop

@section('page-actions')
    @permission('job_types.create')
        <a href="{{ route('ManagingJobTypes.create') }}" class="btn btn-primary">
            <i class="fas fa-plus"></i>
            <span class="d-none d-md-inline">إضافة نوع وظيفة</span>
        </a>
    @endpermission
    @if (canAny(['departments.index', 'job_titles.index', 'org_chart.index']))
        <div class="btn-group">
            <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-ellipsis-h"></i>
                <span class="d-none d-md-inline">إجراءات</span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><h6 class="dropdown-header">الهيكل التنظيمي</h6></li>
                @permission('departments.index')
                    <li><a class="dropdown-item" href="{{ route('department.index') }}"><i class="fas fa-layer-group me-2 text-primary"></i>الأقسام</a></li>
                @endpermission
                @permission('job_titles.index')
                    <li><a class="dropdown-item" href="{{ route('JobTitles.index') }}"><i class="fas fa-briefcase me-2 text-primary"></i>المسميات الوظيفية</a></li>
                @endpermission
                @permission('org_chart.index')
                    <li><a class="dropdown-item" href="{{ route('hr.reports.employees.organization') }}"><i class="fas fa-sitemap me-2 text-primary"></i>الهيكل التنظيمي (الشجرة)</a></li>
                @endpermission
            </ul>
        </div>
    @endif
@endsection

@section('content')
<div class="content-body">
    @include('stock::components.page-head', [
        'title' => 'أنواع الوظائف',
        'icon' => 'tag',
        'crumbs' => ['الرئيسية' => url('/'), 'أنواع الوظائف' => null],
    ])

    @include('layouts.alerts.error')
    @include('layouts.alerts.success')

    {{-- ══════════ إرشادُ الشاشة ══════════ --}}
    @include('hr::components.guide', [
        'key' => 'hr-job-types.index',
        'title' => 'ما هذه الشاشة، وما الذي يترتّب على ما فيها؟',
        'lead' => 'هذه أنواعُ الوظائف — طبيعةُ الارتباط الوظيفي (دوامٌ كامل، جزئيّ، مؤقّت…). والنوعُ مرجعٌ تشير إليه ملفّاتُ الموظفين.',
        'blocks' => [
            'ما يترتّب على النوع' => [
                'يُختار في ملفّ الموظف، ويُستعمَل فلترةً في التقارير وفي معالجة الرواتب وملفّات التصدير.',
                'وقواعدُ الحضور والورديّاتُ المخصَّصة تُخصَّص به كما تُخصَّص بالمسمّى والمستوى.',
            ],
            'التعديلُ والحذف' => [
                'تغييرُ الاسم يسري فوراً على كلّ من يحمله.',
                'والحذفُ يُرفَض ما دام النوعُ مستعمَلاً: الربطُ في قاعدة البيانات يحذف معه ملفّاتِ الموظفين الذين يحملونه. فعطّله بدلاً من ذلك.',
            ],
            'ما لا يعنيه هذا النوع' => [
                'هو غيرُ خانة «موظف / مستخدم» في نموذج الموظف: تلك تقرّر أتُنشأ له خزينةٌ ومستودعٌ افتراضيّان، وهذه طبيعةُ ارتباطه الوظيفي.',
            ],
        ],
        'facts' => [
            'النوعُ مرجعٌ تشير إليه ملفّاتُ الموظفين',
            'ليس هو خانة «موظف / مستخدم»',
        ],
    ])

    @include('hr::organizational_structure.partials._stats', ['statItems' => [
        ['label' => 'إجمالي الأنواع', 'value' => $stats['total'], 'icon' => 'tag', 'tone' => 'primary'],
        ['label' => 'أنواع نشطة', 'value' => $stats['active'], 'icon' => 'check-circle', 'tone' => 'success'],
        ['label' => 'أنواع غير نشطة', 'value' => $stats['inactive'], 'icon' => 'pause-circle', 'tone' => 'muted'],
        ['label' => 'موظفون لهم نوع', 'value' => $stats['employees'], 'icon' => 'users', 'tone' => 'info'],
    ]])

    @include('hr::organizational_structure.partials._simple_index', [
        'rows' => $types,
        'routePrefix' => 'ManagingJobTypes',
        'permPrefix' => 'job_types',
        'icon' => 'tag',
        'tableId' => 'job-types-table',
        'listTitle' => 'قائمة أنواع الوظائف',
        'searchLabel' => 'نوع الوظيفة',
        'deleteHeading' => 'حذف نوع الوظيفة',
        'subject' => 'نوع الوظيفة',
        'emptyTitle' => 'لا توجد أنواع وظائف',
        'emptyHint' => 'أضف أنواع الارتباط الوظيفي (دوام كامل، جزئي…) لتُختار في ملفّات الموظفين',
        'addLabel' => 'إضافة نوع وظيفة',
    ])
</div>
@endsection

@push('scripts')
@include('hr::components.table-menu')
@endpush
