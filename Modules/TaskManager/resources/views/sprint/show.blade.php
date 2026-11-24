@extends('taskmanager::master')

@section('title')
    {{ $sprint->name }}
@stop

@section('content')
    <div class="content-header row">
        <div class="content-header-left col-md-9 col-12 mb-2">
            <div class="row breadcrumbs-top">
                <div class="col-12">
                    <h2 class="content-header-title float-left mb-0">{{ $sprint->name }}</h2>
                    <div class="breadcrumb-wrapper col-12">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">الرئيسية</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('sprints.index') }}">السبرنتات</a></li>
                            <li class="breadcrumb-item active">{{ $sprint->name }}</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
        <div class="content-header-right text-md-right col-md-3 col-12 d-md-block d-none">
            <a href="{{ route('sprints.edit', $sprint->id) }}" class="btn btn-outline-primary btn-sm">
                <i class="feather icon-edit"></i> تعديل
            </a>
        </div>
    </div>

    <div class="content-body">
        @php
            $total = collect($tasksByStatus)->flatten(1)->count();
            $completed = isset($tasksByStatus['completed']) ? $tasksByStatus['completed']->count() : 0;
            $rate = $total > 0 ? round(($completed / $total) * 100) : 0;
        @endphp

        <div class="card bg-primary text-white mb-2">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h4 class="text-white mb-0">
                            {{ $sprint->name }}
                            @if ($sprint->status === 'closed')
                                <span class="badge badge-light text-dark">مغلق</span>
                            @elseif ($sprint->status === 'active')
                                <span class="badge badge-light text-dark">نشط</span>
                            @endif
                        </h4>
                        <p class="mb-0">
                            <i class="feather icon-folder"></i> {{ $sprint->project->title ?? '' }}
                            &nbsp;|&nbsp;
                            {{ optional($sprint->start_date)->format('Y-m-d') }} — {{ optional($sprint->end_date)->format('Y-m-d') }}
                        </p>
                    </div>
                    <div>
                        <h5 class="text-white mb-0">الهدف:</h5>
                        <p class="mb-0">{{ $sprint->goal ?: 'لم يتم تحديد هدف' }}</p>
                    </div>
                    <div class="text-center">
                        <div class="sprint-progress-ring">{{ $rate }}%</div>
                        <small>{{ $completed }}/{{ $total }} مكتملة</small>
                    </div>
                    <div>
                        @if ($sprint->status !== 'closed')
                            <button id="closeSprintBtn" data-sprint-id="{{ $sprint->id }}" class="btn btn-light">
                                <i class="feather icon-lock"></i> إغلاق السبرنت
                            </button>
                        @else
                            <button class="btn btn-secondary" disabled>
                                <i class="feather icon-lock"></i> السبرنت مغلق
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <input type="hidden" id="currentSprintStatus" data-sprint-id="{{ $sprint->id }}" value="{{ $sprint->status }}">

        <div class="kanban-board task-board" style="position: relative; margin-top: 20px;">
            @include('taskmanager::tasks.partials.kanban_board', ['statuses' => $statuses, 'tasksByStatus' => $tasksByStatus, 'showAddButton' => false])
        </div>
    </div>
@endsection

@section('styles')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="{{ asset('assets/css/task-manager.css') }}">
    <style>
        .kanban-board {
            display: flex;
            flex-direction: row;
            gap: 16px;
            overflow-x: auto;
            padding-bottom: 12px;
        }
        .kanban-column-wrapper { flex: 0 0 300px; }
        .kanban-column-card {
            display: flex;
            flex-direction: column;
            max-height: calc(100vh - 380px);
            min-height: 300px;
        }
        .kanban-column-card .task-column { overflow-y: auto; flex: 1 1 auto; }
        .empty-column-hint { text-align: center; color: #adb5bd; font-size: 0.85rem; padding: 24px 0; }
        .sprint-progress-ring {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            border: 4px solid rgba(255,255,255,0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1rem;
        }
        .task-card-ghost { opacity: 0.4; background: #f0f2ff !important; border: 2px dashed #7367f0 !important; }
        .task-card-chosen { box-shadow: 0 10px 25px rgba(115, 103, 240, 0.25) !important; }
        .task-card-dragging { cursor: grabbing !important; transform: rotate(2deg); }
        .task-card { cursor: grab; }
    </style>
@endsection

@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
    <script src="{{ asset('modules/taskmanager/js/task-manager.js') }}?v={{ filemtime(public_path('modules/taskmanager/js/task-manager.js')) }}"></script>
@endsection
