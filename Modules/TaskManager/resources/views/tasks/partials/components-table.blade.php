<table class="table table-bordered table-striped task-table">
    <thead>
        <tr>
            <th>العنوان</th>
            <th>المشروع</th>
            <th>الحالة</th>
            <th>الأولوية</th>

            <th>الإجراءات</th>
        </tr>
    </thead>
    <tbody>
        @foreach($components as $task)
        <tr data-task-id="{{ $task->id }}">
            <td>
                <div class="d-flex align-items-center">
                    <i class="fas fa-puzzle-piece text-primary mr-2"></i>
                    {{ $task->title }}
                </div>
            </td>

            <td>{{ $task->project->title ?? 'غير محدد' }}</td>

            {{-- الحالة --}}
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

            {{-- الأولوية --}}
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


            {{-- الإجراءات --}}
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

                            <a class="dropdown-item" href="javascript:void(0);"
                               onclick="addTask({{ $task->id }}, 'epic')">
                                <i class="fa fa-plus me-2 text-info"></i> إضافة إبيك
                            </a>

                            <a class="dropdown-item" href="javascript:void(0);"
                               onclick="addTask({{ $task->id }}, 'bug')">
                                <i class="fa fa-bug me-2 text-warning"></i> إضافة خطأ
                            </a>
                        </div>
                    </div>
                </div>
            </td>
        </tr>
        @endforeach
    </tbody>
</table>