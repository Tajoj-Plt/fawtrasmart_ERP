@extends('taskmanager::master')

@section('title', 'إدارة المهام')

@section('content')
<div class="container-fluid">
 <div class="content-header row">
        <div class="content-header-left col-md-9 col-12 mb-2">
            <div class="row breadcrumbs-top">
                <div class="col-12">
                    <h2 class="content-header-title float-left mb-0">إدارة قائمة المهام</h2>
                    <div class="breadcrumb-wrapper col-12">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">الرئيسيه</a></li>
                            <li class="breadcrumb-item active">عرض</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
        <!-- Add Sprint Button -->
        <div class="content-header-right col-md-3 col-12 mb-2">
            <button type="button" class="btn btn-primary float-right" id="createSprintBtn">
                <i class="fas fa-plus"></i> إنشاء سبرنت جديد
            </button>
        </div>
    </div>

    
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">إدارة المهام</h3>
                </div>
                <div class="card-body">
                    <!-- Nav tabs -->
                    <ul class="nav nav-tabs" id="taskTabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="components-tab" data-toggle="tab" href="#components" role="tab">
                                <i class="fas fa-puzzle-piece"></i> المكونات
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="epics-tab" data-toggle="tab" href="#epics" role="tab">
                                <i class="fas fa-bookmark"></i> الإبيكات
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="stories-tab" data-toggle="tab" href="#stories" role="tab">
                                <i class="fas fa-book"></i> قصص المستخدم
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="subtasks-tab" data-toggle="tab" href="#subtasks" role="tab">
                                <i class="fas fa-tasks"></i> المهام الفرعية
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="bugs-tab" data-toggle="tab" href="#bugs" role="tab">
                                <i class="fas fa-bug"></i> الأخطاء
                            </a>
                        </li>
                    </ul>

                    <!-- Tab content -->
                    <div class="tab-content mt-3" id="taskTabContent">
                        <!-- Components Tab -->
                        <div class="tab-pane fade show active" id="components" role="tabpanel">
                            <div class="mb-3">
                                <button type="button" class="btn btn-primary add-task-btn" data-type="component" data-title="إضافة مكون جديد">
                                    <i class="fas fa-plus"></i> إضافة مكون جديد
                                </button>
                            </div>
                            <div class="filters-section mb-3">
                                <div class="row">
                                    <div class="col-md-3">
                                        <select class="form-control filter-select" id="component-project-filter" data-type="component">
                                            <option value="">جميع المشاريع</option>
                                            @foreach($projects as $project)
                                                <option value="{{ $project->id }}">{{ $project->title }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <select class="form-control filter-select" id="component-user-filter" data-type="component">
                                            <option value="">جميع المستخدمين</option>
                                            @foreach($users as $user)
                                                <option value="{{ $user->id }}">{{ $user->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <select class="form-control filter-select" id="component-status-filter" data-type="component">
                                            <option value="">جميع الحالات</option>
                                            <option value="not_started">لم تبدأ</option>
                                            <option value="in_progress">قيد التنفيذ</option>
                                            <option value="completed">مكتمل</option>
                                            <option value="overdue">متأخر</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <select class="form-control filter-select" id="component-priority-filter" data-type="component">
                                            <option value="">جميع الأولويات</option>
                                            <option value="low">منخفضة</option>
                                            <option value="medium">متوسطة</option>
                                            <option value="high">عالية</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <select class="form-control filter-select" id="component-sprint-filter" data-type="component">
                                            <option value="">جميع السبرنتات</option>
                                            @foreach($sprints as $sprint)
                                                <option value="{{ $sprint->id }}">{{ $sprint->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <button class="btn btn-secondary reset-filters" data-type="component">إعادة تعيين</button>
                                    </div>
                                </div>
                            </div>
                            <div class="table" id="components-table-container">
                                @include('taskmanager::tasks.partials.components-table', ['components' => $initialComponents])
                            </div>
                            <div class="pagination-container" id="components-pagination"></div>
                        </div>

                        <!-- Epics Tab -->
                        <div class="tab-pane fade" id="epics" role="tabpanel">
                            <div class="mb-3">
                                <button type="button" class="btn btn-primary add-task-btn" data-type="epic" data-title="إضافة إبيك جديد">
                                    <i class="fas fa-plus"></i> إضافة إبيك جديد
                                </button>
                            </div>
                            <div class="filters-section mb-3">
                                <div class="row">
                                    <div class="col-md-3">
                                        <select class="form-control filter-select" id="epic-component-filter" data-type="epic">
                                            <option value="">جميع المكونات</option>
                                            @foreach($components as $component)
                                                <option value="{{ $component->id }}">{{ $component->title }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <select class="form-control filter-select" id="epic-user-filter" data-type="epic">
                                            <option value="">جميع المستخدمين</option>
                                            @foreach($users as $user)
                                                <option value="{{ $user->id }}">{{ $user->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <select class="form-control filter-select" id="epic-status-filter" data-type="epic">
                                            <option value="">جميع الحالات</option>
                                            <option value="not_started">لم تبدأ</option>
                                            <option value="in_progress">قيد التنفيذ</option>
                                            <option value="completed">مكتمل</option>
                                            <option value="overdue">متأخر</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <select class="form-control filter-select" id="epic-priority-filter" data-type="epic">
                                            <option value="">جميع الأولويات</option>
                                            <option value="low">منخفضة</option>
                                            <option value="medium">متوسطة</option>
                                            <option value="high">عالية</option>
                                        </select>
                                    </div>
                                    <div class="col-md-1">
                                        <select class="form-control filter-select" id="epic-sprint-filter" data-type="epic">
                                            <option value="">جميع السبرنتات</option>
                                            @foreach($sprints as $sprint)
                                                <option value="{{ $sprint->id }}">{{ $sprint->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-1">
                                        <button class="btn btn-secondary reset-filters" data-type="epic">إعادة تعيين</button>
                                    </div>
                                </div>
                            </div>
                            <div class="table" id="epics-table-container">
                                @include('taskmanager::tasks.partials.epics-table', ['epics' => $initialEpics])
                            </div>
                            <div class="pagination-container" id="epics-pagination"></div>
                        </div>

                        <!-- Stories Tab -->
                        <div class="tab-pane fade" id="stories" role="tabpanel">
                            <div class="mb-3">
                                <button type="button" class="btn btn-primary add-task-btn" data-type="user_story" data-title="إضافة قصة مستخدم جديدة">
                                    <i class="fas fa-plus"></i> إضافة قصة مستخدم
                                </button>
                            </div>
                            <div class="filters-section mb-3">
                                <div class="row">
                                    <div class="col-md-3">
                                        <select class="form-control filter-select" id="story-epic-filter" data-type="user_story">
                                            <option value="">جميع الإبيكات</option>
                                            @foreach($epics as $epic)
                                                <option value="{{ $epic->id }}">{{ $epic->title }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <select class="form-control filter-select" id="story-user-filter" data-type="user_story">
                                            <option value="">جميع المستخدمين</option>
                                            @foreach($users as $user)
                                                <option value="{{ $user->id }}">{{ $user->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <select class="form-control filter-select" id="story-status-filter" data-type="user_story">
                                            <option value="">جميع الحالات</option>
                                            <option value="not_started">لم تبدأ</option>
                                            <option value="in_progress">قيد التنفيذ</option>
                                            <option value="completed">مكتمل</option>
                                            <option value="overdue">متأخر</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <select class="form-control filter-select" id="story-priority-filter" data-type="user_story">
                                            <option value="">جميع الأولويات</option>
                                            <option value="low">منخفضة</option>
                                            <option value="medium">متوسطة</option>
                                            <option value="high">عالية</option>
                                        </select>
                                    </div>
                                    <div class="col-md-1">
                                        <select class="form-control filter-select" id="story-sprint-filter" data-type="user_story">
                                            <option value="">جميع السبرنتات</option>
                                            @foreach($sprints as $sprint)
                                                <option value="{{ $sprint->id }}">{{ $sprint->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-1">
                                        <button class="btn btn-secondary reset-filters" data-type="user_story">إعادة تعيين</button>
                                    </div>
                                </div>
                            </div>
                            <div class="table" id="stories-table-container">
                                @include('taskmanager::tasks.partials.stories-table', ['stories' => $initialStories])
                            </div>
                            <div class="pagination-container" id="stories-pagination"></div>
                        </div>

                        <!-- Sub Tasks Tab -->
                        <div class="tab-pane fade" id="subtasks" role="tabpanel">
                            <div class="mb-3">
                                <button type="button" class="btn btn-primary add-task-btn" data-type="sub_task" data-title="إضافة مهمة فرعية جديدة">
                                    <i class="fas fa-plus"></i> إضافة مهمة فرعية
                                </button>
                            </div>
                            <div class="filters-section mb-3">
                                <div class="row">
                                    <div class="col-md-3">
                                        <select class="form-control filter-select" id="subtask-user-filter" data-type="sub_task">
                                            <option value="">جميع المستخدمين</option>
                                            @foreach($users as $user)
                                                <option value="{{ $user->id }}">{{ $user->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <select class="form-control filter-select" id="subtask-status-filter" data-type="sub_task">
                                            <option value="">جميع الحالات</option>
                                            <option value="not_started">لم تبدأ</option>
                                            <option value="in_progress">قيد التنفيذ</option>
                                            <option value="completed">مكتمل</option>
                                            <option value="overdue">متأخر</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <select class="form-control filter-select" id="subtask-priority-filter" data-type="sub_task">
                                            <option value="">جميع الأولويات</option>
                                            <option value="low">منخفضة</option>
                                            <option value="medium">متوسطة</option>
                                            <option value="high">عالية</option>
                                        </select>
                                    </div>
                                    <div class="col-md-1">
                                        <select class="form-control filter-select" id="subtask-sprint-filter" data-type="sub_task">
                                            <option value="">جميع السبرنتات</option>
                                            @foreach($sprints as $sprint)
                                                <option value="{{ $sprint->id }}">{{ $sprint->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-1">
                                        <button class="btn btn-secondary reset-filters" data-type="sub_task">إعادة تعيين</button>
                                    </div>
                                </div>
                            </div>
                            <div class="table" id="subtasks-table-container">
                                @include('taskmanager::tasks.partials.subtasks-table', ['subTasks' => $initialSubTasks])
                            </div>
                            <div class="pagination-container" id="subtasks-pagination"></div>
                        </div>

                        <!-- Bugs Tab -->
                        <div class="tab-pane fade" id="bugs" role="tabpanel">
                            <div class="mb-3">
                                <button type="button" class="btn btn-primary add-task-btn" data-type="bug" data-title="إضافة خطأ جديد">
                                    <i class="fas fa-plus"></i> إضافة خطأ
                                </button>
                            </div>
                            <div class="filters-section mb-3">
                                <div class="row">
                                    <div class="col-md-3">
                                        <select class="form-control filter-select" id="bug-user-filter" data-type="bug">
                                            <option value="">جميع المستخدمين</option>
                                            @foreach($users as $user)
                                                <option value="{{ $user->id }}">{{ $user->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <select class="form-control filter-select" id="bug-status-filter" data-type="bug">
                                            <option value="">جميع الحالات</option>
                                            <option value="not_started">لم تبدأ</option>
                                            <option value="in_progress">قيد التنفيذ</option>
                                            <option value="completed">مكتمل</option>
                                            <option value="overdue">متأخر</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <select class="form-control filter-select" id="bug-priority-filter" data-type="bug">
                                            <option value="">جميع الأولويات</option>
                                            <option value="low">منخفضة</option>
                                            <option value="medium">متوسطة</option>
                                            <option value="high">عالية</option>
                                        </select>
                                    </div>
                                    <div class="col-md-1">
                                        <select class="form-control filter-select" id="bug-sprint-filter" data-type="bug">
                                            <option value="">جميع السبرنتات</option>
                                            @foreach($sprints as $sprint)
                                                <option value="{{ $sprint->id }}">{{ $sprint->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-1">
                                        <button class="btn btn-secondary reset-filters" data-type="bug">إعادة تعيين</button>
                                    </div>
                                </div>
                            </div>
                            <div class="table" id="bugs-table-container">
                                @include('taskmanager::tasks.partials.bugs-table', ['bugs' => $initialBugs])
                            </div>
                            <div class="pagination-container" id="bugs-pagination"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@include('taskmanager::tasks.modals.create')
@include('taskmanager::tasks.modals.edit')
@include('taskmanager::tasks.modals.view')

@endsection

@push('styles')
<style>
    .task-card {
        transition: all 0.3s ease;
    }
    .task-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }
    .nav-tabs .nav-link {
        color: #6c757d;
    }
    .nav-tabs .nav-link.active {
        color: #007bff;
        font-weight: bold;
    }
    .table th {
        background-color: #f8f9fa;
    }
    .pagination-container {
        margin-top: 15px;
        text-align: center;
    }
    .load-more-btn {
        margin: 15px 0;
    }
    .sprint-badge {
        font-size: 0.8em;
    }
</style>
@endpush

@push('scripts')
<!-- Include SweetAlert2 library -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<!-- Include the tasks JavaScript file -->
<script src="{{ asset('modules/taskmanager/js/tasks.js') }}?v={{ filemtime(public_path('modules/taskmanager/js/tasks.js')) }}"></script>

<script>
$(document).ready(function() {
    // Apply filters for the active tab on page load
    applyFilters('component');
    
    // Handle filter changes
    $('.filter-select').on('change', function() {
        const type = $(this).data('type');
        applyFilters(type);
    });
    
    // Handle reset filter buttons
    $('.reset-filters').on('click', function() {
        const type = $(this).data('type');
        
        // Reset all filters for this type
        $(`.filter-select[data-type="${type}"]`).val('');
        
        // Apply filters
        applyFilters(type);
    });
    
    // Handle add task button clicks
    $('.add-task-btn').on('click', function() {
        const type = $(this).data('type');
        const title = $(this).data('title');

        // Reset form
        $('#createTaskForm')[0].reset();

        // Set task type and modal title
        $('#taskType').val(type);
        $('#createTaskModalTitle').text(title);

        // Hide all task fields first
        $('.task-fields').hide();

        // Show fields based on task type
        switch(type) {
            case 'component':
                $('.component-fields').show();
                $('#projectId').prop('required', true);
                // No parent tasks needed for components
                break;
            case 'epic':
                $('.epic-fields').show();
                $('#epicParentId').prop('required', true);
                loadComponents('epicParentId');
                break;
            case 'user_story':
                $('.user-story-fields').show();
                $('#storyParentId, #estimatedHours').prop('required', true);
                loadEpics('storyParentId'); // Load all epics initially
                break;
            case 'sub_task':
                $('.sub-task-fields').show();
                $('#subTaskParentId').prop('required', true);
                loadUserStories('subTaskParentId'); // Load all user stories initially
                break;
            case 'bug':
                $('.bug-fields').show();
                $('#bugParentId').prop('required', true);
                loadComponents('bugParentId');
                break;
        }

        $('#createTaskModal').modal('show');
    });

    // Handle edit task button clicks
    $(document).on('click', '.edit-task', function() {
        const taskId = $(this).data('task-id');
        editTask(taskId);
    });
    
    // Handle delete task button clicks
    $(document).on('click', '.delete-task', function() {
        const taskId = $(this).data('task-id');
        deleteTask(taskId);
    });
    
    // Handle view task button clicks
    $(document).on('click', '.view-task', function() {
        const taskId = $(this).data('task-id');
        showTaskDetails(taskId);
    });

    // Handle add task button clicks from task rows
    $(document).on('click', '.add-task-btn', function() {
        const type = $(this).data('type');
        const parentId = $(this).data('parent-id');
        const title = $(this).data('title');

        // Reset form
        $('#createTaskForm')[0].reset();

        // Set task type and modal title
        $('#taskType').val(type);
        $('#createTaskModalTitle').text(title);

        // Hide all task fields first
        $('.task-fields').hide();

        // Show fields based on task type
        switch(type) {
            case 'component':
                $('.component-fields').show();
                $('#projectId').prop('required', true);
                // No parent tasks needed for components
                break;
            case 'epic':
                $('.epic-fields').show();
                $('#epicParentId').prop('required', true);
                loadComponents('epicParentId');
                break;
            case 'user_story':
                $('.user-story-fields').show();
                $('#storyParentId, #estimatedHours').prop('required', true);
                loadEpics('storyParentId'); // Load all epics initially
                break;
            case 'sub_task':
                $('.sub-task-fields').show();
                $('#subTaskParentId').prop('required', true);
                loadUserStories('subTaskParentId'); // Load all user stories initially
                break;
            case 'bug':
                $('.bug-fields').show();
                $('#bugParentId').prop('required', true);
                loadComponents('bugParentId');
                break;
        }

        $('#createTaskModal').modal('show');
    });

    // Function to show task details in edit modal
    function editTask(taskId) {
        $.ajax({
            url: getLocalePrefix() + '/tasks/' + taskId,
            method: 'GET',
            success: function(response) {
                const task = response.task;

                // Populate form fields
                $('#editTaskId').val(task.id);
                $('#editTaskType').val(task.type);
                $('#editTitle').val(task.title);
                $('#editDescription').val(task.description);
                $('#editStatus').val(task.status);
                $('#editPriority').val(task.priority);
                $('#editStartDate').val(task.start_date);
                $('#editDueDate').val(task.due_date);
                $('#editSprintId').val(task.sprint_id);

                // Set project for components
                if (task.project && task.type === 'component') {
                    $('#editProjectId').val(task.project.id);
                }

                // Set parent task
                if (task.parent) {
                    switch(task.type) {
                        case 'epic':
                            $('#editEpicParentId').val(task.parent.id);
                            $('#editParentTaskId').val(task.parent.id);
                            break;
                        case 'user_story':
                            $('#editStoryParentId').val(task.parent.id);
                            $('#editParentTaskId').val(task.parent.id);
                            $('#editEstimatedHours').val(task.estimated_hours);
                            break;
                        case 'sub_task':
                            $('#editSubTaskParentId').val(task.parent.id);
                            $('#editParentTaskId').val(task.parent.id);
                            break;
                        case 'bug':
                            $('#editBugParentId').val(task.parent.id);
                            $('#editParentTaskId').val(task.parent.id);
                            break;
                    }
                }

                // Show/hide fields based on task type
                $('.edit-task-fields').hide();
                switch(task.type) {
                    case 'component':
                        $('.edit-component-fields').show();
                        break;
                    case 'epic':
                        $('.edit-epic-fields').show();
                        break;
                    case 'user_story':
                        $('.edit-user-story-fields').show();
                        break;
                    case 'sub_task':
                        $('.edit-sub-task-fields').show();
                        break;
                    case 'bug':
                        $('.edit-bug-fields').show();
                        break;
                }

                // Set assigned users
                if (task.assigned_users) {
                    const assignedUserIds = task.assigned_users.map(user => user.id);
                    $('#editAssignedUsers').val(assignedUserIds);
                }

                // Add event listeners for parent task selection in edit modal
                // Remove any existing event listeners to prevent duplication
                $('#editEpicParentId, #editStoryParentId, #editSubTaskParentId, #editBugParentId').off('change');
                
                // Add event listeners to update the hidden parent task ID field
                $('#editEpicParentId').on('change', function() {
                    $('#editParentTaskId').val($(this).val());
                });
                
                $('#editStoryParentId').on('change', function() {
                    $('#editParentTaskId').val($(this).val());
                });
                
                $('#editSubTaskParentId').on('change', function() {
                    $('#editParentTaskId').val($(this).val());
                });
                
                $('#editBugParentId').on('change', function() {
                    $('#editParentTaskId').val($(this).val());
                });

                $('#editTaskModal').modal('show');
            },
            error: function(xhr) {
                alert('حدث خطأ أثناء تحميل تفاصيل المهمة');
            }
        });
    }

    // Function to show task details in view modal
    function showTaskDetails(taskId) {
        $.ajax({
            url: getLocalePrefix() + '/tasks/' + taskId,
            method: 'GET',
            success: function(response) {
                const task = response.task;

                // Populate view fields
                $('#viewTaskTitle').text(task.title);
                $('#viewTaskDescription').text(task.description || '-');
                
                // Set task type
                let typeText = '';
                switch(task.type) {
                    case 'component':
                        typeText = 'مكون';
                        break;
                    case 'epic':
                        typeText = 'إبيك';
                        break;
                    case 'user_story':
                        typeText = 'قصة مستخدم';
                        break;
                    case 'sub_task':
                        typeText = 'مهمة فرعية';
                        break;
                    case 'bug':
                        typeText = 'خطأ برمجي';
                        break;
                }
                $('#viewTaskType').text(typeText);
                
                // Set priority
                let priorityText = '';
                switch(task.priority) {
                    case 'high':
                        priorityText = 'عالية';
                        break;
                    case 'medium':
                        priorityText = 'متوسطة';
                        break;
                    case 'low':
                        priorityText = 'منخفضة';
                        break;
                }
                $('#viewTaskPriority').text(priorityText);
                
                // Set status
                let statusText = '';
                switch(task.status) {
                    case 'not_started':
                        statusText = 'لم تبدأ';
                        break;
                    case 'in_progress':
                        statusText = 'قيد التنفيذ';
                        break;
                    case 'completed':
                        statusText = 'مكتمل';
                        break;
                    case 'overdue':
                        statusText = 'متأخر';
                        break;
                }
                $('#viewTaskStatus').text(statusText);
                
                $('#viewTaskStartDate').text(task.start_date || '-');
                $('#viewTaskDueDate').text(task.due_date || '-');
                $('#viewTaskSprint').text(task.sprint ? task.sprint.name : '-');
                $('#viewTaskProject').text(task.project ? task.project.name : '-');
                $('#viewTaskParent').text(task.parent ? task.parent.title : '-');
                $('#viewTaskEstimatedHours').text(task.estimated_hours || '-');
                
                // Set assigned users
                if (task.assigned_users && task.assigned_users.length > 0) {
                    const assignedUsers = task.assigned_users.map(user => user.name).join(', ');
                    $('#viewTaskAssignedUsers').text(assignedUsers);
                } else {
                    $('#viewTaskAssignedUsers').text('-');
                }

                $('#viewTaskModal').modal('show');
            },
            error: function(xhr) {
                alert('حدث خطأ أثناء تحميل تفاصيل المهمة');
            }
        });
    }

    // Function to delete task with SweetAlert2 confirmation
    function deleteTask(taskId) {
        Swal.fire({
            title: 'هل أنت متأكد؟',
            text: "لن تتمكن من التراجع عن هذا الإجراء!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'نعم، احذفها!',
            cancelButtonText: 'إلغاء'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: getLocalePrefix() + '/tasks/' + taskId,
                    method: 'DELETE',
                    data: {
                        _token: $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        // Remove the task row from the table
                        $(`tr[data-task-id="${taskId}"]`).remove();
                        
                        // Show success message
                        Swal.fire(
                            'تم الحذف!',
                            'تم حذف المهمة بنجاح.',
                            'success'
                        );
                    },
                    error: function(xhr) {
                        // Show error message
                        Swal.fire(
                            'خطأ!',
                            'حدث خطأ أثناء حذف المهمة.',
                            'error'
                        );
                    }
                });
            }
        });
    }

    // Handle edit form submission
    $('#editTaskForm').on('submit', function(e) {
        e.preventDefault();
        
        const taskId = $('#editTaskId').val();
        const formData = $(this).serialize();
        
        $.ajax({
            url: getLocalePrefix() + '/tasks/' + taskId,
            method: 'PUT',
            data: formData,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                console.log('Update successful, closing modal...');
                // Close the modal using multiple methods to ensure it closes
                $('#editTaskModal').modal('hide');
                
                // Show success message
                Swal.fire(
                    'تم التحديث!',
                    'تم تحديث المهمة بنجاح.',
                    'success'
                );
                
                // Small delay to ensure modal is closed before updating UI
                setTimeout(function() {
                    // Update the specific task row in the table instead of refreshing the entire table
                    updateTaskRow(response.task);
                }, 500);
            },
            error: function(xhr) {
                console.log('Update error:', xhr);
                if (xhr.status === 422) {
                    // Validation errors
                    const errors = xhr.responseJSON.errors;
                    let errorMessage = '';
                    for (let field in errors) {
                        errorMessage += errors[field][0] + '\n';
                    }
                    Swal.fire(
                        'خطأ في التحقق!',
                        errorMessage,
                        'error'
                    );
                } else {
                    // Other errors
                    Swal.fire(
                        'خطأ!',
                        'حدث خطأ أثناء تحديث المهمة.',
                        'error'
                    );
                }
            }
        });
    });

    // Function to update a specific task row in the table
    function updateTaskRow(task) {
        try {
            // Find the row in the table
            const $row = $(`tr[data-task-id="${task.id}"]`);
            
            if ($row.length > 0) {
                console.log('Updating row for task:', task);
                // Update the row content based on task type
                // This is a simplified version - in a real implementation, you might want to 
                // render a partial view or update each cell individually
                
                // Update title
                $row.find('td:first-child').html(`
                    <div class="d-flex align-items-center">
                        ${getTaskIcon(task.type)}
                        ${$('<span>').text(task.title).html()}
                    </div>
                `);
                
                // Update status badge
                const statusInfo = getStatusInfo(task.status);
                $row.find('td:eq(2)').html(`<span class="badge badge-${statusInfo.class}">${statusInfo.text}</span>`);
                
                // Update priority badge
                const priorityInfo = getPriorityInfo(task.priority);
                $row.find('td:eq(3)').html(`<span class="badge badge-${priorityInfo.class}">${priorityInfo.text}</span>`);
                
                // Update additional fields based on task type
                if (task.type === 'component') {
                    // Update project name
                    const projectName = task.project ? task.project.name : '-';
                    $row.find('td:eq(1)').text(projectName);
                    
                    // Update dates
                    $row.find('td:eq(4)').text(task.start_date || '-');
                    $row.find('td:eq(5)').text(task.due_date || '-');
                } else if (task.type === 'user_story') {
                    // Update parent task name
                    const parentName = task.parent ? task.parent.title : '-';
                    $row.find('td:eq(1)').text(parentName);
                    
                    // Update estimated hours
                    $row.find('td:eq(4)').text(task.estimated_hours || '-');
                } else if (task.type === 'epic') {
                    // Update parent task name
                    const parentName = task.parent ? task.parent.title : '-';
                    $row.find('td:eq(1)').text(parentName);
                    
                    // Update estimated hours
                    $row.find('td:eq(4)').text(task.estimated_hours || '-');
                } else if (task.type === 'sub_task' || task.type === 'bug') {
                    // Update parent task name
                    const parentName = task.parent ? task.parent.title : '-';
                    $row.find('td:eq(1)').text(parentName);
                }
            } else {
                console.log('Task row not found for task ID:', task.id);
            }
        } catch (error) {
            console.error('Error updating task row:', error);
        }
    }
    
    // Helper function to get task icon based on type
    function getTaskIcon(type) {
        switch(type) {
            case 'component':
                return '<i class="fas fa-puzzle-piece text-primary mr-2"></i>';
            case 'epic':
                return '<i class="fas fa-bookmark text-success mr-2"></i>';
            case 'user_story':
                return '<i class="fas fa-book text-info mr-2"></i>';
            case 'sub_task':
                return '<i class="fas fa-tasks text-warning mr-2"></i>';
            case 'bug':
                return '<i class="fas fa-bug text-danger mr-2"></i>';
            default:
                return '';
        }
    }
    
    // Helper function to get status information
    function getStatusInfo(status) {
        switch(status) {
            case 'completed':
                return { class: 'success', text: 'مكتملة' };
            case 'in_progress':
                return { class: 'warning', text: 'قيد المعالجة' };
            case 'not_started':
                return { class: 'secondary', text: 'لم تبدء' };
            case 'overdue':
                return { class: 'danger', text: 'متأخرة' };
            default:
                return { class: 'info', text: 'غير محدد' };
        }
    }
    
    // Helper function to get priority information
    function getPriorityInfo(priority) {
        switch(priority) {
            case 'high':
                return { class: 'danger', text: 'عالية' };
            case 'medium':
                return { class: 'warning', text: 'متوسطة' };
            case 'low':
                return { class: 'info', text: 'منخفضة' };
            default:
                return { class: 'info', text: 'غير محددة' };
        }
    }

    // Function to apply filters and refresh table content
    function applyFilters(type, page = 1) {
        // Collect filter values based on type
        let filters = {};
        
        switch(type) {
            case 'component':
                filters.project_id = $('#component-project-filter').val();
                filters.user_id = $('#component-user-filter').val();
                filters.status = $('#component-status-filter').val();
                filters.priority = $('#component-priority-filter').val();
                filters.sprint_id = $('#component-sprint-filter').val();
                break;
            case 'epic':
                filters.component_id = $('#epic-component-filter').val();
                filters.user_id = $('#epic-user-filter').val();
                filters.status = $('#epic-status-filter').val();
                filters.priority = $('#epic-priority-filter').val();
                filters.sprint_id = $('#epic-sprint-filter').val();
                break;
            case 'user_story':
                filters.epic_id = $('#story-epic-filter').val();
                filters.user_id = $('#story-user-filter').val();
                filters.status = $('#story-status-filter').val();
                filters.priority = $('#story-priority-filter').val();
                filters.sprint_id = $('#story-sprint-filter').val();
                break;
            case 'sub_task':
                filters.user_id = $('#subtask-user-filter').val();
                filters.status = $('#subtask-status-filter').val();
                filters.priority = $('#subtask-priority-filter').val();
                filters.sprint_id = $('#subtask-sprint-filter').val();
                break;
            case 'bug':
                filters.user_id = $('#bug-user-filter').val();
                filters.status = $('#bug-status-filter').val();
                filters.priority = $('#bug-priority-filter').val();
                filters.sprint_id = $('#bug-sprint-filter').val();
                break;
        }
        
        // Add type and page to filters
        filters.type = type;
        filters.page = page;
        
        $.ajax({
            url: getLocalePrefix() + '/tasks/filter',
            method: 'GET',
            data: filters,
            success: function(response) {
                // Update the appropriate table container based on type
                let containerId;
                switch(type) {
                    case 'component':
                        containerId = 'components-table-container';
                        break;
                    case 'epic':
                        containerId = 'epics-table-container';
                        break;
                    case 'user_story':
                        containerId = 'stories-table-container';
                        break;
                    case 'sub_task':
                        containerId = 'subtasks-table-container';
                        break;
                    case 'bug':
                        containerId = 'bugs-table-container';
                        break;
                }

                $('#' + containerId).html(response.html);

                // Update pagination if needed
                // This would require additional implementation based on your pagination structure
            },
            error: function(xhr) {
                alert('حدث خطأ أثناء تحديث البيانات');
            }
        });
    }

    // Function to get locale prefix for URLs
    function getLocalePrefix() {
        // Get the current locale from the HTML dir attribute
        var dir = $('html').attr('dir');
        // Return the locale prefix based on the direction
        if (dir === 'rtl') {
            return '/ar'; // Assuming Arabic for RTL
        } else {
            return '/en'; // Assuming English for LTR
        }
    }

    // Function to load components
    function loadComponents(selectId) {
        $.ajax({
            url: getLocalePrefix() + '/tasks/components',
            method: 'GET',
            success: function(response) {
                const select = $('#' + selectId);
                select.empty();
                select.append('<option value="">اختر المكون</option>');

                response.forEach(function(task) {
                    select.append(
                        $('<option></option>')
                            .val(task.id)
                            .text(task.title)
                    );
                });
            }
        });
    }

    // Function to load epics (without component filter)
    function loadEpics(selectId) {
        $.ajax({
            url: getLocalePrefix() + '/tasks/epics',
            method: 'GET',
            success: function(response) {
                const select = $('#' + selectId);
                select.empty();
                select.append('<option value="">اختر الإبيك</option>');

                response.forEach(function(task) {
                    // Handle both old and new response structures
                    const id = task.id;
                    const title = task.title;
                    select.append(
                        $('<option></option>')
                            .val(id)
                            .text(title)
                    );
                });
            }
        });
    }

    // Function to load user stories (without epic filter)
    function loadUserStories(selectId) {
        $.ajax({
            url: getLocalePrefix() + '/tasks/user-stories',
            method: 'GET',
            success: function(response) {
                const select = $('#' + selectId);
                select.empty();
                select.append('<option value="">اختر قصة المستخدم</option>');

                response.forEach(function(task) {
                    // Handle both old and new response structures
                    const id = task.id;
                    const title = task.title;
                    select.append(
                        $('<option></option>')
                            .val(id)
                            .text(title)
                    );
                });
            }
        });
    }

    // Function to load projects
    function loadProjects(selectId) {
        // This would typically fetch projects via AJAX, but for now we'll assume
        // the projects are already populated in the select element
        // In a real implementation, you might want to fetch fresh project data
    }

    // Function to load parent tasks (original function for backward compatibility)
    function loadParentTasks(parentType, selectId) {
        // If no selectId is provided, return early (for components which don't have parent tasks)
        if (!selectId) {
            return;
        }

        $.ajax({
            url: getLocalePrefix() + '/tasks/get-parent-tasks/' + parentType,
            method: 'GET',
            success: function(response) {
                const select = $('#' + selectId);
                select.empty();
                select.append(`<option value="">اختر ${parentType === 'component' ? 'المكون' :
                                        parentType === 'epic' ? 'الإبيك' :
                                        parentType === 'user_story' ? 'قصة المستخدم' :
                                        'المهمة الأب'}</option>`);

                response.forEach(function(task) {
                    select.append(
                        $('<option></option>')
                            .val(task.id)
                            .text(task.title)
                    );
                });
            },
            error: function(xhr, status, error) {
                // Handle error silently or add user-friendly error handling
            }
        });
    }

    // Handle component selection for epics (load epics when component is selected)
    $(document).off('change', '#epicParentId').on('change', '#epicParentId', function() {
        // This is not needed as the epicParentId is for selecting components, not epics
    });

    // Handle epic selection for user stories (load user stories when epic is selected)
    $(document).off('change', '#storyParentId').on('change', '#storyParentId', function() {
        // This is not needed as the storyParentId is for selecting epics, not user stories
    });

    // Add event listener for when the modal is shown
    $('#createTaskModal').off('shown.bs.modal').on('shown.bs.modal', function () {
        // Also refresh filters when switching tabs
        $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
            // Get the target tab
            const target = $(e.target).attr('href');
            
            // Apply filters for the current tab
            if (target === '#components') {
                applyFilters('component');
            } else if (target === '#epics') {
                applyFilters('epic');
            } else if (target === '#stories') {
                applyFilters('user_story');
            } else if (target === '#subtasks') {
                applyFilters('sub_task');
            } else if (target === '#bugs') {
                applyFilters('bug');
            }
        });
        // Load components for epic and bug forms
        if ($('#epicParentId').is(':visible')) {
            loadComponents('epicParentId');
        }

        if ($('#bugParentId').is(':visible')) {
            loadComponents('bugParentId');
        }
    });

    // Handle component selection for epics - load epics for the selected component
    $(document).on('change', '#epicParentId', function() {
        const componentId = $(this).val();
        if (componentId) {
            // We would need to add a new select field for epics if we want to filter them
            // For now, we'll just load all epics when creating a user story
        }
    });

    // Handle user story selection for sub tasks
    $(document).on('change', '#subTaskParentId', function() {
        // This is just for consistency, sub tasks don't filter further
    });
    
    // Handle component selection for epics
    $(document).on('change', '#epic-component-filter', function() {
        const componentId = $(this).val();
        if (componentId) {
            // Load epics for the selected component
            $.ajax({
                url: getLocalePrefix() + '/tasks/epics/' + componentId,
                method: 'GET',
                success: function(response) {
                    $('#story-epic-filter').empty().append('<option value="">جميع الإبيكات</option>');
                    response.forEach(function(epic) {
                        $('#story-epic-filter').append(
                            $('<option></option>')
                                .val(epic.id)
                                .text(epic.title)
                        );
                    });
                }
            });
        } else {
            // Load all epics
            $.ajax({
                url: getLocalePrefix() + '/tasks/epics',
                method: 'GET',
                success: function(response) {
                    $('#story-epic-filter').empty().append('<option value="">جميع الإبيكات</option>');
                    response.forEach(function(epic) {
                        $('#story-epic-filter').append(
                            $('<option></option>')
                                .val(epic.id)
                                .text(epic.title)
                        );
                    });
                }
            });
        }
    });
    
    // Handle epic selection for user stories
    $(document).on('change', '#story-epic-filter', function() {
        const epicId = $(this).val();
        // In a more complex implementation, you might want to filter user stories by epic
    });

    // Form submission
   
   

    // Rest of your existing JavaScript code...

    // Add event listener for when the edit modal is hidden
    $('#editTaskModal').on('hidden.bs.modal', function () {
        console.log('Edit modal hidden');
        // Reset the form when the modal is closed
        $('#editTaskForm')[0].reset();
        $('.edit-task-fields').hide();
    });
    
    // Add specific click handler for edit modal close button
    $(document).on('click', '#editTaskModal .close, #editTaskModal .btn-secondary', function() {
        console.log('Edit modal close button clicked');
        $('#editTaskModal').modal('hide');
    });

    // Add event listener for when the view modal is hidden
    $('#viewTaskModal').on('hidden.bs.modal', function () {
        console.log('View modal hidden');
        // Clear the view modal content when closed
        $('#viewTaskTitle').text('');
        $('#viewTaskDescription').text('');
        $('#viewTaskType').text('');
        $('#viewTaskPriority').text('');
        $('#viewTaskStatus').text('');
        $('#viewTaskStartDate').text('');
        $('#viewTaskDueDate').text('');
        $('#viewTaskSprint').text('');
        $('#viewTaskProject').text('');
        $('#viewTaskParent').text('');
        $('#viewTaskEstimatedHours').text('');
        $('#viewTaskAssignedUsers').text('');
    });
    
    // Add specific click handler for view modal close button
    $(document).on('click', '#viewTaskModal .close, #viewTaskModal .btn-secondary', function() {
        console.log('View modal close button clicked');
        $('#viewTaskModal').modal('hide');
    });

    // Add event listener for when the edit modal is about to be hidden
    $('#editTaskModal').on('hide.bs.modal', function () {
        console.log('Edit modal about to be hidden');
    });

    // Handle component selection for epics
    $(document).on('change', '#epic-component-filter', function() {
        const componentId = $(this).val();
        if (componentId) {
            // Load epics for the selected component
            $.ajax({
                url: getLocalePrefix() + '/tasks/epics/' + componentId,
                method: 'GET',
                success: function(response) {
                    $('#story-epic-filter').empty().append('<option value="">جميع الإبيكات</option>');
                    response.forEach(function(epic) {
                        $('#story-epic-filter').append(
                            $('<option></option>')
                                .val(epic.id)
                                .text(epic.title)
                        );
                    });
                }
            });
        } else {
            // Load all epics
            $.ajax({
                url: getLocalePrefix() + '/tasks/epics',
                method: 'GET',
                success: function(response) {
                    $('#story-epic-filter').empty().append('<option value="">جميع الإبيكات</option>');
                    response.forEach(function(epic) {
                        $('#story-epic-filter').append(
                            $('<option></option>')
                                .val(epic.id)
                                .text(epic.title)
                        );
                    });
                }
            });
        }
    });
    
    // Handle epic selection for user stories
    $(document).on('change', '#story-epic-filter', function() {
        const epicId = $(this).val();
        // In a more complex implementation, you might want to filter user stories by epic
    });

    // Form submission
   
   

    // Rest of your existing JavaScript code...

    // Handle create sprint button click
    $('#createSprintBtn').on('click', function() {
        $('#createSprintForm')[0].reset();
        $('#createSprintModal').modal('show');
    });

    // Handle create sprint form submission
    $('#createSprintForm').on('submit', function(e) {
        e.preventDefault();
        
        const formData = $(this).serialize();
        
        $.ajax({
            url: getLocalePrefix() + '/sprints',
            method: 'POST',
            data: formData,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.success) {
                    // Close the modal
                    $('#createSprintModal').modal('hide');
                    
                    // Show success message
                    Swal.fire(
                        'تم!',
                        'تم إنشاء السبرنت بنجاح.',
                        'success'
                    );
                    
                    // Instead of reloading the page, dynamically add the new sprint to dropdowns
                    const newSprint = response.sprint;
                    addSprintToDropdowns(newSprint);
                } else {
                    Swal.fire(
                        'خطأ!',
                        response.message || 'حدث خطأ أثناء إنشاء السبرنت.',
                        'error'
                    );
                }
            },
            error: function(xhr) {
                if (xhr.status === 422) {
                    // Validation errors
                    const errors = xhr.responseJSON.errors;
                    let errorMessage = '';
                    for (let field in errors) {
                        errorMessage += errors[field][0] + '\n';
                    }
                    Swal.fire(
                        'خطأ في التحقق!',
                        errorMessage,
                        'error'
                    );
                    // Keep the modal open to show validation errors
                    // Don't close the modal here
                } else {
                    // Other errors
                    Swal.fire(
                        'خطأ!',
                        'حدث خطأ أثناء إنشاء السبرنت.',
                        'error'
                    );
                    // Close the modal on other errors
                    $('#createSprintModal').modal('hide');
                }
            }
        });
    });

    // Handle assign task to sprint button clicks (you'll need to add these buttons to your table rows)
    $(document).on('click', '.assign-to-sprint', function() {
        const taskId = $(this).data('task-id');
        $('#assignTaskId').val(taskId);
        $('#assignTaskToSprintModal').modal('show');
    });

    // Handle assign task to sprint form submission
    $('#assignTaskToSprintForm').on('submit', function(e) {
        e.preventDefault();
        
        const taskId = $('#assignTaskId').val();
        const sprintId = $('#assignSprintId').val();
        
        $.ajax({
            url: getLocalePrefix() + '/sprints/' + sprintId + '/tasks',
            method: 'POST',
            data: {
                _token: $('meta[name="csrf-token"]').attr('content'),
                task_id: taskId
            },
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.success) {
                    // Close the modal
                    $('#assignTaskToSprintModal').modal('hide');
                    
                    // Show success message
                    Swal.fire(
                        'تم!',
                        'تم تعيين المهمة للسبرنت بنجاح.',
                        'success'
                    );
                    
                    // Update the task row to show the sprint assignment
                    updateTaskSprintAssignment(taskId, response.task.sprint);
                } else {
                    Swal.fire(
                        'خطأ!',
                        response.message || 'حدث خطأ أثناء تعيين المهمة للسبرنت.',
                        'error'
                    );
                }
            },
            error: function(xhr) {
                if (xhr.status === 422) {
                    // Validation errors
                    Swal.fire(
                        'خطأ!',
                        'المهمة المحددة غير موجودة أو لا تنتمي إلى نفس المشروع.',
                        'error'
                    );
                } else {
                    // Other errors
                    Swal.fire(
                        'خطأ!',
                        'حدث خطأ أثناء تعيين المهمة للسبرنت.',
                        'error'
                    );
                }
            }
        });
    });

    // Function to update task row with sprint assignment
    function updateTaskSprintAssignment(taskId, sprint) {
        const $row = $(`tr[data-task-id="${taskId}"]`);
        if ($row.length > 0) {
            // Update the sprint badge in the row
            const sprintBadge = sprint ? 
                `<span class="badge badge-info sprint-badge">${$('<span>').text(sprint.name).html()}</span>` : 
                `<span class="badge badge-secondary sprint-badge">بدون سبرنت</span>`;
            
            // You'll need to adjust this based on where you want to show the sprint info in your table
            // This is just an example - you might want to add a specific column for sprint info
            $row.find('td:eq(1)').append(' ' + sprintBadge);
        }
    }

    // Function to add a new sprint option to all sprint dropdowns
    function addSprintToDropdowns(sprint) {
        const newOption = $('<option></option>').val(sprint.id).text(sprint.name);
        
        // Add to all sprint dropdowns in modals
        $('#sprintId, #editSprintId, #assignSprintId, #inheritanceSprintId').append(newOption.clone());
        
        // Add to any dynamically created sprint dropdowns
        $('select[name="sprint_id"]').not('#sprintId, #editSprintId, #assignSprintId, #inheritanceSprintId').append(newOption.clone());
    }

    // Handle assign sprint with inheritance button clicks
    $(document).on('click', '.assign-sprint-with-inheritance', function() {
        const taskId = $(this).data('task-id');
        const taskTitle = $(this).data('task-title');
        
        // Show confirmation dialog
        Swal.fire({
            title: 'تعيين السبرنت مع الإرث',
            html: `
                <p>هل تريد تعيين السبرنت لـ "${taskTitle}" وجميع المهام الفرعية الخاصة بها؟</p>
                <div class="form-group">
                    <label for="inheritanceSprintId">اختر السبرنت</label>
                    <select class="form-control" id="inheritanceSprintId" required>
                        <option value="">اختر السبرنت</option>
                        @foreach($sprints as $sprint)
                            <option value="{{ $sprint->id }}">{{ $sprint->name }}</option>
                        @endforeach
                    </select>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'تعيين',
            cancelButtonText: 'إلغاء',
            preConfirm: () => {
                const sprintId = $('#inheritanceSprintId').val();
                if (!sprintId) {
                    Swal.showValidationMessage('الرجاء اختيار سبرنت');
                    return false;
                }
                return { sprintId: sprintId };
            }
        }).then((result) => {
            if (result.isConfirmed) {
                // Show loading indicator
                Swal.fire({
                    title: 'جاري التعيين...',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showConfirmButton: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                // Make AJAX request to assign sprint with inheritance
                $.ajax({
                    url: getLocalePrefix() + '/tasks/' + taskId + '/assign-sprint-with-inheritance',
                    method: 'POST',
                    data: {
                        _token: $('meta[name="csrf-token"]').attr('content'),
                        sprint_id: result.value.sprintId
                    },
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'تم التعيين!',
                                text: 'تم تعيين السبرنت للمهمة وجميع المهام الفرعية الخاصة بها.',
                                confirmButtonText: 'موافق'
                            });
                            
                            // Update UI without refreshing the page
                            // You can add specific UI updates here if needed
                            // For now, we'll just show a success message
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'خطأ!',
                                text: response.message || 'حدث خطأ أثناء تعيين السبرنت.',
                                confirmButtonText: 'موافق'
                            });
                        }
                    },
                    error: function(xhr) {
                        Swal.fire({
                            icon: 'error',
                            title: 'خطأ!',
                            text: 'حدث خطأ أثناء تعيين السبرنت.',
                            confirmButtonText: 'موافق'
                        });
                    }
                });
            }
        });
    });

    // Ensure all modals close properly
    $(document).on('click', '[data-bs-dismiss="modal"]', function() {
        var modalId = $(this).closest('.modal').attr('id');
        $('#' + modalId).modal('hide');
    });

});
</script>
@endpush

<!-- Sprint Creation Modal -->
<div class="modal fade" id="createSprintModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createSprintModalTitle">إنشاء سبرنت جديد</h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="createSprintForm">
                @csrf
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="sprintProjectId">المشروع <span class="text-danger">*</span></label>
                                <select class="form-control" id="sprintProjectId" name="project_id" required>
                                    <option value="">اختر المشروع</option>
                                    @foreach($projects as $project)
                                        <option value="{{ $project->id }}">{{ $project->title }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="sprintName">اسم السبرنت <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="sprintName" name="name" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label for="sprintGoal">الهدف</label>
                                <textarea class="form-control" id="sprintGoal" name="goal" rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="sprintStartDate">تاريخ البداية <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="sprintStartDate" name="start_date" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="sprintEndDate">تاريخ النهاية <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="sprintEndDate" name="end_date" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="sprintStatus">الحالة</label>
                                <select class="form-control" id="sprintStatus" name="status">
                                    <option value="planned">مخطط</option>
                                    <option value="active">نشط</option>
                                    <option value="completed">مكتمل</option>
                                    <option value="canceled">ملغى</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary">حفظ</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Assign Task to Sprint Modal -->
<div class="modal fade" id="assignTaskToSprintModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">تعيين المهمة للسبرنت</h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="assignTaskToSprintForm">
                @csrf
                <div class="modal-body">
                    <input type="hidden" id="assignTaskId" name="task_id">
                    <div class="form-group">
                        <label for="assignSprintId">اختر السبرنت</label>
                        <select class="form-control" id="assignSprintId" name="sprint_id" required>
                            <option value="">اختر السبرنت</option>
                            @foreach($sprints as $sprint)
                                <option value="{{ $sprint->id }}">{{ $sprint->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <!-- Task title for inheritance assignment -->
                    <div class="form-group" id="sprintAssignmentTaskTitleContainer" style="display: none;">
                        <label>المهمة:</label>
                        <p id="sprintAssignmentTaskTitle"></p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary">تعيين</button>
                </div>
            </form>
        </div>
    </div>
</div>