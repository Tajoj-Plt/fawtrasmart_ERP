@extends('hr::master')

@section('title')
    إدارة الأقسام
@stop

@section('page-actions')
    @permission('departments.create')
        <a href="{{ route('department.create') }}" class="btn btn-primary">
            <i class="fas fa-plus"></i>
            <span class="d-none d-md-inline">إضافة قسم</span>
        </a>
    @endpermission
    @permission('departments.index')
        <div class="btn-group">
            <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-ellipsis-h"></i>
                <span class="d-none d-md-inline">إجراءات</span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><h6 class="dropdown-header">التصدير</h6></li>
                <li>
                    <a class="dropdown-item" href="{{ route('department.export_view') }}">
                        <i class="fas fa-file-export me-2 text-success"></i>تصدير الأقسام
                    </a>
                </li>
                @permission('org_chart.index')
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item" href="{{ route('hr.reports.employees.organization') }}">
                            <i class="fas fa-sitemap me-2 text-primary"></i>الهيكل التنظيمي (الشجرة)
                        </a>
                    </li>
                @endpermission
            </ul>
        </div>
    @endpermission
@endsection

@section('content')
<div class="content-body">
    @include('stock::components.page-head', [
        'title' => 'إدارة الأقسام',
        'icon' => 'layers',
        'crumbs' => ['الرئيسية' => url('/'), 'الأقسام' => null],
    ])

    @include('layouts.alerts.error')
    @include('layouts.alerts.success')

    {{-- ══════════ إرشادُ الشاشة ══════════ --}}
    @include('hr::components.guide', [
        'key' => 'hr-departments.index',
        'title' => 'ما هذه الشاشة، وما الذي يترتّب على ما فيها؟',
        'lead' => 'هذه أقسامُ المنشأة. والقسمُ مرجعٌ تُنسَب إليه ملفّاتُ الموظفين والمسمّياتُ الوظيفية، وعليه تُبنى التقاريرُ والتوزيعُ في لوحة الموارد البشرية.',
        'blocks' => [
            'ما يترتّب على القسم' => [
                'اختيارُ القسم في ملفّ الموظف هو ما يُدخله في «توزيع الموظفين حسب القسم» وفي فلاتر التقارير.',
                'ومن لا قسمَ له لا يختفي من النظام، لكنّه يغيب عن ذلك التوزيع — فيقلّ مجموعُه عن عدد الموظفين.',
                '«المديرون» في القسم قائمةٌ مستقلّة عن موظفيه: من تختارهم في نموذج القسم، لا من يحملون القسمَ في ملفّاتهم.',
            ],
            'الحذفُ والتعطيل' => [
                'تغييرُ اسم القسم يسري على كلّ من يتبعه فوراً، إذ الملفّاتُ تشير إليه لا تنسخه.',
                'والحذفُ يُرفَض ما دام القسمُ مستعمَلاً: الربطُ في قاعدة البيانات يحذف مع القسم ملفّاتِ موظفيه ومسمّياته ومسيراتِ رواتبه — فلا يُحذف إلا قسمٌ لا يتبعه شيء.',
                'والتعطيلُ هو البديل: يبقى القسمُ وأثرُه، ويُعلَّم «غير نشط».',
            ],
        ],
        'facts' => [
            'عددُ الموظفين محصورٌ في نطاق رؤيتك',
            'قسمٌ مستعمَلٌ لا يُحذف — عطّله',
        ],
    ])

    @include('hr::organizational_structure.partials._stats', ['statItems' => [
        ['label' => 'إجمالي الأقسام', 'value' => $stats['total'], 'icon' => 'layers', 'tone' => 'primary'],
        ['label' => 'أقسام نشطة', 'value' => $stats['active'], 'icon' => 'check-circle', 'tone' => 'success'],
        ['label' => 'أقسام غير نشطة', 'value' => $stats['inactive'], 'icon' => 'pause-circle', 'tone' => 'muted'],
        ['label' => 'موظفون منسوبون إلى قسم', 'value' => $stats['employees'], 'icon' => 'users', 'tone' => 'info'],
    ]])

    @php
        $filterKeys = ['name', 'status'];
        $hasFilters = collect(request()->only($filterKeys))->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty();
        $canShow = hasPermission('departments.show');
        $canEdit = hasPermission('departments.edit');
        $canDestroy = hasPermission('departments.destroy');
    @endphp

    <div class="card stk-filters">
        <div class="card-body">
            <div class="stk-filters__head">
                <h6 class="stk-filters__title"><i class="feather icon-filter"></i> بحث وتصفية</h6>
                @if ($hasFilters)
                    <span class="stk-count">فلاتر مفعّلة</span>
                @endif
            </div>

            <form action="{{ route('department.index') }}" method="GET">
                <div class="row">
                    <div class="col-md-8">
                        <div class="form-group">
                            <label for="name">القسم</label>
                            <input type="text" id="name" name="name" class="form-control" value="{{ request('name') }}" placeholder="ابحث بالاسم أو الاختصار…">
                        </div>
                    </div>
                    <div class="col-md-4">
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
                    <a href="{{ route('department.index') }}" class="btn btn-outline-secondary">
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
        @if ($departments->count() > 0)
            <div class="stk-table-head">
                <h6 class="stk-table-head__title">
                    <i class="feather icon-list"></i> قائمة الأقسام
                    <span class="stk-count">{{ $departments->total() }}</span>
                </h6>
                @include('stock::components.per-page', [
                    'id' => 'departmentsPerPage',
                    'current' => request('per_page', 10),
                    'onchange' => "window.location.href='".url()->current().'?'.http_build_query(request()->except(['per_page', 'page']))."&per_page='+this.value",
                ])
            </div>

            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0" id="departments-table">
                    <thead>
                        <tr>
                            <th style="width:44px">#</th>
                            <th>القسم</th>
                            <th class="text-center">الموظفون</th>
                            <th class="text-center">
                                المديرون
                                <i class="feather icon-help-circle stk-tip" title="من اختيروا «مديرين معنيّين» في نموذج القسم — قائمةٌ مستقلّة عن موظفي القسم"></i>
                            </th>
                            <th class="text-center">الحالة</th>
                            <th class="text-center" style="width:70px">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($departments as $index => $department)
                            @php $isActive = (int) $department->status === 0; @endphp
                            <tr>
                                <td class="stk-num text-muted">{{ $departments->firstItem() + $index }}</td>
                                <td>
                                    <div class="stk-media">
                                        <span class="stk-media__thumb"><i class="feather icon-layers"></i></span>
                                        <span class="stk-media__body">
                                            @if ($canShow)
                                                <a href="{{ route('department.show', $department->id) }}" class="stk-media__title">{{ $department->name }}</a>
                                            @else
                                                <span class="stk-media__title">{{ $department->name }}</span>
                                            @endif
                                            <span class="stk-media__sub">{{ $department->short_name ?: \Illuminate\Support\Str::limit($department->description, 60) }}</span>
                                        </span>
                                    </div>
                                </td>
                                <td class="text-center stk-num">{{ (int) $department->members_count }}</td>
                                <td class="text-center stk-num">{{ (int) $department->employees_count }}</td>
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
                                                    <a class="dropdown-item" href="{{ route('department.show', $department->id) }}">
                                                        <i class="fa fa-eye me-2 text-primary"></i>عرض
                                                    </a>
                                                @endif
                                                @if ($canEdit)
                                                    <a class="dropdown-item" href="{{ route('department.edit', $department->id) }}">
                                                        <i class="fa fa-edit me-2 text-success"></i>تعديل
                                                    </a>
                                                    <a class="dropdown-item" href="{{ route('department.updateStatus', $department->id) }}">
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
                                                    <a class="dropdown-item text-danger" href="#" data-toggle="modal" data-target="#modal_DELETE{{ $department->id }}">
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

            @if ($departments->hasPages())
                <div class="stk-table-foot">
                    <p class="stk-table-foot__count">عرض {{ $departments->firstItem() }}–{{ $departments->lastItem() }} من {{ $departments->total() }}</p>
                    {{ $departments->links() }}
                </div>
            @endif
        @else
            <div class="card-body">
                <div class="stk-empty">
                    <div class="stk-empty__icon"><i class="feather icon-layers"></i></div>
                    <h5 class="stk-empty__title">لا توجد أقسام</h5>
                    <p class="stk-empty__hint">
                        @if ($hasFilters)
                            لم يطابق البحث أيّ قسم — جرّب معايير أوسع
                        @else
                            أضف أوّل قسمٍ لتنسب إليه الموظفين والمسمّيات الوظيفية
                        @endif
                    </p>
                    @if ($hasFilters)
                        <a href="{{ route('department.index') }}" class="btn btn-outline-secondary"><i class="feather icon-rotate-ccw"></i> إزالة الفلاتر</a>
                    @elseif (hasPermission('departments.create'))
                        <a href="{{ route('department.create') }}" class="btn btn-primary"><i class="feather icon-plus"></i> إضافة قسم</a>
                    @endif
                </div>
            </div>
        @endif
    </div>

    @if ($canDestroy)
        @foreach ($departments as $department)
            @include('hr::organizational_structure.partials._delete_modal', [
                'modalId' => 'modal_DELETE'.$department->id,
                'heading' => 'حذف القسم',
                'action' => route('department.delete', $department->id),
                'itemName' => $department->name,
                'subject' => 'القسم',
                'usage' => [],
            ])
        @endforeach
    @endif
</div>
@endsection

@push('scripts')
@include('hr::components.table-menu')
@endpush
