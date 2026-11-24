@extends('hr::master')

@section('title')
    القسم: {{ $department->name }}
@stop

@php
    $isActive = (int) $department->status === 0;
    $canEdit = hasPermission('departments.edit');
    $canDestroy = hasPermission('departments.destroy');
    $canTitles = hasPermission('job_titles.show');
@endphp

@section('page-actions')
    {{-- الترتيب الموحّد لشاشات العرض: [تعديل] [المزيد ▾ … حذف] --}}
    @if ($canEdit)
        <a href="{{ route('department.edit', $department->id) }}" class="btn btn-primary">
            <i class="fa fa-edit"></i> تعديل
        </a>
    @endif
    @if ($canEdit || $canDestroy)
        <div class="btn-group">
            <button type="button" class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-ellipsis-h"></i> المزيد
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                @if ($canEdit)
                    <li>
                        <a class="dropdown-item" href="{{ route('department.updateStatus', $department->id) }}">
                            @if ($isActive)
                                <i class="fa fa-pause-circle me-2 text-warning"></i>تعطيل القسم
                            @else
                                <i class="fa fa-play-circle me-2 text-success"></i>تفعيل القسم
                            @endif
                        </a>
                    </li>
                @endif
                @if ($canEdit && $canDestroy)
                    <li><hr class="dropdown-divider"></li>
                @endif
                @if ($canDestroy)
                    <li>
                        <a class="dropdown-item text-danger" href="#" data-toggle="modal" data-target="#modal_DELETE{{ $department->id }}">
                            <i class="fa fa-trash me-2"></i>حذف القسم
                        </a>
                    </li>
                @endif
            </ul>
        </div>
    @endif
@endsection

@section('content')
<div class="content-body">
    @include('stock::components.page-head', [
        'title' => $department->name,
        'icon' => 'layers',
        'crumbs' => ['الرئيسية' => url('/'), 'الأقسام' => route('department.index'), $department->name => null],
    ])

    @include('layouts.alerts.error')
    @include('layouts.alerts.success')

    @include('hr::components.guide', [
        'key' => 'hr-departments.show',
        'title' => 'ما الذي تراه هنا؟',
        'blocks' => [
            'الموظفون والمديرون' => [
                '«الموظفون» من يحملون هذا القسمَ في ملفّاتهم — داخل نطاق رؤيتك. و«المديرون المعنيّون» قائمةٌ إدارية تُحدَّد من نموذج القسم.',
            ],
            'الحذف' => [
                'يُرفَض حذفُ قسمٍ مستعمَل: الحذفُ في قاعدة البيانات يسري على موظفيه ومسمّياته ومسيراتِ رواتبه. عطّله بدلاً من ذلك.',
            ],
        ],
    ])

    <div class="row">
        {{-- ══════════ بطاقة الهوية والتفاصيل ══════════ --}}
        <div class="col-lg-4 col-12">
            <div class="card overflow-hidden">
                <div class="card-body text-center">
                    <span class="stk-page-head__icon mx-auto mb-1" aria-hidden="true">
                        <i class="feather icon-layers"></i>
                    </span>
                    <h4 class="mb-25">{{ $department->name }}</h4>
                    <p class="stk-help mt-0 mb-1">قسم رقم #{{ $department->id }}@if ($department->short_name) · <span dir="auto">{{ $department->short_name }}</span>@endif</p>
                    <span class="stk-state stk-state--{{ $isActive ? 'on' : 'off' }}">{{ $isActive ? 'نشط' : 'غير نشط' }}</span>
                </div>
                <div class="stk-metrics" style="margin: 0; border-width: 1px 0 0; border-radius: 0; box-shadow: none">
                    <div class="stk-metric">
                        <span class="stk-metric__label">الموظفون</span>
                        <span class="stk-metric__value">{{ $members->total() }}</span>
                    </div>
                    <div class="stk-metric">
                        <span class="stk-metric__label">المديرون</span>
                        <span class="stk-metric__value">{{ $department->employees->count() }}</span>
                    </div>
                    <div class="stk-metric">
                        <span class="stk-metric__label">المسمّيات</span>
                        <span class="stk-metric__value">{{ $titles->count() }}</span>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h4 class="card-title"><i class="feather icon-file-text"></i> التفاصيل</h4>
                </div>
                <div class="card-body">
                    <dl class="stk-def">
                        <div class="stk-def__row">
                            <dt>الاختصار</dt>
                            <dd>{{ $department->short_name ?: '—' }}</dd>
                        </div>
                        <div class="stk-def__row">
                            <dt>الوصف</dt>
                            <dd>{{ $department->description ?: '—' }}</dd>
                        </div>
                        <div class="stk-def__row">
                            <dt>المديرون المعنيّون</dt>
                            <dd>
                                @forelse ($department->employees as $manager)
                                    <span class="d-block">{{ $manager->full_name }}@if ($manager->job_title) <span class="text-muted small">· {{ $manager->job_title->name }}</span>@endif</span>
                                @empty
                                    <span class="text-muted">لم يُحدَّد مدير</span>
                                @endforelse
                            </dd>
                        </div>
                        <div class="stk-def__row">
                            <dt>أُنشئ / آخر تعديل</dt>
                            <dd class="stk-num">
                                {{ optional($department->created_at)->format('Y-m-d H:i') ?? '—' }}
                                /
                                {{ optional($department->updated_at)->format('Y-m-d H:i') ?? '—' }}
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>

            @if ($usage !== [])
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title"><i class="feather icon-link"></i> مستعمَل في</h4>
                    </div>
                    <div class="card-body">
                        <dl class="stk-def">
                            @foreach ($usage as $usageLabel => $usageCount)
                                <div class="stk-def__row">
                                    <dt>{{ $usageLabel }}</dt>
                                    <dd class="stk-num">{{ $usageCount }}</dd>
                                </div>
                            @endforeach
                        </dl>
                        <p class="stk-help mb-0">لذلك لا يُحذف القسم — عطّله إن لم يعد مستعمَلاً. (أعدادٌ على مستوى المنشأة، تشمل المؤرشفين.)</p>
                    </div>
                </div>
            @endif
        </div>

        {{-- ══════════ الموظفون والمسمّيات والسجل ══════════ --}}
        <div class="col-lg-8 col-12">
            <div class="card stk-table-card" id="employees">
                <div class="card-body pb-0">
                    <ul class="nav nav-tabs stk-tabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" data-toggle="tab" href="#tab-employees" role="tab" aria-controls="tab-employees" aria-selected="true">
                                <i class="feather icon-users"></i> الموظفون
                                <span class="stk-count">{{ $members->total() }}</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="tab" href="#tab-titles" role="tab" aria-controls="tab-titles" aria-selected="false">
                                <i class="feather icon-briefcase"></i> المسمّيات الوظيفية
                                <span class="stk-count">{{ $titles->count() }}</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="tab" href="#tab-activity" role="tab" aria-controls="tab-activity" aria-selected="false">
                                <i class="feather icon-activity"></i> سجل النشاطات
                                <span class="stk-count">{{ $logs->flatten()->count() }}</span>
                            </a>
                        </li>
                    </ul>
                </div>

                <div class="tab-content">
                    <div class="tab-pane active" id="tab-employees" role="tabpanel">
                        @include('hr::organizational_structure.partials._members', [
                            'members' => $members,
                            'hideColumn' => 'department',
                            'emptyHint' => 'لا أحد من موظفي نطاقك يحمل هذا القسم في ملفّه. يُنسَب الموظف إلى القسم من «تعديل الموظف».',
                        ])
                    </div>

                    <div class="tab-pane" id="tab-titles" role="tabpanel">
                        @if ($titles->isNotEmpty())
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th style="width:44px">#</th>
                                            <th>المسمى الوظيفي</th>
                                            <th class="text-center">الحالة</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($titles as $i => $jobTitle)
                                            <tr>
                                                <td class="stk-num text-muted">{{ $i + 1 }}</td>
                                                <td>
                                                    @if ($canTitles)
                                                        <a href="{{ route('JobTitles.show', $jobTitle->id) }}" class="font-weight-bold">{{ $jobTitle->name }}</a>
                                                    @else
                                                        <span class="font-weight-bold">{{ $jobTitle->name }}</span>
                                                    @endif
                                                </td>
                                                <td class="text-center">
                                                    <span class="stk-state stk-state--{{ (int) $jobTitle->status === 0 ? 'on' : 'off' }}">{{ (int) $jobTitle->status === 0 ? 'نشط' : 'غير نشط' }}</span>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="card-body">
                                <div class="stk-empty">
                                    <div class="stk-empty__icon"><i class="feather icon-briefcase"></i></div>
                                    <h5 class="stk-empty__title">لا مسمّيات وظيفية</h5>
                                    <p class="stk-empty__hint">لم يُنسَب إلى هذا القسم أيُّ مسمّى وظيفي بعد.</p>
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="tab-pane" id="tab-activity" role="tabpanel">
                        <div class="card-body">
                            @include('hr::organizational_structure.partials._activity', ['logs' => $logs])
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if ($canDestroy)
        @include('hr::organizational_structure.partials._delete_modal', [
            'modalId' => 'modal_DELETE'.$department->id,
            'heading' => 'حذف القسم',
            'action' => route('department.delete', $department->id),
            'itemName' => $department->name,
            'subject' => 'القسم',
            'usage' => $usage,
        ])
    @endif
</div>
@endsection
