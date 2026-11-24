@extends('hr::master')

@section('title')
    المسمى الوظيفي: {{ $title->name }}
@stop

@php
    $isActive = (int) $title->status === 0;
    $canEdit = hasPermission('job_titles.edit');
    $canDestroy = hasPermission('job_titles.destroy');
    $titleDept = $title->department;
    $deptHtml = $titleDept
        ? (hasPermission('departments.show')
            ? '<a href="'.e(route('department.show', $titleDept->id)).'">'.e($titleDept->name).'</a>'
            : e($titleDept->name))
        : '<span class="text-muted">غير محدد</span>';
@endphp

@section('page-actions')
    {{-- الترتيب الموحّد لشاشات العرض: [تعديل] [المزيد ▾ … حذف] --}}
    @if ($canEdit)
        <a href="{{ route('JobTitles.edit', $title->id) }}" class="btn btn-primary">
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
                        <a class="dropdown-item" href="{{ route('JobTitles.updateStatus', $title->id) }}">
                            @if ($isActive)
                                <i class="fa fa-pause-circle me-2 text-warning"></i>تعطيل المسمى
                            @else
                                <i class="fa fa-play-circle me-2 text-success"></i>تفعيل المسمى
                            @endif
                        </a>
                    </li>
                @endif
                @if ($canEdit && $canDestroy)
                    <li><hr class="dropdown-divider"></li>
                @endif
                @if ($canDestroy)
                    <li>
                        <a class="dropdown-item text-danger" href="#" data-toggle="modal" data-target="#modal_DELETE{{ $title->id }}">
                            <i class="fa fa-trash me-2"></i>حذف المسمى
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
        'title' => $title->name,
        'icon' => 'briefcase',
        'crumbs' => ['الرئيسية' => url('/'), 'المسميات الوظيفية' => route('JobTitles.index'), $title->name => null],
    ])

    @include('layouts.alerts.error')
    @include('layouts.alerts.success')

    @include('hr::components.guide', [
        'key' => 'hr-job-titles.show',
        'title' => 'ما الذي تراه هنا؟',
        'blocks' => [
            'الموظفون' => [
                'من يحملون هذا المسمّى في ملفّاتهم — داخل نطاق رؤيتك. والعددُ «على مستوى المنشأة» يشمل من هم خارج نطاقك والمؤرشفين.',
            ],
            'الحذف' => [
                'يُرفَض حذفُ مسمّى مستعمَل: الحذفُ في قاعدة البيانات يسري على ملفّات حامليه وعقودهم وقواعده المخصَّصة. عطّله بدلاً من ذلك.',
            ],
        ],
    ])

    @include('hr::organizational_structure.partials._show', [
        'record' => $title,
        'icon' => 'briefcase',
        'noun' => 'مسمى',
        'extraDefs' => ['القسم' => $deptHtml],
        'usage' => $usage,
        'members' => $members,
        'logs' => $logs,
        'hideColumn' => 'job_title',
        'membersEmptyHint' => 'لا أحد من موظفي نطاقك يحمل هذا المسمى. يُسنَد المسمى من ملفّ الموظف.',
    ])

    @if ($canDestroy)
        @include('hr::organizational_structure.partials._delete_modal', [
            'modalId' => 'modal_DELETE'.$title->id,
            'heading' => 'حذف المسمى الوظيفي',
            'action' => route('JobTitles.delete', $title->id),
            'itemName' => $title->name,
            'subject' => 'المسمى الوظيفي',
            'usage' => $usage,
        ])
    @endif
</div>
@endsection
