<?php

namespace Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientRelation;
use App\Models\EmployeeGroup;
use App\Models\notifications;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NotificationsController extends Controller
{
    public function __construct()
    {
        // مُنقولة إلى كتالوج sales.notifications.* — نفس نمط InvoicesPurchaseController
        // ملاحظة: processScheduledNotificationsApi() مسار كرون بلا مستخدمٍ مسجَّل دخوله،
        // مسجَّلٌ خارج مجموعة auth عمداً — لا تُدرَج هنا
        $this->middleware('permission:sales.notifications.view', ['only' => [
            'notifications', 'notificationsAjax', 'show', 'sentNotifications',
            'getUnreadNotifications', 'getUnreadCount', 'getEmployeePopupNotifications',
            'getClientPopupNotifications', 'getClientsByEmployees',
            'markAsReadid', 'markAsRead', 'markAllAsRead', 'markEmployeePopupAsShown', 'markClientPopupAsRead',
        ]]);
        $this->middleware('permission:sales.notifications.send', ['only' => [
            'create', 'sendNotification', 'destroy',
        ]]);
        $this->middleware('permission:sales.notifications.reply', ['only' => [
            'replyToNotification', 'replyToNotificationForm', 'replyClientToNotification', 'respondToNotification',
        ]]);
    }

    /**
     * تحقق من الصلاحيات المتعلقة بالإشعارات
     */
    private function checkNotificationPermission($permission)
    {
        $user = auth()->user();

        // من يملك النطاق الكامل لديه جميع الصلاحيات — ✅ hasRolePermission('data_scope_all') بدلاً من دورٍ ثابت
        if ($user->hasRolePermission('data_scope_all')) {
            return true;
        }

        // تحقق من الصلاحية
        return hasPermission($permission);
    }

    /**
     * فلتر «حالة الرد» — بلا رد / تم الرد عليه.
     *
     * الردُّ صفٌّ في نفس الجدول من النوع `reply`؛ ربطُه بأصله في
     * `notifications.parent_id` (والعنوان وحده للردود الأقدم من العمود) —
     * انظر `notifications::scopeAwaitingReply()`.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     */
    private function applyReplyStatusFilter($query, ?string $replyStatus): void
    {
        if ($replyStatus === 'unanswered') {
            $query->forClients()->awaitingReply();
        } elseif ($replyStatus === 'answered') {
            $query->forClients()->awaitingReply(false);
        }
    }

    /**
     * من يرى إشعارات غيره؟
     *
     * القاعدةُ نفسُها التي يعمل بها جرسُ الترويسة (`getUnreadNotifications`):
     * كان الجرسُ يعرض ما تسمح به `notifications_view_system_notifications`،
     * والقائمةُ تقصر كلَّ من `isEmployee()` على إشعاراته — فيرى المستخدمُ في
     * الجرس إشعاراً ثم لا يجده حين يضغط «عرض كل الإشعارات».
     */
    private function seesAllNotifications(): bool
    {
        $user = auth()->user();

        return ! $user->isEmployee()
            || $user->hasRolePermission('data_scope_all')
            || hasPermission('notifications_view_all_notifications')
            || hasPermission('notifications_view_system_notifications');
    }

    /**
     * استعلامُ قائمة الإشعارات بنطاق المستخدم — قبل فلاتر البحث.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    private function scopedListQuery()
    {
        $user = auth()->user();

        // شرطُ الظهور في نطاقٍ واحد على النموذج، كي يعدّه مركزُ التنبيهات في
        // لوحة العملاء بالقاعدة نفسها
        $query = notifications::query()->visibleInLists();

        if (! $this->seesAllNotifications()) {
            $query->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->orWhere('receiver_id', $user->id);
            });
        }

        return $query;
    }

    /**
     * فلاتر شاشة الإشعارات — موضعٌ واحد للصفحة ولطلب Ajax.
     *
     * `date_from`/`date_to` اسمان قديمان ما زالت روابطُ محفوظة تحملهما.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     */
    private function applyListFilters($query, Request $request, bool $withReadState = true): void
    {
        if ($request->filled('keyword')) {
            $keyword = '%'.str_replace(['%', '_'], ['\%', '\_'], trim((string) $request->input('keyword'))).'%';

            $query->where(function ($q) use ($keyword) {
                $q->where('title', 'like', $keyword)
                    ->orWhere('description', 'like', $keyword);
            });
        }

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        if ($request->filled('receiver_id')) {
            $query->where('receiver_id', $request->input('receiver_id'));
        }

        if ($request->filled('client_id')) {
            $query->where('client_id', $request->input('client_id'));
        }

        $from = $request->input('from_date', $request->input('date_from'));
        $to = $request->input('to_date', $request->input('date_to'));

        if ($from) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to) {
            $query->whereDate('created_at', '<=', $to);
        }

        if ($request->input('notification_target') === 'clients') {
            $query->whereNotNull('client_id');
        } elseif ($request->input('notification_target') === 'employees') {
            $query->whereNull('client_id');
        }

        // «بلا رد» هي وجهة بند «إشعارات عملاء بلا رد» في مركز تنبيهات لوحة العملاء
        $this->applyReplyStatusFilter($query, $request->input('reply_status'));

        if ($withReadState) {
            if ($request->input('read_state') === 'unread') {
                $query->where('read', 0);
            } elseif ($request->input('read_state') === 'read') {
                $query->where('read', 1);
            }
        }
    }

    /**
     * شاشة الإشعارات — الصفحة، وجدولُها وحده حين يكون الطلب Ajax.
     *
     * الجدول يُرسَم في الخادم (`notifications.partials.table`) للحالتين: كانت
     * الصفحةُ ترسمه بـBlade وطلبُ Ajax يعيد بناءه بنصوص JavaScript، فتباعدت
     * النسختان (شارةُ «مجدول» وأزرارٌ في واحدةٍ دون الأخرى).
     */
    public function notifications(Request $request)
    {
        // معالجة الإشعارات المجدولة تلقائياً عند فتح الصفحة
        if (! $request->ajax()) {
            $this->processScheduledNotifications();
        }

        $base = $this->scopedListQuery();

        $query = clone $base;
        $this->applyListFilters($query, $request);

        // «غير مقروءة» تُعدّ بفلاتر البحث كلِّها عدا فلتر القراءة نفسِه
        $unreadQuery = clone $base;
        $this->applyListFilters($unreadQuery, $request, false);
        $unreadCount = $unreadQuery->where('read', 0)->count();

        // `per_page` من قائمةٍ مغلقة: لا يصل إلى `paginate()` كما أرسله المتصفّح
        $perPage = (int) $request->input('per_page', 25);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 25;

        $notifications = $query
            ->with(['user:id,name', 'receiver:id,name', 'client:id,trade_name,first_name,last_name'])
            // المجدول بوقت جدولته، وغيره بوقت إنشائه
            ->orderByRaw('CASE WHEN is_scheduled = 1 AND scheduled_at IS NOT NULL THEN scheduled_at ELSE created_at END DESC')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->appends($request->except('page'));

        $tableData = [
            'notifications' => $notifications,
            'canReply' => $this->checkNotificationPermission('notifications_reply_to_notifications')
                && hasPermission('sales.notifications.reply'),
            'canDelete' => $this->checkNotificationPermission('notifications_delete_notifications')
                && hasPermission('sales.notifications.send'),
        ];

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'data' => view('notifications.partials.table', $tableData)->render(),
                'total' => $notifications->total(),
                'unread' => $unreadCount,
            ]);
        }

        /* قوائم الفلاتر من صفوف الإشعارات نفسِها. كانت `User::where('role',
           'employee')` — وعمودُ `users.role` لا يحمل هذه القيمة، فكانت قائمتا
           «المرسل» و«المستلم» فارغتين دائماً. */
        $senders = User::whereIn('id', (clone $base)->select('user_id')->whereNotNull('user_id')->distinct())
            ->orderBy('name')->get(['id', 'name']);
        $receivers = User::whereIn('id', (clone $base)->select('receiver_id')->whereNotNull('receiver_id')->distinct())
            ->orderBy('name')->get(['id', 'name']);
        $clientsWithNotifications = Client::whereIn('id', (clone $base)->select('client_id')->whereNotNull('client_id')->distinct())
            ->orderBy('trade_name')->get(['id', 'trade_name']);

        // أنواعٌ لها إشعاراتٌ فعلاً في نطاق المستخدم — لا كلُّ ما في الإعدادات
        $presentTypes = (clone $base)->whereNotNull('type')->where('type', '!=', '')->distinct()->pluck('type')->all();
        $typeOptions = collect($presentTypes)
            ->mapWithKeys(fn ($type) => [$type => notifications::typeLabel($type)])
            ->sort()
            ->all();

        return view('notifications.index', $tableData + compact(
            'senders', 'receivers', 'clientsWithNotifications', 'typeOptions', 'unreadCount'
        ));
    }

    /**
     * مسارُ `notifications.ajax` القديم — باقٍ لمن يحمل رابطه، ويردّ ما تردّه
     * الشاشة نفسُها.
     */
    public function notificationsAjax(Request $request)
    {
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');

        return $this->notifications($request);
    }

    /**
     * من يستلم «إشعاراً إلى الموظفين» — حساباتُ المستخدمين المربوطة بموظف.
     *
     * كان الشرط `users.role = 'employee'`، وهذا العمود يحمل اسمَ الدور
     * («مندوب مبيعات»، «manager»…) لا هذه الكلمة: فكانت قائمةُ الموظفين في
     * شاشة الإرسال فارغة، و«جميع الموظفين» يرسل إلى لا أحد ويردّ «تم الإرسال».
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    private function employeeRecipients()
    {
        return User::query()->whereNotNull('employee_id');
    }

    // عرض نموذج إرسال إشعار جديد
    public function create()
    {
        // ⭐ التحقق من الصلاحية
        if (! $this->checkNotificationPermission('notifications_send_notifications')) {
            abort(403, 'ليس لديك صلاحية لإرسال الإشعارات');
        }

        $employees = $this->employeeRecipients()->orderBy('name')->get(['id', 'name']);

        // ⭐ لم يعد يتم جلب العملاء هنا - يتم جلبهم عبر AJAX عند اختيار موظف
        // هذا يحسن أداء الصفحة بشكل كبير عندما يكون هناك آلاف العملاء
        return view('notifications.create', compact('employees'));
    }

    // إرسال إشعار إلى موظفين
    public function sendNotification(Request $request)
    {
        // ⭐ التحقق من الصلاحية
        if (! $this->checkNotificationPermission('notifications_send_notifications')) {
            return response()->json([
                'success' => false,
                'message' => 'ليس لديك صلاحية لإرسال الإشعارات',
            ], 403);
        }

        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string|max:1000',
            'send_to' => 'required|in:all,specific',
            'employees' => 'array|nullable',
            'notification_type' => 'string|nullable',
            'is_scheduled' => 'nullable',
            'scheduled_date' => 'nullable|date',
            'scheduled_time' => 'nullable',
            'send_type' => 'nullable|in:employee_only,all_clients,specific_clients', // ⭐ نوع الإرسال الجديد
            'send_to_clients' => 'nullable|in:all,specific',
            'client_ids' => 'array|nullable',
            'client_ids.*' => 'exists:clients,id',
        ]);

        $user = auth()->user();
        $sentCount = 0;

        // التحقق من وجود جدولة
        $isScheduled = $request->is_scheduled == 1 || $request->is_scheduled === true || $request->is_scheduled === 'true' || $request->is_scheduled === '1';

        // التحقق من نوع إشعار العميل (الطريقة القديمة)
        $isClientPopup = $request->notification_type === 'client_popup';

        // ⭐ معالجة نوع الإرسال الجديد
        $sendType = $request->send_type ?? 'employee_only';
        $shouldSendToClients = in_array($sendType, ['all_clients', 'specific_clients']);

        // ⭐ معالجة اختيار العملاء
        $clientIds = [];

        // الطريقة الجديدة: send_type
        if ($shouldSendToClients && $request->send_to === 'specific' && ! empty($request->employees)) {
            $employeeId = $request->employees[0]; // الموظف المحدد

            if ($sendType === 'all_clients') {
                // جلب جميع عملاء الموظف - نفس منطق getClientsByEmployees بالضبط
                $employeeGroupIds = EmployeeGroup::whereIn('employee_id', function ($query) use ($employeeId) {
                    $query->select('employee_id')
                        ->from('users')
                        ->where('id', $employeeId);
                })->pluck('group_id')->unique();

                Log::info('🔵 مجموعات الموظف', [
                    'employee_id' => $employeeId,
                    'employee_groups' => $employeeGroupIds->toArray(),
                ]);

                if ($employeeGroupIds->isNotEmpty()) {
                    // جلب العملاء الذين ينتمون لمناطق مرتبطة بمجموعات الموظف
                    $clientIds = Client::whereHas('neighborhood.Region', function ($q) use ($employeeGroupIds) {
                        $q->whereIn('id', $employeeGroupIds);
                    })->pluck('id')->toArray();
                }

                // إذا لم نجد عملاء بالطريقة الأولى، نجلب جميع العملاء كخيار افتراضي (مثل getClientsByEmployees)
                if (empty($clientIds) && $employeeGroupIds->isEmpty()) {
                    $clientIds = Client::pluck('id')->toArray();
                }

                Log::info('🔵 عملاء الموظف', [
                    'clients_found' => count($clientIds),
                ]);
            } elseif ($sendType === 'specific_clients') {
                // العملاء المحددين
                $clientIds = $request->client_ids ?? [];
            }
        }
        // الطريقة القديمة: client_popup
        elseif ($isClientPopup) {
            if ($request->send_to_clients === 'all') {
                $clientIds = Client::pluck('id')->toArray();
            } else {
                $clientIds = $request->client_ids ?? [];
            }
        }

        Log::info('🔴 DEBUG - Notification Send', [
            'notification_type' => $request->notification_type,
            'send_type' => $sendType,
            'send_to' => $request->send_to,
            'employees' => $request->employees,
            'client_ids_raw' => $request->client_ids,
            'shouldSendToClients' => $shouldSendToClients,
            'clientIds_count' => count($clientIds),
        ]);

        // التحقق من وجود العملاء إذا كان نوع الإرسال يتطلب عملاء
        if (($isClientPopup || $shouldSendToClients) && empty($clientIds) && $sendType !== 'employee_only') {
            return response()->json([
                'success' => false,
                'message' => 'لم يتم العثور على عملاء للموظف المحدد أو يرجى اختيار عميل واحد على الأقل',
            ], 400);
        }

        // ⭐ تحديد ما إذا كان يجب إنشاء إشعارات للعملاء
        $shouldCreateClientNotifications = $shouldSendToClients || $isClientPopup;

        Log::info('Notification Request - Full Data', [
            'all_request' => $request->all(),
            'is_scheduled_raw' => $request->is_scheduled,
            'is_scheduled_type' => gettype($request->is_scheduled),
            'isScheduled_bool' => $isScheduled,
            'scheduled_date' => $request->scheduled_date,
            'scheduled_time' => $request->scheduled_time,
            'is_client_popup' => $isClientPopup,
            'should_create_client_notifications' => $shouldCreateClientNotifications,
            'client_ids' => $clientIds,
        ]);

        $scheduledAt = null;

        if ($isScheduled) {
            Log::info('Entering scheduled notification creation');
            if (! $request->scheduled_date || ! $request->scheduled_time) {
                return response()->json([
                    'success' => false,
                    'message' => 'يرجى تحديد تاريخ ووقت الإرسال',
                ], 400);
            }

            // دمج التاريخ والوقت
            $scheduledAt = \Carbon\Carbon::parse($request->scheduled_date.' '.$request->scheduled_time);

            // التحقق من أن الوقت في المستقبل
            if ($scheduledAt->isPast()) {
                return response()->json([
                    'success' => false,
                    'message' => 'يجب أن يكون وقت الإرسال في المستقبل',
                ], 400);
            }

            // إنشاء الإشعارات المجدولة في قاعدة البيانات
            // ⭐ إذا كان إشعار عميل، ننشئ إشعار لكل عميل
            $clientIdsToProcess = $shouldCreateClientNotifications && ! empty($clientIds) ? $clientIds : [null];

            // ⭐ تحديد نوع الإشعار - إذا كان للعملاء نستخدم client_popup
            $notificationType = $shouldSendToClients ? 'client_popup' : ($request->notification_type ?? 'general');

            $employeeIds = [];
            if ($request->send_to === 'all') {
                $employees = $this->employeeRecipients()->get();
                foreach ($employees as $employee) {
                    foreach ($clientIdsToProcess as $clientId) {
                        $notification = notifications::create([
                            'user_id' => $user->id,
                            'receiver_id' => $employee->id,
                            'client_id' => $clientId, // إضافة معرف العميل
                            'title' => $request->title,
                            'description' => $request->description,
                            'type' => $notificationType,
                            'read' => 0,
                            'is_scheduled' => 1,
                            'is_sent' => 0,
                            'scheduled_at' => $scheduledAt->format('Y-m-d H:i:s'),
                        ]);

                        Log::info('Created scheduled notification', [
                            'notification_id' => $notification->id,
                            'is_scheduled' => $notification->is_scheduled,
                            'scheduled_at' => $notification->scheduled_at,
                            'client_id' => $clientId,
                        ]);

                        $sentCount++;
                    }
                }
            } else {
                if (! $request->employees || count($request->employees) === 0) {
                    return response()->json([
                        'success' => false,
                        'message' => 'يرجى اختيار موظف واحد على الأقل',
                    ], 400);
                }

                foreach ($request->employees as $employeeId) {
                    $employee = User::find($employeeId);
                    if ($employee && $employee->isEmployee()) {
                        foreach ($clientIdsToProcess as $clientId) {
                            $notification = notifications::create([
                                'user_id' => $user->id,
                                'receiver_id' => $employee->id,
                                'client_id' => $clientId, // إضافة معرف العميل
                                'title' => $request->title,
                                'description' => $request->description,
                                'type' => $notificationType,
                                'read' => 0,
                                'is_scheduled' => 1,
                                'is_sent' => 0,
                                'scheduled_at' => $scheduledAt->format('Y-m-d H:i:s'),
                            ]);

                            Log::info('Created scheduled notification for specific employee', [
                                'notification_id' => $notification->id,
                                'employee_id' => $employee->id,
                                'is_scheduled' => $notification->is_scheduled,
                                'scheduled_at' => $notification->scheduled_at,
                                'client_id' => $clientId,
                            ]);

                            $sentCount++;
                        }
                    }
                }
            }

            Log::info('Scheduled notifications created successfully', [
                'count' => $sentCount,
                'scheduled_at' => $scheduledAt->format('Y-m-d H:i:s'),
            ]);

            // ⭐ إذا كان إشعار للعميل، نضيفه كملاحظة لكل عميل
            if ($shouldCreateClientNotifications && ! empty($clientIds)) {
                foreach ($clientIds as $clientId) {
                    try {
                        $noteDescription = "📢 إشعار: {$request->title}\n\n{$request->description}";
                        $noteDescription .= "\n\n⏰ مجدول في: ".$scheduledAt->format('Y/m/d h:i A');

                        ClientRelation::create([
                            'client_id' => $clientId,
                            'employee_id' => $user->id,
                            'type' => 'client',
                            'description' => $noteDescription,
                            'date' => now()->toDateString(),
                            'time' => now()->toTimeString(),
                            'status' => 'completed',
                            'process' => 'إشعار مجدول للعميل',
                        ]);

                        Log::info('تم إنشاء ملاحظة تلقائية للإشعار المجدول', [
                            'client_id' => $clientId,
                            'user_id' => $user->id,
                            'title' => $request->title,
                        ]);
                    } catch (\Exception $noteException) {
                        Log::warning('فشل في إنشاء ملاحظة الإشعار: '.$noteException->getMessage());
                    }
                }
            }

            $clientCount = count($clientIds);

            return response()->json([
                'success' => true,
                'message' => "تم جدولة {$sentCount} إشعار".($clientCount > 1 ? " لـ {$clientCount} عميل" : '').' للإرسال في '.$scheduledAt->format('Y/m/d h:i A'),
            ]);
        }

        // إرسال فوري (الكود القديم)
        // ⭐ إذا كان إشعار عميل، ننشئ إشعار لكل عميل
        $clientIdsToProcess = $shouldCreateClientNotifications && ! empty($clientIds) ? $clientIds : [null];

        // ⭐ تحديد نوع الإشعار - إذا كان للعملاء نستخدم client_popup
        $notificationType = $shouldSendToClients ? 'client_popup' : ($request->notification_type ?? 'general');

        if ($request->send_to === 'all') {
            // إرسال إلى جميع الموظفين
            $employees = $this->employeeRecipients()->get();

            foreach ($employees as $employee) {
                foreach ($clientIdsToProcess as $clientId) {
                    notifications::create([
                        'user_id' => $user->id,
                        'receiver_id' => $employee->id,
                        'client_id' => $clientId,
                        'title' => $request->title,
                        'description' => $request->description,
                        'type' => $notificationType,
                        'read' => 0,
                        'is_scheduled' => 0,
                        'is_sent' => 1,
                        'scheduled_at' => null,
                    ]);
                    $sentCount++;
                }
            }
        } else {
            // إرسال إلى موظفين محددين
            if (! $request->employees || count($request->employees) === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'يرجى اختيار موظف واحد على الأقل',
                ], 400);
            }

            foreach ($request->employees as $employeeId) {
                $employee = User::find($employeeId);
                if ($employee && $employee->isEmployee()) {
                    foreach ($clientIdsToProcess as $clientId) {
                        notifications::create([
                            'user_id' => $user->id,
                            'receiver_id' => $employeeId,
                            'client_id' => $clientId,
                            'title' => $request->title,
                            'description' => $request->description,
                            'type' => $notificationType,
                            'read' => 0,
                            'is_scheduled' => 0,
                            'is_sent' => 1,
                            'scheduled_at' => null,
                        ]);
                        $sentCount++;
                    }
                }
            }
        }

        // ⭐ إنشاء ملاحظة لكل عميل إجبارياً
        if ($shouldCreateClientNotifications && ! empty($clientIds)) {
            foreach ($clientIds as $clientId) {
                $noteDescription = "📢 إشعار: {$request->title}\n\n{$request->description}";

                $clientRelation = ClientRelation::create([
                    'client_id' => $clientId,
                    'employee_id' => $user->id,
                    'type' => 'client',
                    'description' => $noteDescription,
                    'date' => now()->toDateString(),
                    'time' => now()->toTimeString(),
                    'status' => 'completed',
                    'process' => 'إشعار للعميل',
                ]);

                Log::info('✅ تم إنشاء ملاحظة العميل بنجاح', [
                    'client_relation_id' => $clientRelation->id,
                    'client_id' => $clientId,
                ]);
            }
        }

        $clientCount = count($clientIds);
        $employeeCount = $request->send_to === 'all' ? $this->employeeRecipients()->count() : count($request->employees ?? []);

        $message = 'تم إرسال الإشعار';
        if ($clientCount > 0) {
            $message = "تم إرسال {$sentCount} إشعار لـ {$clientCount} عميل";
        } else {
            $message = "تم إرسال الإشعار إلى {$employeeCount} موظف";
        }

        return response()->json([
            'success' => true,
            'message' => $message.' بنجاح',
        ]);
    }

    // وضع علامة مقروء على إشعار واحد
    public function markAsReadid($id)
    {
        $user = auth()->user();

        $notification = notifications::where('id', $id)
            ->where(function ($query) use ($user) {
                // النطاقُ نفسُه الذي تعرضه القائمة: صفٌّ يُرى فيها يُفتح ويُعلَّم ويُحذف
                if (! $this->seesAllNotifications()) {
                    $query->where('user_id', $user->id)
                        ->orWhere('receiver_id', $user->id);
                }
            })
            ->first();

        if ($notification) {
            $notification->read = 1;
            $notification->save();
        }

        return response()->json(['success' => true]);
    }

    // وضع علامة مقروء على إشعار واحد (POST method)
    public function markAsRead(Request $request)
    {
        $request->validate([
            'id' => 'required|integer|exists:notifications,id',
        ]);

        $user = auth()->user();

        $notification = notifications::where('id', $request->id)
            ->where(function ($query) use ($user) {
                // النطاقُ نفسُه الذي تعرضه القائمة: صفٌّ يُرى فيها يُفتح ويُعلَّم ويُحذف
                if (! $this->seesAllNotifications()) {
                    $query->where('user_id', $user->id)
                        ->orWhere('receiver_id', $user->id);
                }
            })
            ->first();

        if ($notification) {
            $notification->read = 1;
            $notification->save();
        }

        return response()->json(['success' => true]);
    }

    // وضع علامة مقروء على جميع الإشعارات
    public function markAllAsRead(Request $request)
    {
        /* «الكل» هو ما تعرضه الشاشة بفلاترها الحالية، في نطاق المستخدم. كان
           يعلّم كلَّ إشعارات النظام مقروءةً لمن ليس موظفاً، مهما كان الفلتر. */
        $query = $this->scopedListQuery();
        $this->applyListFilters($query, $request, false);

        $updated = $query->where('read', 0)->update(['read' => 1]);

        return response()->json(['success' => true, 'updated' => $updated]);
    }

    /**
     * الإشعارُ المردودُ عليه ومن يستلم الردّ — `[null, null]` حين لا يحقّ الرد.
     *
     * يردّ المستلمُ على المرسل، والمرسلُ على المستلم. وإشعارٌ عامّ (بلا مستلم)
     * يردّ عليه من يراه إلى مرسله — وكان هذا مرفوضاً دائماً بـ403 مع أن زرَّ
     * الرد في الشاشة لا يظهر إلّا له. ولا ردَّ بلا طرفٍ آخر يستلمه.
     *
     * @return array{0: notifications|null, 1: int|null}
     */
    private function resolveReplyTarget($id): array
    {
        $user = auth()->user();
        $original = notifications::find($id);

        if (! $original) {
            return [null, null];
        }

        if ($original->user_id == $user->id) {
            $receiverId = $original->receiver_id;
        } elseif ($original->receiver_id == $user->id || is_null($original->receiver_id)) {
            $receiverId = $original->user_id;
        } else {
            return [null, null];
        }

        if (! $receiverId || $receiverId == $user->id) {
            return [null, null];
        }

        return [$original, (int) $receiverId];
    }

    // الرد على الإشعار
    public function replyToNotification(Request $request, $id)
    {
        // ⭐ التحقق من الصلاحية
        if (! $this->checkNotificationPermission('notifications_reply_to_notifications')) {
            return response()->json([
                'success' => false,
                'message' => 'ليس لديك صلاحية للرد على الإشعارات',
            ], 403);
        }

        $request->validate([
            'reply_message' => 'required|string|max:500',
        ]);

        $user = auth()->user();

        [$originalNotification, $receiverId] = $this->resolveReplyTarget($id);

        if (! $originalNotification) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكنك الرد على هذا الإشعار',
            ], 403);
        }

        // إنشاء إشعار جديد كرد — مربوطاً بأصله كي يُعرف الإشعارُ المردودُ عليه
        $replyNotification = notifications::create(array_merge([
            'user_id' => $user->id,
            'receiver_id' => $receiverId,
            'client_id' => $originalNotification->client_id,
            'title' => 'رد على: '.$originalNotification->title,
            'description' => $request->reply_message,
            'type' => 'reply',
            'read' => 0,
        ], notifications::supportsParentLink() ? ['parent_id' => $originalNotification->id] : []));

        // وضع علامة مقروء على الإشعار الأصلي
        $originalNotification->update(['read' => 1]);

        return response()->json([
            'success' => true,
            'message' => 'تم إرسال الرد بنجاح',
            'notification' => [
                'id' => $replyNotification->id,
                'title' => $replyNotification->title,
                'description' => $replyNotification->description,
            ],
        ]);
    }

    /**
     * الرد على الإشعار باستخدام Form Submission (للتوافق مع الموبايل)
     * هذه الطريقة تستخدم redirect بدلاً من AJAX
     */
    public function replyToNotificationForm(Request $request, $id)
    {
        // التحقق من الصلاحية
        if (! $this->checkNotificationPermission('notifications_reply_to_notifications')) {
            return redirect()->back()->with('error', 'ليس لديك صلاحية للرد على الإشعارات');
        }

        $request->validate([
            'reply_message' => 'required|string|max:500',
        ]);

        $user = auth()->user();

        [$originalNotification, $receiverId] = $this->resolveReplyTarget($id);

        if (! $originalNotification) {
            return redirect()->back()->with('error', 'لا يمكنك الرد على هذا الإشعار');
        }

        // إنشاء إشعار جديد كرد — مربوطاً بأصله كي يُعرف الإشعارُ المردودُ عليه
        $replyNotification = notifications::create(array_merge([
            'user_id' => $user->id,
            'receiver_id' => $receiverId,
            'client_id' => $originalNotification->client_id,
            'title' => 'رد على: '.$originalNotification->title,
            'description' => $request->reply_message,
            'type' => 'reply',
            'read' => 0,
        ], notifications::supportsParentLink() ? ['parent_id' => $originalNotification->id] : []));

        // وضع علامة مقروء على الإشعار الأصلي
        $originalNotification->update(['read' => 1]);

        // إعادة التوجيه مع رسالة نجاح
        $redirectUrl = $request->input('redirect_url', '/');

        // إضافة timestamp لمنع الكاش
        $separator = strpos($redirectUrl, '?') !== false ? '&' : '?';
        $redirectUrl .= $separator.'_t='.time();

        return redirect($redirectUrl)->with('success', 'تم إرسال الرد بنجاح ✅');
    }

    // عرض تفاصيل الإشعار مع السلسلة الكاملة للردود
    public function show($id)
    {
        $user = auth()->user();

        $notification = notifications::with(['user', 'receiver'])
            ->where('id', $id)
            ->where(function ($query) use ($user) {
                // النطاقُ نفسُه الذي تعرضه القائمة: صفٌّ يُرى فيها يُفتح ويُعلَّم ويُحذف
                if (! $this->seesAllNotifications()) {
                    $query->where('user_id', $user->id)
                        ->orWhere('receiver_id', $user->id);
                }
            })
            ->first();

        // If notification not found, return 404
        if (! $notification) {
            return response()->json([
                'success' => false,
                'message' => 'الإشعار غير موجود',
            ], 404);
        }

        // جلب جميع الردود المرتبطة
        /* ردودُ **هذا** الإشعار وحده. كان الشرطُ `type = reply` فقط، فتعرض
           صفحةُ أيّ إشعارٍ كلَّ ردود النظام. والقاعدةُ هي هي في
           `notifications::scopeAwaitingReply()`: `parent_id`، أو — للردود
           السابقة للعمود — عنوانُ «رد على: …» لنفس العميل بعد تاريخ الأصل. */
        $replies = notifications::with(['user', 'receiver'])
            ->whereIn('id', notifications::replyThreads()[$notification->id] ?? [0])
            ->orderBy('created_at', 'asc')
            ->get();

        // «طلب تغيير حالة» ردُّه القرار (اعتماد/رفض) لا رسالة — notifications::statusDecisionReplies()
        $decision = in_array($notification->type, notifications::STATUS_REQUEST_TYPES, true)
            ? (notifications::statusDecisionReplies()[$notification->id] ?? null)
            : null;

        // وضع علامة مقروء
        if ($notification->receiver_id == $user->id && $notification->read == 0) {
            $notification->update(['read' => 1]);
        }

        // إذا كان AJAX request، أرجع HTML
        if (request()->ajax()) {
            $html = view('notifications.partials.notification-detail', compact('notification', 'replies', 'decision'))->render();

            return response()->json([
                'success' => true,
                'notification' => [
                    'id' => $notification->id,
                    'title' => $notification->title,
                    'description' => $notification->description,
                    'user' => $notification->user,
                    'receiver' => $notification->receiver,
                    'created_at' => $notification->created_at,
                    'read' => $notification->read,
                    'type' => $notification->type,
                ],
                'html' => $html,
            ]);
        }

        return view('notifications.show', compact('notification', 'replies', 'decision'));
    }

    // حذف إشعار
    public function destroy($id)
    {
        // ⭐ التحقق من الصلاحية
        if (! $this->checkNotificationPermission('notifications_delete_notifications')) {
            return response()->json([
                'success' => false,
                'message' => 'ليس لديك صلاحية لحذف الإشعارات',
            ], 403);
        }

        $user = auth()->user();

        $notification = notifications::where('id', $id)
            ->where(function ($query) use ($user) {
                // النطاقُ نفسُه الذي تعرضه القائمة: صفٌّ يُرى فيها يُفتح ويُعلَّم ويُحذف
                if (! $this->seesAllNotifications()) {
                    $query->where('user_id', $user->id)
                        ->orWhere('receiver_id', $user->id);
                }
            })
            ->first();

        if ($notification) {
            // حذف جميع الردود المرتبطة — كان الشرط على `data->original_notification_id`
            // وهو عمودٌ لا وجود له في الجدول، فلا يُحذف معه ردٌّ واحد
            if (notifications::supportsParentLink()) {
                notifications::where('parent_id', $id)->delete();
            }

            $notification->delete();

            return response()->json([
                'success' => true,
                'message' => 'تم حذف الإشعار بنجاح',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'الإشعار غير موجود',
        ], 404);
    }

    // Method to send automatic notifications to employees
    public static function sendAutomaticNotification($userId, $notificationData = [])
    {
        $user = User::find($userId);

        if (! $user) {
            return false;
        }

        // Get all employees
        $employees = User::where('role', 'employee')->get();

        // Create a notification for each employee
        foreach ($employees as $employee) {
            notifications::create([
                'user_id' => $userId,
                'receiver_id' => $employee->id,
                'title' => $notificationData['title'] ?? 'تنبيه تلقائي',
                'description' => $notificationData['description'] ?? "قام المستخدم {$user->name} بتنفيذ إجراء في الوقت ".now()->format('Y-m-d H:i:s'),
                'read' => 0,
                'type' => $notificationData['type'] ?? 'automatic_notification',
                'is_scheduled' => 0,
                'is_sent' => 1,
                'scheduled_at' => null,
            ]);
        }

        return true;
    }

    // Method to send notification to specific employee
    public static function sendNotificationToEmployee($userId, $employeeId, $notificationData = [])
    {
        $user = User::find($userId);
        $employee = User::find($employeeId);

        if (! $user || ! $employee) {
            return false;
        }

        notifications::create([
            'user_id' => $userId,
            'receiver_id' => $employeeId,
            'title' => $notificationData['title'] ?? 'تنبيه',
            'description' => $notificationData['description'] ?? "قام المستخدم {$user->name} بتنفيذ إجراء في الوقت ".now()->format('Y-m-d H:i:s'),
            'read' => 0,
            'type' => $notificationData['type'] ?? 'notification',
            'is_scheduled' => 0,
            'is_sent' => 1,
            'scheduled_at' => null,
        ]);

        return true;
    }

    // عرض الإشعارات المرسلة
    public function sentNotifications(Request $request)
    {
        $user = auth()->user();

        $query = notifications::with(['user', 'receiver'])
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc');

        // فلتر البحث
        if ($request->has('receiver_id') && $request->receiver_id != '') {
            $query->where('receiver_id', $request->receiver_id);
        }

        $notifications = $query->paginate(100);
        $employees = User::where('role', 'employee')->get();

        return view('notifications.sent', compact('notifications', 'employees'));
    }

    // الرد على الإشعار (عرض نموذج الرد)
    public function respondToNotification($id)
    {
        $user = auth()->user();

        $notification = notifications::with(['user', 'receiver'])
            ->where('id', $id)
            ->where('receiver_id', $user->id)
            ->first();

        if (! $notification) {
            return redirect()->route('notifications.index')->with('error', 'الإشعار غير موجود أو ليس موجهاً لك');
        }

        return view('notifications.respond', compact('notification'));
    }

    public function getUnreadNotifications()
    {
        $user = auth()->user();

        $query = notifications::with(['user', 'receiver'])
            ->where('read', 0)
            // ⭐ إضافة شرط 24 ساعة فقط
            ->where('created_at', '>=', now()->subHours(24))
            ->where(function ($q) {
                // عرض الإشعارات غير المجدولة
                $q->where(function ($subQ) {
                    $subQ->where('is_scheduled', 0)
                        ->orWhereNull('is_scheduled');
                })
                // أو الإشعارات المجدولة التي حان وقتها أو تم إرسالها
                    ->orWhere(function ($subQ) {
                        $subQ->where('is_scheduled', 1)
                            ->where(function ($innerQ) {
                                $innerQ->where('is_sent', 1)
                                    ->orWhere('scheduled_at', '<=', now());
                            });
                    })
                // أو الإشعارات المجدولة من نوع غير general (تظهر فوراً حتى لو مجدولة)
                    ->orWhere(function ($subQ) {
                        $subQ->where('is_scheduled', 1)
                            ->where(function ($typeQ) {
                                $typeQ->where('type', '!=', 'general')
                                    ->orWhereNull('type');
                            });
                    });
            })
            ->orderBy('created_at', 'desc');

        // فلترة الإشعارات حسب الصلاحية
        // ⭐ إذا كان لديه صلاحية notifications_view_system_notifications، يرى كل الإشعارات
        if (! $this->seesAllNotifications()) {
            if ($user->isEmployee()) {
                $query->where(function ($q) use ($user) {
                    $q->where('user_id', $user->id)
                        ->orWhere('receiver_id', $user->id);
                });
            }
        }

        /* العدُّ كاملاً والقائمةُ بأحدث عشرين: كانت القائمةُ المنسدلة تحمل كلَّ
           غير المقروء في اليوم — مئات الصفوف في جرسٍ يُحدَّث كل دقيقة. */
        $count = (clone $query)->count();
        $notifications = $query->limit(20)->get();

        $response = [
            'success' => true,
            'count' => $count,
            'notifications' => $notifications->map(function ($notification) use ($user) {
                return [
                    'id' => $notification->id,
                    'title' => $notification->title,
                    'description' => $notification->description,

                    // معلومات المرسل
                    'sender' => $notification->user ? $notification->user->name : 'النظام',
                    'sender_id' => $notification->user_id,

                    // معلومات المستقبل
                    'receiver' => $notification->receiver ? $notification->receiver->name : 'الجميع',
                    'receiver_id' => $notification->receiver_id,

                    'created_at' => $notification->created_at
                        ? $notification->created_at->toIso8601String()
                        : now()->toIso8601String(),
                    'type' => $notification->type ?? 'general',

                    // ⭐ الآن جميع المستخدمين يمكنهم الرد
                    'can_reply' => true,

                    // تحديد علاقة المستخدم بالإشعار
                    'is_sender' => $notification->user_id == $user->id,
                    'is_receiver' => $notification->receiver_id == $user->id,
                ];
            }),
        ];

        return response()->json($response);
    }

    // ⭐ إضافة دالة جديدة لحساب عدد الإشعارات غير المقروءة خلال 24 ساعة
    public function getUnreadCount()
    {
        $user = auth()->user();

        $query = notifications::where('read', 0)
            ->where('created_at', '>=', now()->subHours(24)) // فقط آخر 24 ساعة
            ->where(function ($q) {
                // عرض الإشعارات غير المجدولة
                $q->where(function ($subQ) {
                    $subQ->where('is_scheduled', 0)
                        ->orWhereNull('is_scheduled');
                })
                // أو الإشعارات المجدولة التي حان وقتها أو تم إرسالها
                    ->orWhere(function ($subQ) {
                        $subQ->where('is_scheduled', 1)
                            ->where(function ($innerQ) {
                                $innerQ->where('is_sent', 1)
                                    ->orWhere('scheduled_at', '<=', now());
                            });
                    })
                // أو الإشعارات المجدولة من نوع غير general (تظهر فوراً حتى لو مجدولة)
                    ->orWhere(function ($subQ) {
                        $subQ->where('is_scheduled', 1)
                            ->where(function ($typeQ) {
                                $typeQ->where('type', '!=', 'general')
                                    ->orWhereNull('type');
                            });
                    });
            });

        // فلترة الإشعارات حسب الصلاحية
        // ⭐ إذا كان لديه صلاحية notifications_view_system_notifications، يرى كل الإشعارات
        if (! $this->seesAllNotifications()) {
            if ($user->isEmployee()) {
                $query->where(function ($q) use ($user) {
                    $q->where('user_id', $user->id)
                        ->orWhere('receiver_id', $user->id);
                });
            }
        }

        $count = $query->count();

        return response()->json([
            'success' => true,
            'count' => $count,
        ]);
    }

    /**
     * ⭐ جلب إشعارات Popup للموظف
     * تُستخدم لعرض الإشعارات كـ SweetAlert2 popup للموظف
     * تدعم الإشعارات المجدولة والفورية
     */
    public function getEmployeePopupNotifications()
    {
        try {
            $user = auth()->user();

            if (! $user) {
                Log::warning('🔔 محاولة جلب إشعارات بدون تسجيل دخول');

                return response()->json([
                    'success' => false,
                    'message' => 'المستخدم غير مسجل دخول',
                ], 401);
            }

            Log::info('🔔 جلب إشعارات Popup للموظف: '.$user->id.' - '.$user->name);

            $now = now();

            // التحقق من وجود عمود popup_shown
            $hasPopupShownColumn = \Schema::hasColumn('notifications', 'popup_shown');

            Log::info('🔔 DEBUG - hasPopupShownColumn: '.($hasPopupShownColumn ? 'yes' : 'no'));

            // أولاً: عد كل الإشعارات للموظف للتشخيص
            $totalNotifications = notifications::where('receiver_id', $user->id)->count();
            $unreadNotifications = notifications::where('receiver_id', $user->id)->where('read', 0)->count();

            Log::info('🔔 DEBUG - Total notifications for user: '.$totalNotifications);
            Log::info('🔔 DEBUG - Unread notifications for user: '.$unreadNotifications);

            // ⭐ جلب الإشعارات نفس نظام صفحة العميل
            // الإشعارات الموجهة للموظف من الإدارة بنفس آلية client_popup
            // الفرق: بدلاً من client_id تكون receiver_id = الموظف الحالي

            $query = notifications::with(['user', 'client'])
                // ⭐ الشرط الأساسي: الإشعار موجه للموظف الحالي (مثل client_popup في صفحة العميل)
                ->where('receiver_id', $user->id)
                // ⭐ الإشعارات غير المقروءة فقط
                ->where('read', 0)
                // ⭐ استبعاد إشعارات client_popup (هذه لا تظهر في الداشبورد)
                ->where(function ($q) {
                    $q->where('type', '!=', 'client_popup')
                        ->orWhereNull('type');
                });

            // إذا كان عمود popup_shown موجود، نستخدمه
            if ($hasPopupShownColumn) {
                $query->where('popup_shown', 0);
            }

            // ⭐ الإشعارات الفورية أو المجدولة التي حان وقتها (نفس نظام صفحة العميل)
            $query->where(function ($q) use ($now) {
                // إما إشعارات فورية
                $q->where('is_sent', 1)
                // أو إشعارات مجدولة حان وقتها
                    ->orWhere(function ($subQ) use ($now) {
                        $subQ->where('is_scheduled', 1)
                            ->where('scheduled_at', '<=', $now);
                    });
            });

            // طباعة الـ SQL للتشخيص
            Log::info('🔔 DEBUG - SQL Query: '.$query->toSql());
            Log::info('🔔 DEBUG - Query Bindings: '.json_encode($query->getBindings()));

            $notifications = $query->get();

            // ⭐ إضافة الردود على إشعاراتك
            // إذا الموظف أرسل إشعار وفيه رد عليه، يظهر الرد
            $replies = notifications::with(['user', 'client'])
                ->where('receiver_id', $user->id)  // الردود الموجهة للموظف الحالي
                ->where('type', 'reply')           // ردود فقط
                ->where('read', 0)                 // غير مقروءة فقط
                ->orderBy('created_at', 'desc')
                ->get();

            // دمج الإشعارات والردود
            $notifications = $notifications->concat($replies);

            Log::info('📬 عدد الإشعارات المنبثقة للموظف: '.$notifications->count());
            Log::info('📬 عدد الردود على إشعاراتك: '.$replies->count());

            $notificationsData = $notifications->map(function ($notification) {
                $clientName = null;
                $clientCode = null;
                if ($notification->client_id && $notification->client) {
                    $clientName = $notification->client->trade_name ?: $notification->client->name;
                    $clientCode = $notification->client->code;
                }

                return [
                    'id' => $notification->id,
                    'title' => $notification->title,
                    'description' => $notification->description,
                    'sender' => $notification->user ? $notification->user->name : 'النظام',
                    'sender_id' => $notification->user_id,
                    'type' => $notification->type ?? 'general',
                    'client_id' => $notification->client_id,
                    'client_name' => $clientName,
                    'client_code' => $clientCode,
                    'is_client_reply' => $notification->client_id ? true : false,
                    'data' => $notification->data,
                    'is_scheduled' => $notification->is_scheduled ? true : false,
                    'scheduled_at' => $notification->scheduled_at
                        ? \Carbon\Carbon::parse($notification->scheduled_at)->format('Y/m/d h:i A')
                        : null,
                    'created_at' => $notification->created_at
                        ? $notification->created_at->format('Y/m/d h:i A')
                        : now()->format('Y/m/d h:i A'),
                ];
            });

            return response()->json([
                'success' => true,
                'count' => $notifications->count(),
                'notifications' => $notificationsData,
            ]);

        } catch (\Exception $e) {
            Log::error('❌ خطأ في جلب إشعارات Popup للموظف: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في جلب الإشعارات',
                'error' => $e->getMessage(),
            ], 500);
        }

    }

    /**
     * ⭐ وضع علامة "تم العرض كـ popup" على إشعار الموظف
     */
    public function markEmployeePopupAsShown(Request $request)
    {
        try {
            $user = auth()->user();

            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'المستخدم غير مسجل دخول',
                ], 401);
            }

            $notificationId = $request->notification_id;

            $notification = notifications::where('id', $notificationId)
                ->where('receiver_id', $user->id)
                ->first();

            if (! $notification) {
                return response()->json([
                    'success' => false,
                    'message' => 'الإشعار غير موجود',
                ], 404);
            }

            // التحقق من وجود عمود popup_shown
            $hasPopupShownColumn = \Schema::hasColumn('notifications', 'popup_shown');

            // وضع علامة أنه تم عرضه كـ popup ومقروء
            $updateData = ['read' => 1];
            if ($hasPopupShownColumn) {
                $updateData['popup_shown'] = 1;
            }

            $notification->update($updateData);

            Log::info('✅ تم وضع علامة مقروء على الإشعار: '.$notificationId);

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث حالة الإشعار',
            ]);

        } catch (\Exception $e) {
            Log::error('❌ خطأ في تحديث حالة الإشعار: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * معالجة الإشعارات المجدولة التي حان وقتها
     */
    private function processScheduledNotifications()
    {
        try {
            $now = now();

            // جلب الإشعارات المجدولة التي حان وقت إرسالها
            $scheduledNotifications = notifications::where('is_scheduled', true)
                ->where('is_sent', false)
                ->where('scheduled_at', '<=', $now)
                ->get();

            foreach ($scheduledNotifications as $notification) {
                $notification->update(['is_sent' => true]);
            }
        } catch (\Exception $e) {
            Log::error('Error processing scheduled notifications: '.$e->getMessage());
        }
    }

    /**
     * معالجة الإشعارات المجدولة - API للكرون جوب
     * الرابط: /api/notifications/process-scheduled
     */
    public function processScheduledNotificationsApi()
    {
        try {
            $now = now();

            // جلب الإشعارات المجدولة التي حان وقت إرسالها
            $scheduledNotifications = notifications::where('is_scheduled', true)
                ->where('is_sent', false)
                ->where('scheduled_at', '<=', $now)
                ->get();

            if ($scheduledNotifications->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'لا توجد إشعارات مجدولة لمعالجتها',
                    'processed' => 0,
                ]);
            }

            $processedCount = 0;
            foreach ($scheduledNotifications as $notification) {
                $notification->update(['is_sent' => true]);
                $processedCount++;

                Log::info('Scheduled notification sent', [
                    'notification_id' => $notification->id,
                    'title' => $notification->title,
                    'scheduled_at' => $notification->scheduled_at,
                    'sent_at' => now(),
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => "تم معالجة {$processedCount} إشعار بنجاح",
                'processed' => $processedCount,
                'timestamp' => now()->toDateTimeString(),
            ]);

        } catch (\Exception $e) {
            Log::error('Error processing scheduled notifications API: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء معالجة الإشعارات المجدولة',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * جلب إشعارات Popup للعميل
     * تُستخدم في صفحة عرض العميل لعرض الإشعارات المجدولة كـ SweetAlert2
     * ⭐ ملاحظة: الإشعارات تظهر فقط للموظف المستلم وليس للإدارة
     */
    public function getClientPopupNotifications($clientId)
    {
        try {
            $user = auth()->user();
            $now = now();

            Log::info('🔔 Checking popup notifications', [
                'client_id' => $clientId,
                'user_id' => $user->id,
                'user_role' => $user->role,
                'now' => $now->toDateTimeString(),
            ]);

            // أولاً: نتحقق من وجود أي إشعارات للعميل من نوع client_popup
            $allClientNotifications = notifications::where('client_id', $clientId)
                ->where('type', 'client_popup')
                ->get();

            Log::info('🔔 All client_popup notifications for this client', [
                'count' => $allClientNotifications->count(),
                'notifications' => $allClientNotifications->map(function ($n) use ($user) {
                    return [
                        'id' => $n->id,
                        'type' => $n->type,
                        'receiver_id' => $n->receiver_id,
                        'current_user_id' => $user->id,
                        'is_receiver_match' => $n->receiver_id == $user->id,
                        'read' => $n->read,
                        'is_scheduled' => $n->is_scheduled,
                        'is_sent' => $n->is_sent,
                    ];
                }),
            ]);

            // جلب الإشعارات لهذا العميل التي لم تُقرأ بعد
            // ⭐ للموظف: نعرض فقط الإشعارات الموجهة له
            // ⭐ للأدمن: نعرض جميع الإشعارات للاختبار
            $query = notifications::with(['user'])
                ->where('client_id', $clientId)
                ->where('type', 'client_popup')
                ->where('read', 0);

            // ⭐ تقييد الظهور للمستلم فقط - حتى لو كان أدمن لا يظهر له المنبثق إلا إذا كان هو المستلم
            $query->where('receiver_id', $user->id);

            // الإشعارات الفورية أو المجدولة التي حان وقتها
            $query->where(function ($q) use ($now) {
                $q->where('is_sent', 1)
                    ->orWhere(function ($subQ) use ($now) {
                        $subQ->where('is_scheduled', 1)
                            ->where('scheduled_at', '<=', $now);
                    });
            });

            $notifications = $query->orderBy('created_at', 'desc')->get();

            Log::info('🔔 Filtered popup notifications to show', [
                'count' => $notifications->count(),
                'ids' => $notifications->pluck('id'),
            ]);

            $notificationsData = $notifications->map(function ($notification) {
                return [
                    'id' => $notification->id,
                    'title' => $notification->title,
                    'description' => $notification->description,
                    'type' => $notification->type,
                    'scheduled_at' => $notification->scheduled_at ? $notification->scheduled_at->format('Y/m/d h:i A') : null,
                    'created_at' => $notification->created_at->format('Y/m/d h:i A'),
                    'sender' => $notification->user ? $notification->user->name : 'النظام',
                ];
            });

            return response()->json([
                'success' => true,
                'count' => $notifications->count(),
                'notifications' => $notificationsData,
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching client popup notifications: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب الإشعارات',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * وضع علامة مقروء على إشعار popup للعميل
     */
    public function markClientPopupAsRead(Request $request)
    {
        try {
            $request->validate([
                'notification_id' => 'required|integer|exists:notifications,id',
            ]);

            $notification = notifications::find($request->notification_id);

            if ($notification) {
                $notification->update(['read' => 1]);

                return response()->json([
                    'success' => true,
                    'message' => 'تم وضع علامة مقروء على الإشعار',
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'الإشعار غير موجود',
            ], 404);

        } catch (\Exception $e) {
            Log::error('Error marking client popup notification as read: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * إضافة الإشعار كملاحظة للعميل
     * تُستخدم لتسجيل إشعارات client_popup كملاحظات في سجل العميل
     */
    private function addNotificationAsClientNote($clientId, $userId, $title, $description, $scheduledAt = null)
    {
        try {
            // إنشاء ملاحظة للعميل بنفس طريقة الفواتير
            $noteDescription = "📢 إشعار: {$title}\n\n{$description}";

            if ($scheduledAt) {
                $noteDescription .= "\n\n⏰ مجدول في: ".$scheduledAt->format('Y/m/d h:i A');
            }

            $clientRelation = ClientRelation::create([
                'employee_id' => $userId,
                'client_id' => $clientId,
                'status' => 'completed',
                'process' => 'إشعار للعميل',
                'description' => $noteDescription,
                'type' => 'notification',
                'date' => now()->toDateString(),
                'time' => now()->toTimeString(),
            ]);

            Log::info('Client notification added as note successfully', [
                'client_relation_id' => $clientRelation->id,
                'client_id' => $clientId,
                'user_id' => $userId,
                'title' => $title,
                'scheduled_at' => $scheduledAt ? $scheduledAt->format('Y-m-d H:i:s') : null,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Error adding notification as client note: '.$e->getMessage(), [
                'client_id' => $clientId,
                'user_id' => $userId,
                'title' => $title,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * رد العميل على الإشعار
     */
    public function replyClientToNotification(Request $request, $id)
    {
        // ⭐ التحقق من الصلاحية
        if (! $this->checkNotificationPermission('notifications_reply_to_notifications')) {
            return response()->json([
                'success' => false,
                'message' => 'ليس لديك صلاحية للرد على الإشعارات',
            ], 403);
        }

        $request->validate([
            'reply_message' => 'required|string|max:500',
        ]);

        $user = auth()->user();

        // التحقق من أن الإشعار موجود وأن المستخدم الحالي هو العميل المستقبل له
        $originalNotification = notifications::where('id', $id)
            ->where('receiver_id', $user->id)
            ->where('type', 'client_popup')
            ->first();

        if (! $originalNotification) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكنك الرد على هذا الإشعار',
            ], 403);
        }

        // إنشاء إشعار جديد كرد من العميل للموظف الذي أرسل الرسالة
        $replyNotification = notifications::create(array_merge([
            'user_id' => $user->id,  // العميل (المرسل الآن)
            'receiver_id' => $originalNotification->user_id,  // الموظف الأصلي
            'title' => 'رد عميل: '.$originalNotification->title,
            'description' => $request->reply_message,
            'type' => 'reply',  // نفس النوع
            'client_id' => $originalNotification->client_id,
            'read' => 0,
        ], notifications::supportsParentLink() ? ['parent_id' => $originalNotification->id] : []));

        // وضع علامة مقروء على الإشعار الأصلي
        $originalNotification->update(['read' => 1]);

        return response()->json([
            'success' => true,
            'message' => 'تم إرسال الرد بنجاح',
            'notification' => [
                'id' => $replyNotification->id,
                'title' => $replyNotification->title,
                'description' => $replyNotification->description,
            ],
        ]);
    }

    /**
     * جلب العملاء حسب مجموعات الموظفين المحددين
     * تُستخدم في صفحة إنشاء الإشعارات لعرض العملاء الخاصين بمجموعات الموظف فقط
     */
    public function getClientsByEmployees(Request $request)
    {
        try {
            $employeeIds = $request->input('employee_ids', []);
            $search = $request->input('search', '');
            $page = $request->input('page', 1);
            $perPage = 50; // ⭐ Pagination لتحسين الأداء

            // إذا لم يتم تحديد موظفين، إرجاع قائمة فارغة
            if (empty($employeeIds)) {
                return response()->json([
                    'data' => [],
                    'total' => 0,
                    'has_more' => false,
                ]);
            }

            // جلب مجموعات جميع الموظفين المحددين
            $employeeGroupIds = EmployeeGroup::whereIn('employee_id', function ($query) use ($employeeIds) {
                // جلب employee_id من جدول users حيث id في employeeIds
                $query->select('employee_id')
                    ->from('users')
                    ->whereIn('id', $employeeIds);
            })->pluck('group_id')->unique();

            // بناء الاستعلام الأساسي
            $query = Client::select('id', 'trade_name', 'code');

            // إذا كان للموظفين مجموعات، فلترة العملاء حسب المناطق
            if ($employeeGroupIds->isNotEmpty()) {
                $query->whereHas('neighborhood.Region', function ($q) use ($employeeGroupIds) {
                    $q->whereIn('id', $employeeGroupIds);
                });
            }

            // تطبيق البحث إذا وجد
            if (! empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('trade_name', 'like', '%'.$search.'%')
                        ->orWhere('code', 'like', '%'.$search.'%');
                });
            }

            // الحصول على العدد الكلي
            $total = $query->count();

            // جلب العملاء مع Pagination
            $clients = $query->orderBy('trade_name')
                ->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get();

            return response()->json([
                'data' => $clients,
                'total' => $total,
                'has_more' => ($page * $perPage) < $total,
                'current_page' => $page,
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching clients by employees: '.$e->getMessage());

            return response()->json([
                'error' => 'حدث خطأ أثناء جلب العملاء',
            ], 500);
        }
    }
}
