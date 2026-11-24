@php use Modules\RentalManagement\Support\RealEstateVocabulary as V; @endphp
@if ($tasks->count() > 0)
    <div class="d-flex justify-content-end p-2 pb-0">@include('stock::components.per-page', ['onchange' => 'loadData(1)'])</div>

    <div class="table-responsive">
        <table class="table table-hover mb-0" id="reportTable">
            <thead>
                <tr><th>المهمة</th><th>النوع</th><th>مرتبطة بـ</th><th>العميل</th><th>المسؤول</th><th class="text-center">الأولوية</th><th>الموعد</th><th class="text-center">الحالة</th><th></th></tr>
            </thead>
            <tbody>
                @foreach ($tasks as $task)
                    <tr>
                        <td>
                            <strong>{{ $task->title }}</strong>
                            @if ($task->next_follow_up_at) <small class="text-muted d-block stk-num">المتابعة التالية: {{ $task->next_follow_up_at->format('Y-m-d H:i') }}</small> @endif
                        </td>
                        <td class="text-nowrap"><i class="feather icon-{{ V::ACTIVITY_TYPES[$task->activity_type]['icon'] ?? 'check-square' }} text-muted"></i> {{ $task->type_label }}</td>
                        <td>{{ $task->related_label ?? '—' }}</td>
                        <td>{{ $task->client->display_name ?? '—' }}</td>
                        <td>{{ $task->assignee->name ?? '—' }}</td>
                        <td class="text-center"><span class="badge badge-light-{{ $task->priority_meta['tone'] }}">{{ $task->priority_meta['label'] }}</span></td>
                        <td class="stk-num {{ $task->isOverdue() ? 'text-danger' : '' }}">{{ $task->due_at?->format('Y-m-d H:i') ?? '—' }}</td>
                        <td class="text-center"><span class="badge badge-light-{{ $task->status_meta['tone'] }}">{{ $task->status_meta['label'] }}</span></td>
                        <td class="text-end text-nowrap">@include('rentalmanagement::realestate.tasks.partials._actions', ['task' => $task, 'quick' => true])</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @include('rentalmanagement::realestate.components.pagination', ['paginator' => $tasks, 'unit' => 'مهمة'])
@else
    @include('rentalmanagement::realestate.components.empty', ['icon' => 'check-square', 'title' => 'لا مهام', 'hint' => 'أضف أول مهمة متابعة، أو عدّل معايير البحث'])
@endif
