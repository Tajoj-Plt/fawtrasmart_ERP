@extends('hr::master')

@section('title', 'العقود')

@section('page-actions')
    @permission('contracts.create')
        <a href="{{ route('Contracts.create') }}" class="btn btn-primary">
            <i class="fas fa-plus"></i> أضف عقد
        </a>
    @endpermission
@endsection

@section('content')
<div class="content-body">
    @include('stock::components.page-head', [
        'title' => 'العقود',
        'icon' => 'file-text',
        'crumbs' => ['الرئيسية' => url('/'), 'العقود' => null],
    ])

    @include('layouts.alerts.error')
    @include('layouts.alerts.success')

    {{-- ══════════ إرشادُ الشاشة ══════════ --}}
    @include('hr::components.guide', [
        'key' => 'hr-contracts.index',
        'title' => 'ما هذه الشاشة، وما الذي يترتّب على ما فيها؟',
        'lead' => 'هذه عقودُ الموظفين. والعقدُ مرجعُ الأجر ومدّةِ الخدمة: منه يُقرأ الراتبُ الأساسي وبنودُه، وعليه تُبنى مكافأةُ نهاية الخدمة، وتاريخُ انتهائه هو ما تنبّه عليه لوحةُ الموارد البشرية.',
        'blocks' => [
            'ما يترتّب على الحفظ' => [
                'يُصرَف للعقد رقمٌ من «تسلسل أرقام المستندات» عند الحفظ.',
                'وتاريخُ الانتهاء يُدخل الموظفَ في «عقود تنتهي خلال 30 يوماً» في اللوحة قبل انتهائه بشهر.',
            ],
            'علاقتُه بالراتب' => [
                'العقدُ لا يصرف راتباً بنفسه: الصرفُ من قسيمة الراتب. لكنّ ما فيه من أجرٍ هو ما تُقاس عليه الاستحقاقاتُ والحسومات.',
                'مبلغ العقد = الأساسي + المستحقات، والصافي بعد المستقطعات.',
                'وتعديلُ الأجر فيه لا يُغيّر صرفاتٍ وقعت — يسري على ما يأتي بعده.',
            ],
            'ما تراه هنا' => [
                'القائمةُ محصورةٌ في نطاق صلاحيتك وفرع الشريط العلوي. ومن يملك «عرض عقده هو» وحده يرى عقدَه فقط.',
                'الطباعةُ من «قوالب المستندات ← عقد عمل»: عدّل القالب هناك فيتغيّر ما يُطبع.',
            ],
        ],
        'facts' => [
            'رقمُ العقد يُصرَف من التسلسل عند الحفظ',
            'الأجرُ فيه أساسُ الحسومات ومكافأة نهاية الخدمة',
        ],
    ])

    @php
        $advancedKeys = ['start_date_from', 'start_date_to', 'end_date_from', 'end_date_to', 'job_title_id', 'salary_item_id', 'created_by', 'join_date_from', 'join_date_to', 'probation_date_from', 'probation_date_to'];
        $filterKeys = array_merge(['employee_search', 'code', 'department_id', 'contract_status', 'branch_id'], $advancedKeys);
        $hasFilters = request()->hasAny($filterKeys) && collect(request()->only($filterKeys))->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty();
        $advancedOpen = collect(request()->only($advancedKeys))->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty();
    @endphp

    <div class="card stk-filters">
        <div class="card-body">
            <div class="stk-filters__head">
                <h6 class="stk-filters__title"><i class="feather icon-filter"></i> بحث وتصفية</h6>
                @if ($hasFilters)
                    <span class="stk-count">فلاتر مفعّلة</span>
                @endif
            </div>

            <form action="{{ route('Contracts.index') }}" method="GET">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="f_employee">الموظف</label>
                            <select name="employee_search" id="f_employee" class="form-control hr-s2" data-placeholder="كل الموظفين">
                                <option value="">كل الموظفين</option>
                                @foreach ($employees as $employee)
                                    <option value="{{ $employee->id }}" {{ (string) request('employee_search') === (string) $employee->id ? 'selected' : '' }}>{{ $employee->full_name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label for="f_code">رقم العقد</label>
                            <input type="text" name="code" id="f_code" class="form-control" value="{{ request('code') }}" placeholder="اكتب للبحث…">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="f_department">القسم</label>
                            <select name="department_id" id="f_department" class="form-control hr-s2">
                                <option value="">كل الأقسام</option>
                                @foreach ($departments as $department)
                                    <option value="{{ $department->id }}" {{ (string) request('department_id') === (string) $department->id ? 'selected' : '' }}>{{ $department->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="f_status">حالة العقد</label>
                            <select name="contract_status" id="f_status" class="form-control hr-s2">
                                <option value="">الكل</option>
                                <option value="active" {{ request('contract_status') === 'active' ? 'selected' : '' }}>ساري</option>
                                <option value="expiring" {{ request('contract_status') === 'expiring' ? 'selected' : '' }}>ينتهي خلال 30 يوماً</option>
                                <option value="expired" {{ request('contract_status') === 'expired' ? 'selected' : '' }}>منتهٍ</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-12">
                        @include('reports.partials.affiliation-tree', [
                            'treeNodes' => \Modules\HR\Support\BranchTree::treeNodes(),
                            'name' => 'branch_id',
                            'showExact' => false,
                            'label' => 'تابع لـ',
                        ])
                    </div>
                </div>

                <div class="collapse {{ $advancedOpen ? 'show' : '' }}" id="advancedSearchForm">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="f_start_from">بداية العقد من</label>
                                <input type="date" name="start_date_from" id="f_start_from" class="form-control" value="{{ request('start_date_from') }}">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="f_start_to">بداية العقد إلى</label>
                                <input type="date" name="start_date_to" id="f_start_to" class="form-control" value="{{ request('start_date_to') }}">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="f_end_from">انتهاء العقد من</label>
                                <input type="date" name="end_date_from" id="f_end_from" class="form-control" value="{{ request('end_date_from') }}">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="f_end_to">انتهاء العقد إلى</label>
                                <input type="date" name="end_date_to" id="f_end_to" class="form-control" value="{{ request('end_date_to') }}">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="f_join_from">الالتحاق من</label>
                                <input type="date" name="join_date_from" id="f_join_from" class="form-control" value="{{ request('join_date_from') }}">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="f_join_to">الالتحاق إلى</label>
                                <input type="date" name="join_date_to" id="f_join_to" class="form-control" value="{{ request('join_date_to') }}">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="f_prob_from">نهاية التجربة من</label>
                                <input type="date" name="probation_date_from" id="f_prob_from" class="form-control" value="{{ request('probation_date_from') }}">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="f_prob_to">نهاية التجربة إلى</label>
                                <input type="date" name="probation_date_to" id="f_prob_to" class="form-control" value="{{ request('probation_date_to') }}">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="f_job_title">المسمى الوظيفي</label>
                                <select name="job_title_id" id="f_job_title" class="form-control hr-s2">
                                    <option value="">الكل</option>
                                    @foreach ($jobTitles as $jobTitle)
                                        <option value="{{ $jobTitle->id }}" {{ (string) request('job_title_id') === (string) $jobTitle->id ? 'selected' : '' }}>{{ $jobTitle->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="f_item">بند الراتب</label>
                                <select name="salary_item_id" id="f_item" class="form-control hr-s2">
                                    <option value="">الكل</option>
                                    @foreach ($salaryItems as $item)
                                        <option value="{{ $item->id }}" {{ (string) request('salary_item_id') === (string) $item->id ? 'selected' : '' }}>{{ $item->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="f_creator">أضيف بواسطة</label>
                                <select name="created_by" id="f_creator" class="form-control hr-s2">
                                    <option value="">الكل</option>
                                    @foreach ($users as $user)
                                        <option value="{{ $user->id }}" {{ (string) request('created_by') === (string) $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="stk-filters__foot">
                    <a class="btn btn-outline-secondary mr-auto" data-toggle="collapse" href="#advancedSearchForm" role="button" aria-expanded="{{ $advancedOpen ? 'true' : 'false' }}">
                        <i class="feather icon-sliders"></i> بحث متقدم
                    </a>
                    <a href="{{ route('Contracts.index') }}" class="btn btn-outline-secondary">
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
        @if ($contracts->count() > 0)
            <div class="stk-table-head">
                <h6 class="stk-table-head__title">
                    <i class="feather icon-list"></i> قائمة العقود
                    <span class="stk-count">{{ $contracts->total() }}</span>
                </h6>
                @include('stock::components.per-page', [
                    'id' => 'contractsPerPage',
                    'current' => request('per_page', 10),
                    'onchange' => "window.location.href='".url()->current().'?'.http_build_query(request()->except(['per_page', 'page']))."&per_page='+this.value",
                ])
            </div>

            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0" id="contracts-table">
                    <thead>
                        <tr>
                            <th style="width:44px">#</th>
                            <th>رقم العقد</th>
                            <th>الموظف</th>
                            <th>المدة</th>
                            <th class="text-end">مبلغ العقد</th>
                            <th class="text-center">الحالة</th>
                            <th class="text-center" style="width:70px">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($contracts as $index => $contract)
                            @php
                                $totals = $contract->salaryTotals();
                                $end = $contract->end_date;
                                $state = ! $end || $end->gte(now()->startOfDay())
                                    ? ($end && $end->lte(now()->addDays(30)) ? ['warn', 'ينتهي قريباً'] : ['on', 'ساري'])
                                    : ['off', 'منتهٍ'];
                                $act = $contractActions($contract);
                            @endphp
                            <tr>
                                <td class="stk-num text-muted">{{ $contracts->firstItem() + $index }}</td>
                                <td>
                                    @if ($act['view'])
                                        <a href="{{ route('Contracts.show', $contract->id) }}" class="font-weight-bold" dir="ltr">{{ $contract->code ?: '#'.$contract->id }}</a>
                                    @else
                                        <span class="font-weight-bold" dir="ltr">{{ $contract->code ?: '#'.$contract->id }}</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="stk-media">
                                        <span class="stk-media__thumb"><i class="feather icon-user"></i></span>
                                        <span class="stk-media__body">
                                            <span class="stk-media__title">{{ $contract->employee->full_name ?? '—' }}</span>
                                            <span class="stk-media__sub">{{ $contract->jobTitle->name ?? '' }}{{ ($contract->jobTitle && $contract->employee?->department) ? ' · ' : '' }}{{ $contract->employee->department->name ?? '' }}</span>
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <span dir="ltr">{{ optional($contract->start_date)->format('Y-m-d') }}</span>
                                    <span class="text-muted">←</span>
                                    <span dir="ltr">{{ $end ? $end->format('Y-m-d') : 'مفتوح' }}</span>
                                    <span class="d-block small text-muted">{{ $contract->getDurationText() }}</span>
                                </td>
                                <td class="text-end">
                                    <strong>{{ \App\Support\CurrencySettings::format($totals['gross']) }}</strong>
                                    <span class="d-block small text-muted">الصافي {{ \App\Support\CurrencySettings::format($totals['net'], false) }}</span>
                                </td>
                                <td class="text-center">
                                    <span class="stk-state stk-state--{{ $state[0] }}">{{ $state[1] }}</span>
                                    @unless ($contract->isApproved())
                                        <span class="d-block small text-warning mt-25">مسودّة — بانتظار الاعتماد</span>
                                    @endunless
                                </td>
                                <td class="text-center">
                                    @if ($act['view'] || $act['edit'] || $act['print'] || $act['destroy'] || $act['approve'])
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary" type="button"
                                            data-bs-toggle="dropdown" data-bs-display="static"
                                            aria-expanded="false" aria-label="خيارات">
                                            <i class="fa fa-ellipsis-v"></i>
                                        </button>
                                        <div class="dropdown-menu dropdown-menu-end">
                                            @if ($act['view'])
                                                <a class="dropdown-item" href="{{ route('Contracts.show', $contract->id) }}">
                                                    <i class="fa fa-eye me-2 text-primary"></i>عرض
                                                </a>
                                            @endif
                                            @if ($act['edit'])
                                                <a class="dropdown-item" href="{{ route('Contracts.edit', $contract->id) }}">
                                                    <i class="fa fa-edit me-2 text-success"></i>تعديل
                                                </a>
                                            @endif
                                            @if ($act['approve'])
                                                <form action="{{ route('Contracts.approve', $contract->id) }}" method="POST" class="m-0">
                                                    @csrf
                                                    <button type="submit" class="dropdown-item"><i class="fa fa-check-circle me-2 text-success"></i>اعتماد العقد</button>
                                                </form>
                                            @endif
                                            @if ($act['print'])
                                                <a class="dropdown-item" href="{{ route('Contracts.printTemplate', $contract->id) }}" target="_blank">
                                                    <i class="fa fa-print me-2 text-info"></i>طباعة
                                                </a>
                                            @endif
                                            @if ($act['destroy'])
                                                <div class="dropdown-divider"></div>
                                                <a class="dropdown-item text-danger" href="#" data-toggle="modal" data-target="#modal_DELETE{{ $contract->id }}">
                                                    <i class="fa fa-trash me-2"></i>حذف
                                                </a>
                                            @endif
                                        </div>
                                    </div>
                                    @endif

                                    @if ($act['destroy'])
                                        <div class="modal fade" id="modal_DELETE{{ $contract->id }}" tabindex="-1" role="dialog" aria-hidden="true">
                                            <div class="modal-dialog modal-dialog-centered" role="document">
                                                <div class="modal-content text-right">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">حذف العقد</h5>
                                                        <button type="button" class="close" data-dismiss="modal" aria-label="إغلاق"><span aria-hidden="true">&times;</span></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <p class="mb-1">سيُحذف عقد <strong>{{ $contract->employee->full_name ?? '' }}</strong> رقم <strong>{{ $contract->code }}</strong> نهائياً مع بنوده.</p>
                                                        <p class="stk-empty__hint mb-0">القسائم الصادرة سابقاً لا تتأثر، لكن مسير الرواتب لن يجد عقداً سارياً للموظف بعده.</p>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">إلغاء</button>
                                                        <form action="{{ route('Contracts.destroy', $contract->id) }}" method="POST" class="d-inline">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="btn btn-danger"><i class="feather icon-trash-2"></i> حذف نهائي</button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($contracts->hasPages())
                <div class="stk-table-foot">
                    <p class="stk-table-foot__count">عرض {{ $contracts->firstItem() }}–{{ $contracts->lastItem() }} من {{ $contracts->total() }}</p>
                    {{ $contracts->links() }}
                </div>
            @endif
        @else
            <div class="card-body">
                <div class="stk-empty">
                    <div class="stk-empty__icon"><i class="feather icon-file-text"></i></div>
                    <h5 class="stk-empty__title">لا توجد عقود</h5>
                    <p class="stk-empty__hint">
                        @if ($hasFilters)
                            لم يطابق البحث أيّ عقد — جرّب معايير أوسع
                        @else
                            أضف عقداً لموظف ليُقرأ منه راتبُه في مسير الرواتب
                        @endif
                    </p>
                    @if ($hasFilters)
                        <a href="{{ route('Contracts.index') }}" class="btn btn-outline-secondary"><i class="feather icon-rotate-ccw"></i> إزالة الفلاتر</a>
                    @elseif (hasPermission('contracts.create'))
                        <a href="{{ route('Contracts.create') }}" class="btn btn-primary"><i class="feather icon-plus"></i> إضافة عقد</a>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
@include('hr::components.table-menu')
@endpush
