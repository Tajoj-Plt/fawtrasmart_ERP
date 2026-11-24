{{-- أزرار بطاقة/صفّ المهمة. زرّ التعديل يحمل حقول المهمة في data-* ليملأ نافذة التعديل بلا طلبٍ إضافي. الوسائط: $task، $quick (bool). --}}
@permission('realestate.tasks.edit')
    @if (! empty($quick) && $task->status !== 'completed')
        <button type="button" class="btn btn-sm btn-outline-success re-action" data-url="{{ route('realestate.tasks.status', $task->id) }}" data-method="POST"
                data-payload='{"status":"completed"}' title="إكمال"><i class="feather icon-check"></i> إكمال</button>
    @endif
    <button type="button" class="btn btn-sm btn-link p-0 px-25 re-task-edit" title="تعديل" aria-label="تعديل {{ $task->title }}"
            data-id="{{ $task->id }}" data-title="{{ $task->title }}" data-activity_type="{{ $task->activity_type }}" data-priority="{{ $task->priority }}"
            data-related_type="{{ $task->related_type }}" data-related_id="{{ $task->related_id }}" data-related_label="{{ $task->related_label }}"
            data-client_id="{{ $task->client_id }}" data-client_name="{{ $task->client?->display_name }}"
            data-assigned_user_id="{{ $task->assigned_user_id }}" data-due_at="{{ $task->due_at?->format('Y-m-d\TH:i') }}"
            data-next_follow_up_at="{{ $task->next_follow_up_at?->format('Y-m-d\TH:i') }}" data-notes="{{ $task->notes }}"
            data-update-url="{{ route('realestate.tasks.update', $task->id) }}"><i class="feather icon-edit"></i></button>
@endpermission
@permission('realestate.tasks.delete')
    <button type="button" class="btn btn-sm btn-link text-danger p-0 px-25 re-action" title="حذف" aria-label="حذف {{ $task->title }}"
            data-url="{{ route('realestate.tasks.destroy', $task->id) }}" data-method="DELETE" data-tone="danger" data-confirm="حذف المهمة «{{ $task->title }}»؟"><i class="feather icon-trash-2"></i></button>
@endpermission
