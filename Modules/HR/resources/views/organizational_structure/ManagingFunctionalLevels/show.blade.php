@extends('hr::master')

@section('title')
    المستوى الوظيفي: {{ $level->name }}
@stop

@php
    $isActive = (int) $level->status === 0;
    $canEdit = hasPermission('job_levels.edit');
    $canDestroy = hasPermission('job_levels.destroy');
@endphp

@section('page-actions')
    {{-- الترتيب الموحّد لشاشات العرض: [تعديل] [المزيد ▾ … حذف] --}}
    @if ($canEdit)
        <a href="{{ route('ManagingFunctionalLevels.edit', $level->id) }}" class="btn btn-primary">
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
                        <a class="dropdown-item" href="{{ route('ManagingFunctionalLevels.updateStatus', $level->id) }}">
                            @if ($isActive)
                                <i class="fa fa-pause-circle me-2 text-warning"></i>تعطيل المستوى
                            @else
                                <i class="fa fa-play-circle me-2 text-success"></i>تفعيل المستوى
                            @endif
                        </a>
                    </li>
                @endif
                @if ($canEdit && $canDestroy)
                    <li><hr class="dropdown-divider"></li>
                @endif
                @if ($canDestroy)
                    <li>
                        <a class="dropdown-item text-danger" href="#" data-toggle="modal" data-target="#modal_DELETE{{ $level->id }}">
                            <i class="fa fa-trash me-2"></i>حذف المستوى
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
        'title' => $level->name,
        'icon' => 'bar-chart-2',
        'crumbs' => ['الرئيسية' => url('/'), 'المستويات الوظيفية' => route('ManagingFunctionalLevels.index'), $level->name => null],
    ])

    @include('layouts.alerts.error')
    @include('layouts.alerts.success')

    @include('hr::components.guide', [
        'key' => 'hr-functional-levels.show',
        'title' => 'ما الذي تراه هنا؟',
        'blocks' => [
            'الموظفون' => [
                'من هم في هذا المستوى بحسب ملفّاتهم — داخل نطاق رؤيتك. والعددُ «على مستوى المنشأة» يشمل من هم خارج نطاقك والمؤرشفين.',
            ],
            'الحذف' => [
                'يُرفَض حذفُ مستوى مستعمَل: الحذفُ في قاعدة البيانات يسري على ملفّات موظفيه وعقودهم. عطّله بدلاً من ذلك.',
            ],
        ],
    ])

    @include('hr::organizational_structure.partials._show', [
        'record' => $level,
        'icon' => 'bar-chart-2',
        'noun' => 'مستوى',
        'usage' => $usage,
        'members' => $members,
        'logs' => $logs,
        'membersEmptyHint' => 'لا أحد من موظفي نطاقك في هذا المستوى. يُسنَد المستوى من ملفّ الموظف.',
    ])

    @if ($canDestroy)
        @include('hr::organizational_structure.partials._delete_modal', [
            'modalId' => 'modal_DELETE'.$level->id,
            'heading' => 'حذف المستوى الوظيفي',
            'action' => route('ManagingFunctionalLevels.delete', $level->id),
            'itemName' => $level->name,
            'subject' => 'المستوى الوظيفي',
            'usage' => $usage,
        ])
    @endif
</div>
@endsection
