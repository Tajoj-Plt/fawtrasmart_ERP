@php
    $priorityClass = 'priority-' . $task->priority;
    $typeClass = 'type-' . $task->type;
    $typeName = [
        'component' => 'مكون',
        'epic' => 'إبيك',
        'user_story' => 'قصة مستخدم',
        'sub_task' => 'مهمة فرعية',
        'bug' => 'خطأ برمجي',
        'task' => 'مهمة',
    ][$task->type] ?? $task->type;
    $priorityName = [
        'low' => 'منخفضة',
        'medium' => 'متوسطة',
        'high' => 'عالية',
        'urgent' => 'عاجلة',
    ][$task->priority] ?? $task->priority;
    $progressPercent = $task->completion_percentage ?? 0;

    if ($progressPercent >= 70) {
        $progressColor = '#28c76f';
    } elseif ($progressPercent >= 30) {
        $progressColor = '#ff9f43';
    } else {
        $progressColor = '#ea5455';
    }

    $radius = 25;
    $circumference = 2 * 3.14159 * $radius;
    $offset = $circumference - ($progressPercent / 100) * $circumference;

    $isOverdue = $task->status !== 'completed' && $task->due_date && \Carbon\Carbon::parse($task->due_date)->isPast();
    $assignees = $task->assignedUsers ?? collect();
    $visibleAssignees = $assignees->take(3);
    $extraAssigneesCount = max(0, $assignees->count() - $visibleAssignees->count());
@endphp
<div class="card task-card {{ $priorityClass }} {{ $typeClass }}" data-task-id="{{ $task->id }}" draggable="true">
    <div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-2">
            <span class="task-type-badge">{{ $typeName }}</span>
            <div class="task-actions">
                <button class="btn-info" onclick="showTaskDetails({{ $task->id }})" title="عرض التفاصيل">
                    <i class="feather icon-eye"></i>
                </button>
                <button class="btn-primary" onclick="editTask({{ $task->id }})" title="تعديل">
                    <i class="feather icon-edit"></i>
                </button>
                <button class="btn-danger" onclick="deleteTask({{ $task->id }})" title="حذف">
                    <i class="feather icon-trash-2"></i>
                </button>
            </div>
        </div>

        <h6 class="card-title mb-2">{{ $task->title }}</h6>

        <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-2">
            @if($task->due_date)
                <div class="task-due-date {{ $isOverdue ? 'text-danger fw-bold' : '' }}">
                    <i class="feather icon-calendar"></i>
                    {{ \Carbon\Carbon::parse($task->due_date)->format('d M') }}
                    @if($isOverdue)
                        <span class="badge badge-sm bg-danger text-white">متأخرة</span>
                    @endif
                </div>
            @endif

            <span class="badge badge-sm" style="background-color: {{ ['low' => '#28c76f','medium' => '#ff9f43','high' => '#ea5455','urgent' => '#e83e8c'][$task->priority] ?? '#6c757d' }}; color: #fff;">
                {{ $priorityName }}
            </span>
        </div>

        <div class="d-flex align-items-center justify-content-between">
            <div class="task-assignees">
                @forelse($visibleAssignees as $assignee)
                    <img src="{{ $assignee->avatar_url }}" alt="{{ $assignee->name }}" class="user-avatar" title="{{ $assignee->name }}">
                @empty
                    <span class="text-muted small">لا يوجد معينون</span>
                @endforelse
                @if($extraAssigneesCount > 0)
                    <div class="extra-users-count" title="{{ $extraAssigneesCount }} أعضاء إضافيون">+{{ $extraAssigneesCount }}</div>
                @endif
            </div>

            <div class="circular-progress" title="نسبة الإنجاز {{ $progressPercent }}%">
                <svg width="44" height="44" viewBox="0 0 60 60">
                    <circle cx="30" cy="30" r="{{ $radius }}" stroke="#e9ecef" stroke-width="6" fill="none"/>
                    <circle class="progress-circle" cx="30" cy="30" r="{{ $radius }}" stroke="{{ $progressColor }}"
                        stroke-width="6" fill="none" stroke-linecap="round"
                        stroke-dasharray="{{ $circumference }}" stroke-dashoffset="{{ $offset }}"
                        transform="rotate(-90 30 30)"/>
                </svg>
                <div class="progress-value"><small>{{ $progressPercent }}%</small></div>
            </div>
        </div>

        <div class="mt-2">
            <select class="form-control task-status-select" data-task-id="{{ $task->id }}">
                @foreach(($statuses ?? []) as $statusKey => $statusName)
                    <option value="{{ $statusKey }}" {{ $task->status === $statusKey ? 'selected' : '' }}>{{ $statusName }}</option>
                @endforeach
            </select>
        </div>
    </div>
</div>
