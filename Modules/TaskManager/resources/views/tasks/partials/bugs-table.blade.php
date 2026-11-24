<table class="table table-bordered table-striped task-table">
    <thead>
        <tr>
            <th>العنوان</th>
            <th>المكون</th>
            <th>الحالة</th>
            <th>الساعات المقدرة</th>
            <th>الأولوية</th>
            <th>الإجراءات</th>
        </tr>
    </thead>
    <tbody>
        @foreach($bugs as $task)
        <tr data-task-id="{{ $task->id }}">
            <td>
                <div class="d-flex align-items-center">
                    <i class="fas fa-bug text-danger mr-2"></i>
                    {{ $task->title }}
                </div>
            </td>
            <td>{{ optional($task->parent)->title ?? '-' }}</td>

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

            {{-- الساعات المقدرة --}}
            <td>{{ $task->estimated_hours ?? '-' }}</td>

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

                            <a class="dropdown-item assign-to-sprint" href="javascript:void(0);" 
                               data-task-id="{{ $task->id }}">
                                <i class="fa fa-calendar me-2 text-info"></i> تعيين للسبرنت
                            </a>

                            <a class="dropdown-item assign-sprint-with-inheritance" href="javascript:void(0);" 
                               data-task-id="{{ $task->id }}" data-task-title="{{ $task->title }}">
                                <i class="fa fa-sitemap me-2 text-primary"></i> تعيين السبرنت مع الإرث
                            </a>
                        </div>
                    </div>
                </div>
            </td>
        </tr>
        @endforeach
    </tbody>
</table>
