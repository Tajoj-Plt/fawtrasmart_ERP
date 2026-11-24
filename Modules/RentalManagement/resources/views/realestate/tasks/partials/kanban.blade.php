@php
    use Modules\RentalManagement\Support\RealEstateVocabulary as V;
    $canMove = hasPermission('realestate.tasks.edit');
    $todayDate = now()->toDateString();
    // «اليوم» عمودٌ مشتقّ: الجديدة التي حلّ موعدها اليوم تُعرض فيه دون أن تُكتب حالتها
    $columns = $tasks->groupBy(fn ($task) => $task->status === 'new' && $task->due_at?->toDateString() === $todayDate ? 'today' : $task->status);
@endphp
@if ($tasks->isEmpty())
    @include('rentalmanagement::realestate.components.empty', ['icon' => 'check-square', 'title' => 'لا مهام', 'hint' => 'أضف أول مهمة متابعة، أو عدّل معايير البحث'])
@else
    <div class="p-1">
        <div class="re-kanban">
            @foreach (V::TASK_STATUSES as $status => $meta)
                @php $cards = $columns->get($status, collect()); @endphp
                <div class="re-kanban__col" data-status="{{ $status }}">
                    <div class="re-kanban__head">
                        <span class="text-{{ $meta['tone'] }}">{{ $meta['label'] }}</span>
                        <span class="badge badge-light-{{ $meta['tone'] }} stk-num">{{ $cards->count() }}</span>
                    </div>
                    @foreach ($cards as $task)
                        <div class="re-task {{ $task->isOverdue() ? 'is-late' : '' }}" data-id="{{ $task->id }}" data-column="{{ $status }}"
                             data-status-url="{{ route('realestate.tasks.status', $task->id) }}"
                             @if ($canMove) draggable="true" @else style="cursor:default" @endif>
                            <div class="d-flex justify-content-between align-items-start" style="gap:.4rem">
                                <div class="re-task__title">{{ $task->title }}</div>
                                <span class="text-nowrap">@include('rentalmanagement::realestate.tasks.partials._actions', ['task' => $task])</span>
                            </div>
                            <div class="re-task__meta">
                                <span><i class="feather icon-{{ V::ACTIVITY_TYPES[$task->activity_type]['icon'] ?? 'check-square' }}"></i> {{ $task->type_label }}</span>
                                <span class="badge badge-light-{{ $task->priority_meta['tone'] }}">{{ $task->priority_meta['label'] }}</span>
                                @if ($task->client) <span><i class="feather icon-user"></i> {{ $task->client->display_name }}</span> @endif
                                @if ($task->related_label) <span><i class="feather icon-link"></i> {{ $task->related_label }}</span> @endif
                                @if ($task->due_at) <span class="stk-num {{ $task->isOverdue() ? 'text-danger' : '' }}"><i class="feather icon-clock"></i> {{ $task->due_at->format('Y-m-d H:i') }}</span> @endif
                                @if ($task->assignee) <span><i class="feather icon-user-check"></i> {{ $task->assignee->name }}</span> @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
    </div>
@endif
