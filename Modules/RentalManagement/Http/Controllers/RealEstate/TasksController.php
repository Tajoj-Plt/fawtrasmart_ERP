<?php

namespace Modules\RentalManagement\Http\Controllers\RealEstate;

use App\Models\Client;
use App\Models\notifications;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Modules\RentalManagement\Models\CustomerRelationship;
use Modules\RentalManagement\Models\Task;
use Modules\RentalManagement\Services\AttentionService;
use Modules\RentalManagement\Support\RealEstateVocabulary as V;

class TasksController extends RealEstateController
{
    public const LOG = 're_task_log';

    public function __construct(protected AttentionService $attention)
    {
        $this->middleware('permission.role:realestate.tasks.view', ['only' => ['index']]);
        $this->middleware('permission.role:realestate.tasks.create', ['only' => ['store']]);
        $this->middleware('permission.role:realestate.tasks.edit', ['only' => ['update', 'status']]);
        $this->middleware('permission.role:realestate.tasks.delete', ['only' => ['destroy']]);
        $this->middleware('permission.role:realestate.tasks.create|realestate.tasks.edit', ['only' => ['relatedSearch']]);
    }

    /**
     * النطاق من `scoped()`: المستخدم يرى ما أنشأه أو أُسند إليه، ومهامّ من
     * يديرهم، ومحور الفرع يرى مهامّ فرعه.
     */
    public function index(Request $request)
    {
        $query = $this->scoped(Task::query())->with(['client', 'assignee'])->orderByRaw('due_at IS NULL')->orderBy('due_at');

        $query->when($request->filled('keywords'), fn ($q) => $q->where('title', 'like', '%'.$request->keywords.'%'));
        $query->when($request->boolean('mine'), fn ($q) => $q->where('assigned_user_id', auth()->id()));

        foreach (['priority', 'activity_type', 'assigned_user_id', 'related_type', 'client_id'] as $filter) {
            $query->when($request->filled($filter), fn ($q) => $q->where($filter, $request->input($filter)));
        }

        /*
            «اليوم» عمودٌ مشتقّ لا حالةٌ مخزَّنة: مهمةٌ جديدة موعدها اليوم. فالتصفية
            بها تُترجَم إلى شرطها — وكانت تبحث عن `status = today` فلا تجد شيئاً.
            و«جديدة» تستثني ما يُعرض في عمود اليوم كي يطابق الفلترُ اللوحة.
        */
        $today = now()->toDateString();

        match ($request->input('status')) {
            null, '' => null,
            'today' => $query->where('status', 'new')->whereDate('due_at', $today),
            'new' => $query->where('status', 'new')->where(fn ($q) => $q->whereNull('due_at')->orWhereDate('due_at', '!=', $today)),
            default => $query->where('status', $request->input('status')),
        };

        $mode = $request->input('mode', 'kanban') === 'list' ? 'list' : 'kanban';

        // «اليوم» عمودٌ مشتقّ: مهمةٌ جديدة حلّ موعدها اليوم تُعرض فيه دون أن تُكتب حالتها
        $tasks = $mode === 'kanban' ? $query->limit(300)->get() : $query->paginate($this->perPage($request));

        if ($request->ajax()) {
            return view('rentalmanagement::realestate.tasks.partials.'.$mode, compact('tasks'))->render();
        }

        return view('rentalmanagement::realestate.tasks.index', [
            'tasks' => $tasks,
            'mode' => $mode,
            'users' => $this->users(),
            'today' => $this->scoped(Task::query())->where('status', '!=', 'completed')
                ->whereDate('due_at', $today)->with('client')->orderBy('due_at')->get(),
            'attention' => $this->attention->items(fn ($q, $t = null) => $this->scoped($q, $t)),
        ]);
    }

    /**
     * بحث «مرتبطة بـ»: سجلّات النوع المختار داخل النطاق — بدل كتابة المعرّف
     * الداخلي باليد.
     */
    public function relatedSearch(Request $request)
    {
        $type = (string) $request->input('type');
        $term = trim((string) $request->input('q', ''));
        $query = $this->relatedQuery($type);

        if (! $query) {
            return response()->json(['results' => [], 'pagination' => ['more' => false]]);
        }

        if ($term !== '') {
            $like = '%'.$term.'%';
            $columns = self::RELATED_SEARCH[$type];
            $query->where(fn ($w) => collect($columns)->each(fn ($column) => $w->orWhere($column, 'like', $like)));
        }

        $page = $query->latest('id')->simplePaginate(20);

        return response()->json([
            'results' => collect($page->items())->map(fn ($record) => ['id' => $record->id, 'text' => $this->relatedText($type, $record)])->values(),
            'pagination' => ['more' => $page->hasMorePages()],
        ]);
    }

    public function store(Request $request)
    {
        $task = Task::create($this->validated($request) + ['status' => 'new']);

        OperationLog::record('created', self::LOG, $task, sprintf('أُنشئت المهمة **%s**', $task->title));

        CustomerRelationship::touchActivity($task->client_id);
        $this->notifyAssignee($task);

        return $this->ok($request, 'تمت إضافة المهمة');
    }

    public function update(Request $request, $id)
    {
        $task = $this->findScoped(Task::class, $id);
        $previousAssignee = (int) $task->assigned_user_id;

        $task->update($this->validated($request, $task));

        OperationLog::record('updated', self::LOG, $task, sprintf('عُدّلت المهمة **%s**', $task->title));

        CustomerRelationship::touchActivity($task->client_id);

        // إعادة الإسناد تُبلَّغ كالإسناد الأول
        if ((int) $task->assigned_user_id !== $previousAssignee) {
            $this->notifyAssignee($task, true);
        }

        return $this->ok($request, 'تم تحديث المهمة');
    }

    /** نقل البطاقة بين أعمدة اللوحة. */
    public function status(Request $request, $id)
    {
        $task = $this->findScoped(Task::class, $id);

        $data = $request->validate([
            'status' => 'required|'.V::in(V::TASK_STATUSES),
            'next_follow_up_at' => 'nullable|date',
            'notes' => 'nullable|string|max:3000',
        ], [], ['status' => 'الحالة', 'next_follow_up_at' => 'المتابعة التالية', 'notes' => 'الملاحظات']);

        /*
            «اليوم» عمودٌ مشتقّ من الموعد لا حالةٌ تُخزَّن: لو كُتبت لبقيت
            المهمة في عمود اليوم غداً وبعده. فإسقاطُ بطاقةٍ فيه يعني «اجعل
            موعدها اليوم».
        */
        if ($data['status'] === 'today') {
            $data['status'] = 'new';
            $task->due_at = $task->due_at && $task->due_at->isToday() ? $task->due_at : now()->setTime(17, 0);
        }

        $task->fill($data);
        $task->completed_at = $data['status'] === 'completed' ? now() : null;
        $task->save();

        CustomerRelationship::touchActivity($task->client_id);

        OperationLog::record('status_changed', self::LOG, $task, sprintf(
            'المهمة **%s** صارت «%s»', $task->title, V::label(V::TASK_STATUSES, $task->status)));

        return $this->ok($request, 'تم تحديث حالة المهمة');
    }

    public function destroy(Request $request, $id)
    {
        $task = $this->findScoped(Task::class, $id);
        $task->delete();

        OperationLog::record('deleted', self::LOG, $task->id, sprintf('حُذفت المهمة **%s**', $task->title));

        return $this->ok($request, 'تم حذف المهمة');
    }

    /** أعمدة البحث لكلّ نوعٍ مرتبط. */
    private const RELATED_SEARCH = [
        'client' => ['trade_name', 'first_name', 'last_name', 'code', 'mobile', 'phone'],
        'building' => ['name_ar', 'name_en', 'code'],
        'unit' => ['unit_no'],
        'listing' => ['title', 'code'],
        'contract' => ['code'],
        'payment' => ['code', 'reference_no'],
        'maintenance' => ['title', 'code'],
    ];

    /** استعلام النوع المرتبط داخل النطاق — أو `null` لنوعٍ غير معروف. */
    private function relatedQuery(string $type)
    {
        $class = Task::RELATED_MODELS[$type] ?? null;

        if (! $class) {
            return null;
        }

        // العميل من الملفّ المركزي له نطاقه، والبقية سجلّاتٌ عقارية بنطاقها
        return $class === Client::class ? $this->scopedClients() : $this->scoped($class::query());
    }

    private function relatedText(string $type, $record): string
    {
        $name = match ($type) {
            'client' => ($record->display_name ?? $record->trade_name).($record->code ? ' — '.$record->code : ''),
            'building' => trim(($record->code ? $record->code.' — ' : '').$record->name),
            'unit' => $record->full_label ?? ($record->code.' — '.$record->unit_no),
            'listing', 'maintenance' => trim($record->code.' — '.$record->title),
            default => (string) ($record->code ?? '#'.$record->id),
        };

        return $name !== '' ? $name : '#'.$record->id;
    }

    private function validated(Request $request, ?Task $current = null): array
    {
        $data = $request->validate([
            'title' => 'required|string|max:191',
            'activity_type' => 'required|'.V::in(V::ACTIVITY_TYPES),
            'related_type' => 'nullable|'.V::in(V::TASK_RELATED),
            'related_id' => 'nullable|required_with:related_type|integer|min:1',
            'client_id' => 'nullable|exists:clients,id',
            'assigned_user_id' => 'nullable|exists:users,id',
            'priority' => 'required|'.V::in(V::PRIORITIES),
            'due_at' => 'nullable|date|after:2000-01-01|before:'.now()->addYears(5)->toDateString(),
            'next_follow_up_at' => 'nullable|date|before:'.now()->addYears(5)->toDateString(),
            'notes' => 'nullable|string|max:3000',
        ], [
            'related_id.required_with' => 'اختر السجلّ المرتبط بعد تحديد نوع الارتباط.',
        ], [
            'title' => 'عنوان المهمة', 'activity_type' => 'نوع النشاط', 'priority' => 'الأولوية', 'due_at' => 'الموعد',
            'related_type' => 'نوع الارتباط', 'related_id' => 'السجلّ المرتبط', 'client_id' => 'العميل',
            'assigned_user_id' => 'المسؤول', 'next_follow_up_at' => 'المتابعة التالية', 'notes' => 'الملاحظات',
        ]);

        // بلا نوع ارتباطٍ لا معنى لمعرّفه
        if (empty($data['related_type'])) {
            $data['related_type'] = $data['related_id'] = null;
        }

        $errors = [];

        // السجلّ المرتبط موجودٌ في جدول نوعه وداخل النطاق — لا رقمٌ يُكتب كيفما اتّفق
        $relatedChanged = ($data['related_type'] ?? null) !== $current?->related_type || (int) ($data['related_id'] ?? 0) !== (int) $current?->related_id;

        if (! empty($data['related_type']) && $relatedChanged
            && ! $this->relatedQuery($data['related_type'])->whereKey($data['related_id'])->exists()) {
            $errors['related_id'] = sprintf('لا يوجد %s بهذا المعرّف ضمن بياناتك.', V::label(V::TASK_RELATED, $data['related_type']));
        }

        // موعدٌ جديد لا يكون في يومٍ مضى (المهمة القديمة تُعدَّل دون تغيير موعدها)
        $due = ! empty($data['due_at']) ? Carbon::parse($data['due_at']) : null;
        $dueChanged = $due && (! $current?->due_at || ! $due->equalTo($current->due_at));

        if ($dueChanged && $due->lt(now()->startOfDay())) {
            $errors['due_at'] = 'موعد المهمة لا يكون في يومٍ مضى.';
        }

        // المتابعة التالية تلي الموعد لا تسبقه
        if (! empty($data['next_follow_up_at'])) {
            $follow = Carbon::parse($data['next_follow_up_at']);

            if ($due && $follow->lt($due)) {
                $errors['next_follow_up_at'] = 'المتابعة التالية لا تسبق موعد المهمة.';
            } elseif ((! $current?->next_follow_up_at || ! $follow->equalTo($current->next_follow_up_at)) && $follow->lt(now()->startOfDay())) {
                $errors['next_follow_up_at'] = 'المتابعة التالية لا تكون في يومٍ مضى.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        if ((int) ($data['client_id'] ?? 0) !== (int) $current?->client_id) {
            $this->assertClientInScope($data['client_id'] ?? null);
        }

        if ((int) ($data['assigned_user_id'] ?? 0) !== (int) $current?->assigned_user_id) {
            $this->assertAssignable($data['assigned_user_id'] ?? null);
        }

        // مهمةٌ مرتبطة بعميل: العميل هو المرتبط نفسه إن لم يُحدَّد غيره
        if (($data['related_type'] ?? null) === 'client' && empty($data['client_id'])) {
            $data['client_id'] = $data['related_id'];
        }

        return $data;
    }

    /** يبلّغ المكلَّف بالمهمة — بنوع إشعارات العقارات إن وُجد صنفه، وإلّا بالإشعار العامّ. */
    private function notifyAssignee(Task $task, bool $reassigned = false): void
    {
        if (! $task->assigned_user_id || (int) $task->assigned_user_id === (int) auth()->id()) {
            return;
        }

        $title = ($reassigned ? 'أُسندت إليك مهمة عقارية: ' : 'مهمة عقارية جديدة: ').$task->title;
        $body = trim(($task->related_label ?? '').' '.($task->due_at ? '— الموعد '.$task->due_at->format('Y-m-d H:i') : ''));
        $class = '\Modules\RentalManagement\Support\RealEstateNotifications';

        try {
            if (class_exists($class) && method_exists($class, 'send')) {
                $class::send((int) $task->assigned_user_id, $class::TASK_ASSIGNED, $title, $body);

                return;
            }

            notifications::create([
                'user_id' => auth()->id(),
                'receiver_id' => $task->assigned_user_id,
                'title' => $title,
                'description' => $body,
                'type' => class_exists($class) ? $class::TASK_ASSIGNED : 'info',
            ]);
        } catch (\Throwable $e) {
            // فشل الإشعار لا يُسقط حفظ المهمة
            report($e);
        }
    }
}
