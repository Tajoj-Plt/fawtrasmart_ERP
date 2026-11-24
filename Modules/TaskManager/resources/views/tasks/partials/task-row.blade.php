<tr data-task-id="{{ $task->id }}">
    <td>
        <div class="d-flex align-items-center">
            @if($task->type === 'component')
                <i class="fas fa-puzzle-piece text-primary mr-2"></i>
            @elseif($task->type === 'epic')
                <i class="fas fa-bookmark text-success mr-2"></i>
            @elseif($task->type === 'user_story')
                <i class="fas fa-book text-info mr-2"></i>
            @elseif($task->type === 'sub_task')
                <i class="fas fa-tasks text-warning mr-2"></i>
            @elseif($task->type === 'bug')
                <i class="fas fa-bug text-danger mr-2"></i>
            @endif
            {{ $task->title }}
        </div>
    </td>
    <td>
        @if($task->parent)
            {{ $task->parent->title }}
        @elseif($task->project)
            {{ $task->project->title }}
        @else
            -
        @endif
    </td>
    <td>
        @php
            $statusClass = 'info';
            $statusText = 'غير محدد';

            if ($task->status === 'completed') {
                $statusClass = 'success';
                $statusText = 'مكتملة';
            } elseif ($task->status === 'in_progress') {
                $statusClass = 'warning';
                $statusText = 'قيد المعالجة';
            } elseif ($task->status === 'not_started') {
                $statusClass = 'secondary';
                $statusText = 'لم تبدء';
            } elseif ($task->status === 'overdue') {
                $statusClass = 'primary';
                $statusText = '';
            }
        @endphp

        <span class="badge badge-{{ $statusClass }}">{{ $statusText }}</span>
    </td>
    <td>
        @php
            $priorityClass = 'info';
            $priorityText = 'غير محددة';

            if ($task->priority === 'high') {
                $priorityClass = 'danger';
                $priorityText = 'عالية';
            } elseif ($task->priority === 'medium') {
                $priorityClass = 'warning';
                $priorityText = 'متوسطة';
            } elseif ($task->priority === 'low') {
                $priorityClass = 'info';
                $priorityText = 'منخفضة';
            }
        @endphp

        <span class="badge badge-{{ $priorityClass }}">{{ $priorityText }}</span>
    </td>
    @if($task->type === 'user_story' || $task->type === 'epic')
    <td>{{ $task->estimated_hours ?? '-' }}</td>
    @endif
    <td>
        <div class="btn-group">
            <div class="dropdown">
                <button class="btn bg-gradient-info fa fa-ellipsis-v btn-sm"
                        type="button"
                        data-bs-toggle="dropdown"
                        aria-expanded="false">
                </button>
                <div class="dropdown-menu dropdown-menu-end">
                    <a class="dropdown-item view-task" href="javascript:void(0);"
                       data-task-id="{{ $task->id }}">
                        <i class="fa fa-eye me-2 text-primary"></i> عرض التفاصيل
                    </a>

                    <a class="dropdown-item edit-task" href="javascript:void(0);"
                       data-task-id="{{ $task->id }}">
                        <i class="fa fa-edit me-2 text-success"></i> تعديل
                    </a>

                    <a class="dropdown-item delete-task" href="javascript:void(0);"
                       data-task-id="{{ $task->id }}">
                        <i class="fa fa-trash me-2 text-danger"></i> حذف
                    </a>

                    @if($task->type === 'component')
                        <a class="dropdown-item" href="javascript:void(0);"
                           onclick="addTask({{ $task->id }}, 'epic')">
                            <i class="fa fa-plus me-2 text-info"></i> إضافة إبيك
                        </a>

                        <a class="dropdown-item" href="javascript:void(0);"
                           onclick="addTask({{ $task->id }}, 'bug')">
                            <i class="fa fa-bug me-2 text-warning"></i> إضافة خطأ
                        </a>
                    @elseif($task->type === 'epic')
                        <a class="dropdown-item" href="javascript:void(0);"
                           onclick="addTask({{ $task->id }}, 'user_story')">
                            <i class="fa fa-plus me-2 text-info"></i> إضافة قصة مستخدم
                        </a>

                        <a class="dropdown-item assign-to-sprint" href="javascript:void(0);"
                           data-task-id="{{ $task->id }}">
                            <i class="fa fa-calendar me-2 text-info"></i> تعيين للسبرنت
                        </a>

                        <a class="dropdown-item assign-sprint-with-inheritance" href="javascript:void(0);"
                           data-task-id="{{ $task->id }}" data-task-title="{{ $task->title }}">
                            <i class="fa fa-sitemap me-2 text-primary"></i> تعيين السبرنت مع الإرث
                        </a>
                    @elseif($task->type === 'user_story')
                        <a class="dropdown-item" href="javascript:void(0);"
                           onclick="addTask({{ $task->id }}, 'sub_task')">
                            <i class="fa fa-plus me-2 text-info"></i> إضافة مهمة فرعية
                        </a>

                        <a class="dropdown-item assign-to-sprint" href="javascript:void(0);"
                           data-task-id="{{ $task->id }}">
                            <i class="fa fa-calendar me-2 text-info"></i> تعيين للسبرنت
                        </a>

                        <a class="dropdown-item assign-sprint-with-inheritance" href="javascript:void(0);"
                           data-task-id="{{ $task->id }}" data-task-title="{{ $task->title }}">
                            <i class="fa fa-sitemap me-2 text-primary"></i> تعيين السبرنت مع الإرث
                        </a>
                    @elseif($task->type === 'sub_task' || $task->type === 'bug')
                        <a class="dropdown-item assign-to-sprint" href="javascript:void(0);"
                           data-task-id="{{ $task->id }}">
                            <i class="fa fa-calendar me-2 text-info"></i> تعيين للسبرنت
                        </a>

                        <a class="dropdown-item assign-sprint-with-inheritance" href="javascript:void(0);"
                           data-task-id="{{ $task->id }}" data-task-title="{{ $task->title }}">
                            <i class="fa fa-sitemap me-2 text-primary"></i> تعيين السبرنت مع الإرث
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </td>
</tr>