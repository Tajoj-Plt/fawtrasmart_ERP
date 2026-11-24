@extends('hr::master')

@section('title')
    نوع الوظيفة: {{ $type->name }}
@stop

@php
    $isActive = (int) $type->status === 0;
    $canEdit = hasPermission('job_types.edit');
    $canDestroy = hasPermission('job_types.destroy');
@endphp

@section('page-actions')
    {{-- الترتيب الموحّد لشاشات العرض: [تعديل] [المزيد ▾ … حذف] --}}
    @if ($canEdit)
        <a href="{{ route('ManagingJobTypes.edit', $type->id) }}" class="btn btn-primary">
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
                        <a class="dropdown-item" href="{{ route('ManagingJobTypes.updateStatus', $type->id) }}">
                            @if ($isActive)
                                <i class="fa fa-pause-circle me-2 text-warning"></i>تعطيل النوع
                            @else
                                <i class="fa fa-play-circle me-2 text-success"></i>تفعيل النوع
                            @endif
                        </a>
                    </li>
                @endif
                @if ($canEdit && $canDestroy)
                    <li><hr class="dropdown-divider"></li>
                @endif
                @if ($canDestroy)
                    <li>
                        <a class="dropdown-item text-danger" href="#" data-toggle="modal" data-target="#modal_DELETE{{ $type->id }}">
                            <i class="fa fa-trash me-2"></i>حذف النوع
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
        'title' => $type->name,
        'icon' => 'tag',
        'crumbs' => ['الرئيسية' => url('/'), 'أنواع الوظائف' => route('ManagingJobTypes.index'), $type->name => null],
    ])

    @include('layouts.alerts.error')
    @include('layouts.alerts.success')

    @include('hr::components.guide', [
        'key' => 'hr-job-types.show',
        'title' => 'ما الذي تراه هنا؟',
        'blocks' => [
            'الموظفون' => [
                'من يحملون هذا النوع في ملفّاتهم — داخل نطاق رؤيتك. والعددُ «على مستوى المنشأة» يشمل من هم خارج نطاقك والمؤرشفين.',
            ],
            'الحذف' => [
                'يُرفَض حذفُ نوعٍ مستعمَل: الحذفُ في قاعدة البيانات يسري على ملفّات الموظفين الذين يحملونه. عطّله بدلاً من ذلك.',
            ],
        ],
    ])

    @include('hr::organizational_structure.partials._show', [
        'record' => $type,
        'icon' => 'tag',
        'noun' => 'نوع',
        'usage' => $usage,
        'members' => $members,
        'logs' => $logs,
        'membersEmptyHint' => 'لا أحد من موظفي نطاقك يحمل هذا النوع. يُسنَد النوع من ملفّ الموظف.',
    ])

    @if ($canDestroy)
        @include('hr::organizational_structure.partials._delete_modal', [
            'modalId' => 'modal_DELETE'.$type->id,
            'heading' => 'حذف نوع الوظيفة',
            'action' => route('ManagingJobTypes.delete', $type->id),
            'itemName' => $type->name,
            'subject' => 'نوع الوظيفة',
            'usage' => $usage,
        ])
    @endif
</div>
@endsection
