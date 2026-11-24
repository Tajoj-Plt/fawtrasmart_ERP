{{--
    أعمدة لوحة كانبان القابلة لإعادة الاستخدام — تستقبل $statuses (map
    key=>label) و$tasksByStatus (Collection مجمّعة حسب status). تُستخدم من
    لوحة المهام الرئيسية ولوحة السبرنت، وأيضاً كاستجابة AJAX عند تطبيق
    الفلاتر (TaskController::getTaskManagerData) — لذا لا تحمل غلافها
    الخارجي (kanban-board/task-board) بنفسها؛ الصفحة المستضيفة توفره
    لتبقى صالحة للحقن داخل نفس الحاوية عبر .html(html).
    اختياري: $showAddButton (افتراضي true) لإخفاء زر الإضافة السريعة إن لزم.
--}}
@php
    $kanbanStatusMeta = [
        'not_started' => ['icon' => 'icon-circle', 'accent' => 'secondary'],
        'in_progress' => ['icon' => 'icon-loader', 'accent' => 'primary'],
        'completed' => ['icon' => 'icon-check-circle', 'accent' => 'success'],
        'overdue' => ['icon' => 'icon-alert-triangle', 'accent' => 'danger'],
    ];
@endphp
@foreach ($statuses as $statusKey => $statusName)
    @php $meta = $kanbanStatusMeta[$statusKey] ?? ['icon' => 'icon-circle', 'accent' => 'secondary']; @endphp
    <div class="kanban-column-wrapper">
        <div class="card kanban-column-card kanban-accent-{{ $meta['accent'] }}">
            <div class="card-header">
                <h4 class="card-title">
                    <i class="feather {{ $meta['icon'] }}"></i>
                    {{ $statusName }}
                </h4>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge badge-pill task-count task-count-{{ $statusKey }}">
                        {{ isset($tasksByStatus[$statusKey]) ? $tasksByStatus[$statusKey]->count() : 0 }}
                    </span>
                    @if ($showAddButton ?? true)
                        <button type="button" class="btn-add-task" title="إضافة مهمة" data-bs-toggle="modal" data-bs-target="#createTaskModal">
                            <i class="feather icon-plus"></i>
                        </button>
                    @endif
                </div>
            </div>
            <div class="card-body task-column" data-status="{{ $statusKey }}">
                @if (isset($tasksByStatus[$statusKey]) && $tasksByStatus[$statusKey]->count() > 0)
                    @foreach ($tasksByStatus[$statusKey] as $task)
                        @include('taskmanager::tasks.modals.task_card', ['task' => $task])
                    @endforeach
                @else
                    <div class="empty-column-hint">لا توجد مهام هنا</div>
                @endif
            </div>
        </div>
    </div>
@endforeach
