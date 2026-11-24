<?php

namespace Modules\Integration\Http\Controllers\Api\Notifications;

use App\Http\Controllers\Controller;
use App\Models\notifications;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Modules\Integration\Http\Controllers\Api\Concerns\RespondsJson;

/**
 * الإشعارات الداخلية (جدول `notifications`) — نفس `NotificationsController`
 * في الموقع ونفس بوّاباته، كلّها على المسار (routes/api/notifications.php):
 * `sales.notifications.*` وميزة الباقة، وفحوص كتالوج `notifications_*` التي
 * يجريها متحكّم الموقع داخله (ومالك `data_scope_all` يتجاوزها).
 *
 * الموظف يرى ما أرسله أو ما وصله فقط. لا إشعارات دفعٍ (Push) — التطبيق
 * يسأل `unread-count` دوريّاً.
 */
class NotificationController extends Controller
{
    use RespondsJson;

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'unread' => 'nullable|boolean',
            'client_id' => 'nullable|integer',
            'type' => 'nullable|string|max:60',
            'target' => 'nullable|in:clients,employees',
            'reply_status' => 'nullable|in:unanswered,answered',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
        ]);

        $user = $request->user();
        $query = notifications::with(['user:id,name', 'receiver:id,name', 'client:id,trade_name'])
            ->visibleInLists()
            ->where(fn ($q) => $q->where('user_id', $user->id)->orWhere('receiver_id', $user->id))
            ->when($request->boolean('unread'), fn ($q) => $q->where('read', 0)->where('receiver_id', $user->id))
            ->when($request->filled('client_id'), fn ($q) => $q->where('client_id', $request->integer('client_id')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->input('type')))
            ->when($request->input('target') === 'clients', fn ($q) => $q->whereNotNull('client_id'))
            ->when($request->input('target') === 'employees', fn ($q) => $q->whereNull('client_id'))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('created_at', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('created_at', '<=', $request->input('date_to')))
            ->orderByRaw('CASE WHEN is_scheduled = 1 AND scheduled_at IS NOT NULL THEN scheduled_at ELSE created_at END DESC')
            ->orderByDesc('id');

        if ($status = $request->input('reply_status')) {
            $query->forClients()->awaitingReply($status === 'unanswered');
        }

        $page = $query->paginate($this->perPage(20));

        return $this->paginated($page, $page->getCollection()->map(fn ($n) => $this->present($n, (int) $user->id))->values()->all(), [
            'unread_count' => $this->unreadQuery($user)->count(),
        ]);
    }

    /** عدّاد الجرس — نفس `getUnreadCount()`: غير المقروء خلال آخر ٢٤ ساعة. */
    public function unreadCount(Request $request): JsonResponse
    {
        return $this->ok(['count' => $this->unreadQuery($request->user())->count()]);
    }

    public function show(Request $request, int $notification): JsonResponse
    {
        $user = $request->user();
        $row = notifications::with(['user:id,name', 'receiver:id,name', 'client:id,trade_name'])
            ->where(fn ($q) => $q->where('user_id', $user->id)->orWhere('receiver_id', $user->id))
            ->findOrFail($notification);

        $replies = $this->repliesOf($row);

        $decision = in_array($row->type, notifications::STATUS_REQUEST_TYPES, true)
            ? (notifications::statusDecisionReplies()[$row->id] ?? null)
            : null;

        if ((int) $row->receiver_id === (int) $user->id && ! $row->read) {
            $row->update(['read' => 1]);
        }

        return $this->ok($this->present($row, (int) $user->id) + [
            'replies' => $replies->map(fn ($r) => [
                'id' => (int) $r->id,
                'description' => $r->description,
                'from' => $r->user?->name,
                'created_at' => $r->created_at?->toDateTimeString(),
            ])->values(),
            'decision' => $decision,
        ]);
    }

    public function markRead(Request $request, int $notification): JsonResponse
    {
        $user = $request->user();
        $row = notifications::where(fn ($q) => $q->where('user_id', $user->id)->orWhere('receiver_id', $user->id))->findOrFail($notification);
        $row->update(['read' => 1]);

        return $this->ok(['id' => (int) $row->id, 'read' => true], 'عُلِّم الإشعار مقروءاً.');
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $user = $request->user();
        $count = notifications::where('read', 0)
            ->where(fn ($q) => $q->where('user_id', $user->id)->orWhere('receiver_id', $user->id))
            ->update(['read' => 1]);

        return $this->ok(['updated' => $count], $count ? 'عُلِّمت '.$count.' إشعارات مقروءة.' : 'لا إشعارات غير مقروءة.');
    }

    /** الردّ يصل الطرف الآخر ويقفل الأصل — نفس `replyToNotification()`. */
    public function reply(Request $request, int $notification): JsonResponse
    {
        $data = $request->validate(['reply_message' => 'required|string|max:500'], ['reply_message.required' => 'اكتب نصّ الردّ.']);
        $user = $request->user();

        $original = notifications::where(fn ($q) => $q->where('receiver_id', $user->id)->orWhere('user_id', $user->id))->find($notification);
        if (! $original) {
            return $this->fail('لا يمكنك الردّ على هذا الإشعار.', 403);
        }

        $receiverId = (int) $original->receiver_id === (int) $user->id ? $original->user_id : $original->receiver_id;

        if (! $receiverId || (int) $receiverId === (int) $user->id) {
            return $this->fail('لا طرفَ آخر في هذا الإشعار لتردّ عليه.', 422);
        }

        $reply = notifications::create(array_merge([
            'user_id' => $user->id,
            'receiver_id' => $receiverId,
            'client_id' => $original->client_id,
            'title' => 'رد على: '.$original->title,
            'description' => $data['reply_message'],
            'type' => 'reply',
            'read' => 0,
            'is_sent' => true,
        ], notifications::supportsParentLink() ? ['parent_id' => $original->id] : []));

        $original->update(['read' => 1]);

        return $this->ok(['id' => (int) ($reply?->id ?? 0), 'reply_to' => (int) $original->id], 'أُرسل الردّ.', 201);
    }

    // ═══════════════════════ داخلي ═══════════════════════

    /**
     * ردود هذا الإشعار — بنتيجة `notifications::replyThreads()` نفسها، بلا
     * كلفتها حيث لا حاجة لها. تلك تبني سلاسل الجدول كلّه في كل نداء (ثوانٍ)
     * لأجل الردود القديمة التي سبقت عمود `parent_id` فلا رابط لها إلا عنوانها.
     *
     *   • إشعارٌ أحدث من أوّل ردٍّ مربوط: كلُّ ردوده مربوطة (كل مسارات الردّ
     *     تكتب `parent_id` منذئذ) — فتُقرأ بالعمود مباشرة.
     *   • إشعارٌ أقدم لا يحتمل ردّاً قديماً (لا ردَّ غير مربوط بعده يخصّ طرفيه
     *     أو عميله) — ردوده المربوطة وحدها.
     *   • وما عداه — الحساب الكامل نفسه، فلا تفترق النتيجة عن الموقع.
     */
    private function repliesOf(notifications $row)
    {
        if (! notifications::supportsParentLink()) {
            return $this->threadReplies($row);
        }

        $linked = fn () => notifications::with('user:id,name')->where('parent_id', $row->id)->orderBy('created_at')->get();

        $since = Cache::remember('integration.notifications.linked_replies_since', 3600,
            fn () => notifications::where('type', 'reply')->whereNotNull('parent_id')->min('created_at'));

        if ($since && $row->created_at && $row->created_at->gte($since)) {
            return $linked();
        }

        /* شرطُ «مجموعة المرشَّحين» في `replyThreads()` نفسُه — لا يُنسب ردٌّ قديم
           إلى إشعارٍ إلا من داخلها: ردٌّ يحمل عميلاً ⇐ إشعاراتُ عميله، وردٌّ بلا
           عميل ⇐ الإشعاراتُ بعنوانه أو بين طرفيه. فغيابُ أيّ ردٍّ كهذا يقطع بأن
           لا ردَّ قديماً للإشعار، بلا الحساب الكامل. */
        $titles = array_map(fn ($prefix) => $prefix.$row->title, notifications::REPLY_TITLE_PREFIXES);

        $legacyPossible = notifications::where('type', 'reply')->whereNull('parent_id')
            ->where('created_at', '>=', $row->created_at)
            ->where(fn ($q) => $q
                ->when($row->client_id, fn ($o) => $o->where('client_id', $row->client_id))
                ->orWhere(fn ($loose) => $loose->whereNull('client_id')->where(fn ($m) => $m
                    ->whereIn('title', $titles)
                    ->when($row->receiver_id, fn ($p) => $p
                        ->orWhere(fn ($a) => $a->where('user_id', $row->user_id)->where('receiver_id', $row->receiver_id))
                        ->orWhere(fn ($b) => $b->where('user_id', $row->receiver_id)->where('receiver_id', $row->user_id))))))
            ->exists();

        return $legacyPossible ? $this->threadReplies($row) : $linked();
    }

    private function threadReplies(notifications $row)
    {
        return notifications::with('user:id,name')
            ->whereIn('id', notifications::replyThreads()[$row->id] ?? [0])
            ->orderBy('created_at')->get();
    }

    private function unreadQuery($user): Builder
    {
        return notifications::where('read', 0)
            ->where('created_at', '>=', now()->subHours(24))
            ->visibleInLists()
            ->when(! hasPermission('notifications_view_system_notifications'), fn ($q) => $q->where(
                fn ($o) => $o->where('user_id', $user->id)->orWhere('receiver_id', $user->id)
            ));
    }

    private function present(notifications $n, int $userId): array
    {
        return [
            'id' => (int) $n->id,
            'title' => $n->title,
            'description' => $n->description,
            'type' => $n->type ?? 'general',
            'read' => (bool) $n->read,
            'is_sender' => (int) $n->user_id === $userId,
            'is_receiver' => (int) $n->receiver_id === $userId,
            'from' => $n->user ? ['id' => (int) $n->user->id, 'name' => $n->user->name] : null,
            'to' => $n->receiver ? ['id' => (int) $n->receiver->id, 'name' => $n->receiver->name] : null,
            'client' => $n->client ? ['id' => (int) $n->client->id, 'trade_name' => $n->client->trade_name] : null,
            'scheduled_at' => $n->is_scheduled && $n->scheduled_at ? $n->scheduled_at->toDateTimeString() : null,
            'created_at' => $n->created_at?->toDateTimeString(),
        ];
    }
}
