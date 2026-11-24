@extends('taskmanager::master')

@section('title')
    إدارة المهام
@stop

@section('content')

    <div class="content-header row">
        <div class="content-header-left col-md-9 col-12 mb-2">
            <div class="row breadcrumbs-top">
                <div class="col-12">
                    <h2 class="content-header-title float-left mb-0">إدارة المهام</h2>
                    <div class="breadcrumb-wrapper col-12">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="/">الرئيسية</a></li>
                            <li class="breadcrumb-item active">المهام</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="content-body">
        @php
            $tmAllTasks = collect($tasksByStatus)->flatten(1);
            $tmTotalTasks = $tmAllTasks->count();
            $tmCompletedTasks = isset($tasksByStatus['completed']) ? $tasksByStatus['completed']->count() : 0;
            $tmInProgressTasks = isset($tasksByStatus['in_progress']) ? $tasksByStatus['in_progress']->count() : 0;
            $tmOverdueTasks = isset($tasksByStatus['overdue']) ? $tasksByStatus['overdue']->count() : 0;
            $tmCompletionRate = $tmTotalTasks > 0 ? round(($tmCompletedTasks / $tmTotalTasks) * 100) : 0;
        @endphp
        @include('taskmanager::partials.page_hero', [
            'heroTitle' => 'لوحة',
            'heroHighlight' => 'إدارة المهام',
            'heroSubtitle' => (($managementType ?? 'scrum') === 'manual') ? 'إدارة مهامك بأسلوب بسيط ومباشر' : 'تابع مهامك وسباقاتك بأسلوب Trello/Jira التفاعلي',
            'heroStats' => [
                ['icon' => 'fas fa-list-check', 'value' => $tmTotalTasks, 'label' => 'إجمالي المهام'],
                ['icon' => 'fas fa-circle-check', 'value' => $tmCompletionRate.'%', 'label' => 'نسبة الإنجاز'],
                ['icon' => 'fas fa-spinner', 'value' => $tmInProgressTasks, 'label' => 'قيد التنفيذ'],
                ['icon' => 'fas fa-triangle-exclamation', 'value' => $tmOverdueTasks, 'label' => 'متأخرة'],
            ],
        ])

        <!-- معلومات السبرنت الحالي -->
        @if (($managementType ?? 'scrum') === 'manual')
            {{-- المشاريع اليدوية لا تستخدم السبرنتات إطلاقاً --}}
        @elseif ($currentSprint)
            <div class="card bg-primary text-white mb-2">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="text-white mb-0">
                                السبرنت الحالي: {{ $currentSprint->name }}
                                @if ($currentSprint->status === 'closed')
                                    <span class="badge badge-danger">مغلق</span>
                                @endif
                            </h4>
                            <p class="mb-0">
                                تاريخ البداية:
                                {{ $currentSprint->start_date ? (is_string($currentSprint->start_date) ? $currentSprint->start_date : $currentSprint->start_date->format('Y-m-d')) : 'غير محدد' }}
                                |
                                تاريخ النهاية:
                                {{ $currentSprint->end_date ? (is_string($currentSprint->end_date) ? $currentSprint->end_date : $currentSprint->end_date->format('Y-m-d')) : 'غير محدد' }}
                            </p>
                        </div>
                        <div>
                            <h5 class="text-white mb-0">الهدف:</h5>
                            <p class="mb-0">{{ $currentSprint->goal ?: 'لم يتم تحديد هدف' }}</p>
                        </div>
                        @if ($currentSprint->status !== 'closed')
                            <button id="closeSprintBtn" data-sprint-id="{{ $currentSprint->id }}" class="btn btn-light">
                                <i class="fas fa-times-circle me-1"></i> إغلاق السبرنت
                            </button>
                        @else
                            <button id="closeSprintBtn" data-sprint-id="{{ $currentSprint->id }}" class="btn btn-secondary"
                                disabled>
                                <i class="fas fa-lock me-1"></i> السبرنت مغلق
                            </button>
                        @endif
                    </div>
                </div>
            </div>

            <!-- حقل مخفي لحالة السبرنت الحالي -->
            <input type="hidden" id="currentSprintStatus" data-sprint-id="{{ $currentSprint->id }}"
                value="{{ $currentSprint->status }}">
        @else
            <div class="alert alert-warning mb-2">
                <h4 class="alert-heading">لا يوجد سبرنت نشط حالياً</h4>
                <p class="mb-0">قم بتفعيل سبرنت للبدء في العمل على المهام</p>
            </div>
        @endif

        <!-- شريط أدوات اللوحة المدمج -->
        <div class="card shadow-sm board-toolbar">
            <div class="card-body py-2">
                <div class="board-toolbar-row">
                <div class="board-filter-row">
                    <select class="form-control select2 board-quick-filter" id="filterProject" data-placeholder="المشروع">
                        <option value="">كل المشاريع</option>
                        @foreach ($projects as $projectOption)
                            <option value="{{ $projectOption->id }}" {{ (string) ($resolvedProjectId ?? '') === (string) $projectOption->id ? 'selected' : '' }}>{{ $projectOption->title }}</option>
                        @endforeach
                    </select>

                    @if(($managementType ?? 'scrum') !== 'manual')
                        <select class="form-control select2 board-quick-filter" id="filterSprint" data-placeholder="السبرنت">
                            <option value="">كل السبرنتات</option>
                            @foreach ($sprints as $sprint)
                                <option value="{{ $sprint->id }}">
                                    {{ $sprint->name }}
                                    @if ($sprint->status === 'closed')
                                        (مغلق)
                                    @endif
                                </option>
                            @endforeach
                        </select>
                    @endif

                    <select class="form-control select2 board-quick-filter" id="filterType" data-placeholder="النوع">
                        @if(($managementType ?? 'scrum') === 'manual')
                            <option value="task" selected>مهمة</option>
                            <option value="sub_task">مهمة فرعية</option>
                        @else
                            <option value="user_story" selected>User Story</option>
                            <option value="epic">Epic</option>
                            <option value="component">Component</option>
                            <option value="bug">Bug</option>
                            <option value="sub_task">Sub Task</option>
                        @endif
                    </select>

                    <select class="form-control select2 board-quick-filter" id="filterStatus" data-placeholder="الحالة">
                        <option value="">كل الحالات</option>
                        <option value="not_started">لم تبدأ</option>
                        <option value="in_progress">قيد التنفيذ</option>
                        <option value="completed">مكتملة</option>
                        <option value="overdue">متأخرة</option>
                    </select>

                    <select class="form-control select2 board-quick-filter" id="filterPriority" data-placeholder="الأولوية">
                        <option value="">كل الأولويات</option>
                        <option value="low">منخفضة</option>
                        <option value="medium">متوسطة</option>
                        <option value="high">عالية</option>
                        <option value="urgent">عاجلة</option>
                    </select>

                    <button class="btn btn-sm btn-outline-secondary flex-shrink-0" type="button" data-bs-toggle="collapse" data-bs-target="#moreFiltersCollapse">
                        <i class="feather icon-sliders"></i> فلاتر إضافية
                    </button>
                    <button class="btn btn-sm btn-flat-secondary flex-shrink-0" id="btnResetFilters" title="إعادة تعيين الفلاتر">
                        <i class="feather icon-refresh-cw"></i>
                    </button>
                </div>

                    <div class="board-toolbar-actions">
                        @if ($users->count())
                            <div class="assignee-filter-stack">
                                @foreach ($users->take(6) as $user)
                                    <img src="{{ $user->avatar_url }}" class="assignee-quick-filter" data-user-id="{{ $user->id }}" title="تصفية حسب: {{ $user->name }}" alt="{{ $user->name }}">
                                @endforeach
                            </div>
                        @endif
                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createTaskModal">
                            <i class="feather icon-plus"></i> مهمة جديدة
                        </button>
                    </div>
                </div>

                <div class="collapse mt-3" id="moreFiltersCollapse">
                    <div class="row">
                        @if(($managementType ?? 'scrum') !== 'manual')
                            <div class="col-md-3">
                                <label>المكون (Component)</label>
                                <select class="form-control select2" id="filterComponent">
                                    <option value="">جميع المكونات</option>
                                    @foreach ($projects as $project)
                                        @php
                                            $projectComponents = $project->tasks->where('type', 'component');
                                        @endphp
                                        @if ($projectComponents->count() > 0)
                                            <optgroup label="{{ $project->title }}">
                                                @foreach ($projectComponents as $component)
                                                    <option value="{{ $component->id }}">{{ $component->title }}</option>
                                                @endforeach
                                            </optgroup>
                                        @endif
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label>الإبيك (Epic)</label>
                                <select class="form-control select2" id="filterEpic">
                                    <option value="">جميع الإبيكات</option>
                                </select>
                            </div>
                        @endif
                        <div class="col-md-3">
                            <label>المعين</label>
                            <select class="form-control select2 board-quick-filter" id="filterAssignee">
                                <option value="">جميع المستخدمين</option>
                                @foreach ($users as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="includeChildrenCheckbox" value="1">
                                <label class="form-check-label" for="includeChildrenCheckbox">
                                    إظهار المهام الفرعية (Children)
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- لوحة المهام بالسحب والإفلات -->
        <div class="kanban-board task-board" style="position: relative; margin-top: 20px;">
            @include('taskmanager::tasks.partials.kanban_board', ['statuses' => $statuses, 'tasksByStatus' => $tasksByStatus])
        </div>
    </div>

    <!-- Include Modals -->
    @include('taskmanager::tasks.modals.create')
    @include('taskmanager::tasks.modals.edit')
    @include('taskmanager::tasks.modals.view')
@endsection
@section('styles')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="{{ asset('assets/css/task-manager.css') }}?v={{ filemtime(public_path('assets/css/task-manager.css')) }}">
    <style>
        /* لوحة كانبان أفقية قابلة للتمرير، على غرار Trello/Jira */
        .kanban-board.task-board {
            display: flex !important;
            flex-direction: row !important;
            flex-wrap: nowrap !important;
            gap: 16px;
            overflow-x: auto;
            padding-bottom: 12px;
            scroll-snap-type: x proximity;
        }

        .kanban-column-wrapper {
            flex: 0 0 300px;
            scroll-snap-align: start;
        }

        .kanban-column-card {
            display: flex;
            flex-direction: column;
            max-height: calc(100vh - 320px);
            min-height: 300px;
        }

        .kanban-column-card .task-column {
            overflow-y: auto;
            flex: 1 1 auto;
        }

        .btn-add-task {
            width: 26px;
            height: 26px;
            border: none;
            border-radius: 6px;
            background: rgba(115, 103, 240, 0.12);
            color: #7367f0;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }

        .btn-add-task:hover {
            background: #7367f0;
            color: #fff;
            transform: scale(1.08);
        }

        .empty-column-hint {
            text-align: center;
            color: #adb5bd;
            font-size: 0.85rem;
            padding: 24px 0;
        }

        @media (max-width: 768px) {
            .kanban-column-wrapper {
                flex: 0 0 85vw;
            }
        }

        /* ===== شريط الأدوات المدمج — أفقي دائماً، بدون تكديس عمودي ===== */
        .board-toolbar {
            border: 1px solid #edf0f5;
            border-radius: 14px !important;
            overflow: hidden;
        }

        .board-toolbar-row {
            display: flex;
            flex-wrap: nowrap;
            align-items: center;
            gap: 0.75rem;
        }

        .board-filter-row {
            display: flex;
            flex-wrap: nowrap;
            align-items: center;
            gap: 0.5rem;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            padding-bottom: 2px;
            flex: 1 1 auto;
            min-width: 0;
        }

        .board-filter-row::-webkit-scrollbar { height: 5px; }
        .board-filter-row::-webkit-scrollbar-thumb { background: #dfe1e6; border-radius: 999px; }

        .board-toolbar-actions {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-shrink: 0;
        }

        .board-toolbar .board-quick-filter {
            min-width: 150px;
        }
        .board-toolbar .select2-container {
            min-width: 150px;
            flex-shrink: 0;
        }

        @media (max-width: 992px) {
            .board-toolbar-row { flex-wrap: wrap; }
            .board-toolbar-actions { width: 100%; justify-content: space-between; }
        }

        .assignee-filter-stack {
            display: flex;
            align-items: center;
        }
        .assignee-quick-filter {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            border: 2px solid #fff;
            box-shadow: 0 0 0 1px #dfe1e6;
            margin-inline-start: -8px;
            cursor: pointer;
            transition: all 0.2s ease;
            object-fit: cover;
        }
        .assignee-filter-stack .assignee-quick-filter:first-child {
            margin-inline-start: 0;
        }
        .assignee-quick-filter:hover {
            transform: translateY(-3px);
            z-index: 2;
            position: relative;
        }
        .assignee-quick-filter.active {
            box-shadow: 0 0 0 3px #7367f0;
            transform: translateY(-3px);
            z-index: 2;
            position: relative;
        }

        /* ===== حالات السحب عبر SortableJS ===== */
        .task-card-ghost {
            opacity: 0.4;
            background: #f0f2ff !important;
            border: 2px dashed #7367f0 !important;
        }
        .task-card-chosen {
            box-shadow: 0 10px 25px rgba(115, 103, 240, 0.25) !important;
        }
        .task-card-dragging {
            cursor: grabbing !important;
            transform: rotate(2deg);
        }
        .task-card {
            cursor: grab;
        }
    </style>
@endsection

@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
    <script src="{{ asset('modules/taskmanager/js/task-manager.js') }}?v={{ filemtime(public_path('modules/taskmanager/js/task-manager.js')) }}"></script>
    <script src="{{ asset('modules/taskmanager/js/tasks.js') }}?v={{ filemtime(public_path('modules/taskmanager/js/tasks.js')) }}"></script>
@endsection
