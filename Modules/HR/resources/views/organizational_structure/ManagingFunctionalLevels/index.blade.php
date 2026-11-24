@extends('hr::master')

@section('title')
    المستويات الوظيفية
@stop

@section('page-actions')
    @permission('job_levels.create')
        <a href="{{ route('ManagingFunctionalLevels.create') }}" class="btn btn-primary">
            <i class="fas fa-plus"></i>
            <span class="d-none d-md-inline">إضافة مستوى وظيفي</span>
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
        'title' => 'المستويات الوظيفية',
        'icon' => 'bar-chart-2',
        'crumbs' => ['الرئيسية' => url('/'), 'المستويات الوظيفية' => null],
    ])

    @include('layouts.alerts.error')
    @include('layouts.alerts.success')

    {{-- ══════════ إرشادُ الشاشة ══════════ --}}
    @include('hr::components.guide', [
        'key' => 'hr-functional-levels.index',
        'title' => 'ما هذه الشاشة، وما الذي يترتّب على ما فيها؟',
        'lead' => 'هذه المستوياتُ الوظيفية — درجاتُ التدرّج الوظيفي. والمستوى مرجعٌ تشير إليه ملفّاتُ الموظفين وعقودُهم، وتُخصَّص به قواعدُ الحضور والإجازات.',
        'blocks' => [
            'ما يترتّب على المستوى' => [
                'يُختار في ملفّ الموظف وفي عقده، ويُستعمَل فلترةً في التقارير وملفّات التصدير.',
                'وسياساتُ الإجازات وقواعدُ الحضور والعُطَل تُخصَّص به، فما يُكتب «لهذا المستوى» يسري على كلّ من هو فيه.',
            ],
            'التعديلُ والحذف' => [
                'تغييرُ الاسم يسري فوراً على كلّ من هو في المستوى — لا نسخةَ لديه منه.',
                'والحذفُ يُرفَض ما دام المستوى مستعمَلاً: الربطُ في قاعدة البيانات يحذف معه ملفّاتِ موظفيه وعقودَهم. فعطّله بدلاً من ذلك.',
            ],
        ],
        'facts' => [
            'سياساتُ الإجازات والحضور تُخصَّص بالمستوى',
            'مستوى مستعمَلٌ لا يُحذف — عطّله',
        ],
    ])

    @include('hr::organizational_structure.partials._stats', ['statItems' => [
        ['label' => 'إجمالي المستويات', 'value' => $stats['total'], 'icon' => 'bar-chart-2', 'tone' => 'primary'],
        ['label' => 'مستويات نشطة', 'value' => $stats['active'], 'icon' => 'check-circle', 'tone' => 'success'],
        ['label' => 'مستويات غير نشطة', 'value' => $stats['inactive'], 'icon' => 'pause-circle', 'tone' => 'muted'],
        ['label' => 'موظفون لهم مستوى', 'value' => $stats['employees'], 'icon' => 'users', 'tone' => 'info'],
    ]])

    @include('hr::organizational_structure.partials._simple_index', [
        'rows' => $functionalLevels,
        'routePrefix' => 'ManagingFunctionalLevels',
        'permPrefix' => 'job_levels',
        'icon' => 'bar-chart-2',
        'tableId' => 'functional-levels-table',
        'listTitle' => 'قائمة المستويات الوظيفية',
        'searchLabel' => 'المستوى الوظيفي',
        'deleteHeading' => 'حذف المستوى الوظيفي',
        'subject' => 'المستوى الوظيفي',
        'emptyTitle' => 'لا توجد مستويات وظيفية',
        'emptyHint' => 'أضف درجات التدرّج الوظيفي لتُختار في ملفّات الموظفين وعقودهم',
        'addLabel' => 'إضافة مستوى وظيفي',
    ])
</div>
@endsection

@push('scripts')
@include('hr::components.table-menu')
@endpush
