<?php

namespace App\Models;

use App\Services\NotificationDispatcher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class notifications extends Model
{
    /**
     * بادئات عنوان الرد قبل عمود `parent_id` — الرابط الوحيد المتاح
     * للردود القديمة بأصلها (انظر scopeAwaitingReply).
     */
    public const REPLY_TITLE_PREFIXES = ['رد على: ', 'رد عميل: '];

    protected $table = 'notifications';

    protected $fillable = [
        'user_id',
        'receiver_id',
        'parent_id', // الإشعار الذي جاء هذا الصفُّ رداً عليه
        'client_id', // إضافة حقل العميل
        'title',
        'description',
        'read',
        'popup_shown', // إضافة حقل العرض
        'type',
        // `message` و `data` ليسا عمودين في الجدول، لكن 28 موضعاً في الكود
        // يمرّرهما — تُحوَّل قيمهما في المحوّلات أدناه بدل رفضها
        'message',
        'data',
        'is_scheduled',
        'is_sent',
        'scheduled_at',
    ];

    protected $casts = [
        'read' => 'boolean',
        'is_scheduled' => 'boolean',
        'is_sent' => 'boolean',
        'scheduled_at' => 'datetime',
    ];

    /**
     * بوابة الإعدادات.
     *
     * الإشعارات تُنشَأ من 73 موضعاً في الكود بـ `notifications::create()`
     * مباشرة، فلا وجود لطبقة مركزية يمكن اعتراضها. ربط البوابة بأحداث
     * النموذج يجعل كل تلك المواضع تحترم إعدادات الإشعارات فوراً دون
     * تعديل أيٍّ منها — وهو ما يجعل هذا تحسيناً لا إعادة بناء.
     */
    protected static function booted(): void
    {
        static::creating(function (self $notification) {
            // إرجاع false يلغي الإدراج بلا استثناء؛ المواضع التي تستعمل
            // القيمة الراجعة تسجّل `->id` فقط، وقيمته null تمرّ بسلام
            if (! NotificationSetting::allows($notification->type)) {
                return false;
            }
        });

        static::created(function (self $notification) {
            // القنوات الإضافية (بريد/رسائل) معطّلة افتراضياً؛ لا شيء
            // يحدث هنا ما لم يفعّلها المسؤول من شاشة الإعدادات
            NotificationDispatcher::afterCreate($notification);
        });
    }

    /**
     * `message` تُكتب في `description`.
     *
     * جدول `notifications` لا يحوي عمود `message`، ومع ذلك تمرّره مواضع
     * كثيرة (زيارات العملاء، تحويل الفواتير، تبريرات الزيارة). النتيجة كانت
     * خطأ SQLSTATE[42S22] يبتلعه try/catch، فلا يصل الإشعار أصلاً. التحويل
     * هنا يُصلح تلك المواضع كلّها دون لمسها.
     */
    public function setMessageAttribute($value): void
    {
        if (($this->attributes['description'] ?? null) === null || $this->attributes['description'] === '') {
            $this->attributes['description'] = $value;
        }
    }

    public function getMessageAttribute()
    {
        return $this->attributes['description'] ?? null;
    }

    /** `data` كذلك بلا عمود — تُهمَل قيمتها بدل أن تُسقط الإدراج */
    public function setDataAttribute($value): void
    {
        // لا شيء: الحمولة السياقية غير مخزَّنة في هذا الجدول
    }

    public function getDataAttribute()
    {
        return null;
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    // علاقة مع العميل
    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    /** الإعداد المقابل لنوع هذا الإشعار — قد لا يكون مسجّلاً */
    public function setting()
    {
        return $this->belongsTo(NotificationSetting::class, 'type', 'key');
    }

    /**
     * اسمُ نوع الإشعار كما يُعرض للمستخدم.
     *
     * التسمية التي كتبها المدير في «إعدادات الإشعارات» أولاً، ثم ملفّ اللغة
     * `notification_types`، ثم المفتاح مقروءاً. جدولُ الإعدادات يملؤه
     * `NotificationSettingSeeder` وحده، فقاعدةٌ لم يُشغَّل عليها كانت تعرض
     * الأنواع بمفاتيحها الإنجليزية (`invoice_payment`) في الفلتر والجدول.
     */
    public static function typeLabel(?string $type): ?string
    {
        if ($type === null || $type === '') {
            return null;
        }

        $stored = trim((string) NotificationSetting::labelFor($type));

        // تسميةٌ تساوي المفتاح ليست تسمية — صفٌّ أُنشئ بلا اسم
        if ($stored !== '' && strcasecmp($stored, $type) !== 0) {
            return $stored;
        }

        $key = 'notification_types.'.$type;

        // لغةُ الواجهة أولاً، ثم العربية: هي لغة النظام الأساسية والملفُّ فيها كامل
        foreach ([null, 'ar'] as $locale) {
            $translated = __($key, [], $locale);

            if (is_string($translated) && $translated !== $key) {
                return $translated;
            }
        }

        return str_replace('_', ' ', $type);
    }

    /** هل هاجرت قاعدة البيانات إلى عمود `parent_id` بعد؟ (قواعدُ إنتاجيةٌ تسبق الهجرات) */
    protected static ?bool $hasParentColumn = null;

    public static function supportsParentLink(): bool
    {
        return static::$hasParentColumn ??= Schema::hasColumn('notifications', 'parent_id');
    }

    /**
     * الردود على هذا الإشعار — منذ إضافة عمود `parent_id`.
     */
    public function replies()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** الإشعار الذي جاء هذا الصفُّ رداً عليه (إن كان رداً) */
    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * الإشعارات الظاهرة في القوائم: غير المجدولة، والمجدولة التي حان وقتها
     * أو أُرسلت، والمجدولة من نوعٍ غير `general` (تظهر فوراً).
     *
     * منقولةٌ حرفياً من `NotificationsController::notifications()` كي يبقى
     * عدّادُ «إشعارات عملاء بلا رد» في لوحة العملاء مطابقاً للصفحة التي
     * يفتحها البند — لا رقماً يقود إلى قائمةٍ أقصر منه.
     */
    public function scopeVisibleInLists(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->where(function (Builder $subQ) {
                $subQ->where('is_scheduled', 0)->orWhereNull('is_scheduled');
            })
                ->orWhere(function (Builder $subQ) {
                    $subQ->where('is_scheduled', 1)
                        ->where(function (Builder $innerQ) {
                            $innerQ->where('is_sent', 1)->orWhere('scheduled_at', '<=', now());
                        });
                })
                ->orWhere(function (Builder $subQ) {
                    $subQ->where('is_scheduled', 1)
                        ->where(function (Builder $typeQ) {
                            $typeQ->where('type', '!=', 'general')->orWhereNull('type');
                        });
                });
        });
    }

    /**
     * إشعارات العملاء وحدها — الموجَّهة بخصوص عميلٍ محدَّد، دون الردود
     * نفسها (الرد صفٌّ في نفس الجدول من النوع `reply`).
     */
    public function scopeForClients(Builder $query): Builder
    {
        return $query->whereNotNull('client_id')
            ->where(function (Builder $q) {
                $q->whereNull('type')->orWhere('type', '!=', 'reply');
            });
    }

    /**
     * إشعارات لم يصل عليها ردٌّ بعد.
     *
     * الردُّ الحديث يحمل `parent_id` نحو أصله. أمّا ردود ما قبل ذلك العمود
     * فلا رابط لها إلّا العنوان: تُنشَأ بعنوان «رد على: {العنوان}» أو
     * «رد عميل: {العنوان}» لنفس العميل وبعد تاريخ الأصل — وهي القاعدة
     * الوحيدة المتاحة لهم، فبدونها تظهر كلُّ الإشعارات القديمة «بلا رد».
     */
    public function scopeAwaitingReply(Builder $query, bool $awaiting = true): Builder
    {
        // وإشعاراتُ «طلب تغيير الحالة» ردُّها قرارُ الاعتماد أو الرفض لا رسالة
        $answered = array_keys(static::replyThreads() + static::statusDecisionReplies());

        if ($awaiting) {
            return $answered === [] ? $query : $query->whereNotIn('notifications.id', $answered);
        }

        return $query->whereIn('notifications.id', $answered ?: [0]);
    }

    /** @var array{key: string, threads: array<int, array<int, int>>}|null */
    protected static ?array $threadCache = null;

    /**
     * سلاسلُ الردود: `[معرّف الإشعار الأصلي => [معرّفات ردوده بترتيب إنشائها]]`.
     *
     * المصدرُ الوحيد لسؤال «هل رُدّ على هذا الإشعار؟ وبماذا؟» — يقرؤه فلتر
     * «حالة الرد» في صفحة الإشعارات، وصفحةُ تفاصيل الإشعار، ولوحةُ العملاء.
     *
     * الردُّ الحديث يحمل `parent_id`. الردودُ القديمة بلا رابط، وأغلبُها
     * (١٧٩ من ٢٨٠) بلا `client_id` أيضاً — فكانت قاعدةُ «عنوانٌ + نفسُ العميل»
     * تُسقطها، ويبقى إشعارٌ رُدّ عليه «بلا رد». فالردُّ القديم يُنسب إلى
     * **أقرب إشعارٍ سبقه** عنوانُه هو عنوانُ الرد بلا «رد على: »/«رد عميل: »:
     *
     *   - لنفس العميل إن حمل الردُّ عميلاً؛
     *   - وإلّا بين الطرفين أنفسهما (مُرسِلُ الرد ومستقبِلُه هما مُرسِلُ الأصل
     *     ومستقبِلُه بأيّ ترتيب — الرد يعود للطرف الآخر)، أو إلى مُرسِل إشعارٍ
     *     عامٍّ بلا مستقبِل.
     *
     * «الأقرب» لا «كلُّ ما سبق»: العنوانُ نفسُه يُرسَل مراراً بين الطرفين
     * نفسيهما، وردٌّ واحد لا يجيب عنها كلِّها.
     *
     * @return array<int, array<int, int>>
     */
    public static function replyThreads(): array
    {
        $stamp = DB::table('notifications')->where('type', 'reply')->selectRaw('COUNT(*) as c, MAX(id) as m')->first();
        $key = ($stamp->c ?? 0).':'.($stamp->m ?? 0);

        if (static::$threadCache !== null && static::$threadCache['key'] === $key) {
            return static::$threadCache['threads'];
        }

        $hasParent = static::supportsParentLink();
        $columns = ['id', 'title', 'client_id', 'user_id', 'receiver_id', 'created_at'];

        $replies = DB::table('notifications')
            ->where('type', 'reply')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get($hasParent ? array_merge($columns, ['parent_id']) : $columns);

        $threads = [];
        $legacy = [];

        foreach ($replies as $reply) {
            if ($hasParent && $reply->parent_id) {
                $threads[(int) $reply->parent_id][] = (int) $reply->id;

                continue;
            }

            foreach (self::REPLY_TITLE_PREFIXES as $prefix) {
                if (str_starts_with((string) $reply->title, $prefix)) {
                    $legacy[] = [$reply, mb_substr((string) $reply->title, mb_strlen($prefix))];

                    break;
                }
            }
        }

        if ($legacy !== []) {
            /* المرشَّحون: كلُّ إشعارات عميل الرد (ردٌّ يحمل عميلاً)، أو ما يحمل
               العنوانَ نفسه (ردٌّ بلا عميل). والعنوان لا يُطابَق بالمساواة وحدها —
               راجع `replyTitleMatches()`: إشعارُ العميل يُحفظ مقصوصاً بـ«...»
               والردُّ يحمل النصَّ كاملاً، فكان ردٌّ واضحٌ على إشعارٍ واضح لا يُنسب
               إليه ويبقى الإشعار «بلا رد». */
            $clientIds = array_values(array_unique(array_filter(array_map(fn ($l) => $l[0]->client_id, $legacy))));
            $titles = array_values(array_unique(array_column($legacy, 1)));

            /* ردٌّ بلا عميل قد يحمل **نصَّ** الإشعار لا عنوانه («رد على: لاتمشي من
               عندة» على إشعار «صفي منة») — فيُجلب كذلك ما أُرسل بين الطرفين أنفسهما. */
            $looseReplies = array_filter(array_column($legacy, 0), fn ($r) => $r->client_id === null);
            $repliers = array_values(array_unique(array_map(fn ($r) => (int) $r->user_id, $looseReplies)));
            $addressees = array_values(array_unique(array_map(fn ($r) => (int) $r->receiver_id, $looseReplies)));

            $originals = DB::table('notifications')
                ->where(fn ($q) => $q->whereNull('type')->orWhere('type', '!=', 'reply'))
                ->where(function ($q) use ($clientIds, $titles, $repliers, $addressees) {
                    $q->whereIn('title', $titles);
                    if ($clientIds !== []) {
                        $q->orWhereIn('client_id', $clientIds);
                    }
                    if ($repliers !== []) {
                        $q->orWhere(fn ($p) => $p->whereIn('receiver_id', $repliers)->whereIn('user_id', $addressees));
                    }
                })
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->get(array_merge($columns, ['description']));

            $pair = function ($a, $b): string {
                $ids = [(int) $a, (int) $b];
                sort($ids);

                return implode('-', $ids);
            };

            $byClient = $originals->whereNotNull('client_id')->groupBy('client_id');
            $byTitle = $originals->groupBy('title');
            $byPeople = $originals->whereNotNull('receiver_id')->groupBy(fn ($o) => $pair($o->user_id, $o->receiver_id));

            // مفتاح الخيط (نطاقُه + عنوانُه) ⇐ وقتُ آخر ردٍّ عليه
            $lastReplyAt = [];

            foreach ($legacy as [$reply, $title]) {
                $pool = $reply->client_id !== null
                    ? $byClient->get($reply->client_id, collect())
                    : $byTitle->get($title, collect())
                        ->merge($byPeople->get($pair($reply->user_id, $reply->receiver_id), collect()))
                        ->unique('id')
                        ->sortBy([['created_at', 'desc'], ['id', 'desc']])
                        ->values();

                // الأحدثُ أوّلاً — فأوّلُ مطابِقٍ هو أقربُ إشعارٍ سبق الرد
                $matches = [];
                foreach ($pool as $original) {
                    $titleHit = self::replyTitleMatches($title, (string) $original->title);
                    if ((string) $original->created_at > (string) $reply->created_at
                        || ! ($titleHit || self::replyDescriptionMatches($title, (string) ($original->description ?? '')))) {
                        continue;
                    }

                    if ($reply->client_id === null) {
                        $samePeople = $original->receiver_id === null
                            ? (int) $reply->receiver_id === (int) $original->user_id
                            : $pair($original->user_id, $original->receiver_id) === $pair($reply->user_id, $reply->receiver_id);
                        if (! $samePeople) {
                            continue;
                        }
                    }

                    // من استُهدف بالإشعار أولى به: مستقبِلُه هو الرادّ
                    /* الترتيب: مطابقةُ العنوان أقوى من مطابقة النصّ (إشعارٌ نصُّه «صفى الفاتوره»
                       لا يسلب الردَّ من إشعارٍ عنوانُه «صفى الفاتوره» لأنه أُرسل بعده بدقائق)،
                       ثم من استُهدف بالإشعار، ثم الأقرب زمناً. */
                    $matches[] = [($titleHit ? 2 : 0) + ((int) $original->receiver_id === (int) $reply->user_id ? 1 : 0), $original];
                }

                if ($matches === []) {
                    continue;
                }

                $best = max(array_column($matches, 0));
                $candidates = array_values(array_filter($matches, fn ($m) => $m[0] === $best));
                $nearest = $candidates[0][1];

                /* الإشعارُ نفسُه يُرسَل مكرَّراً (مرّتين في دقائق)، فيجيب الردُّ عن
                   كلّ نسخةٍ أُرسلت خلال اليوم الذي سبق أقربَها ولم يُجَب عنها بعد —
                   لا عن أحدثها وحدها، وإلّا بقيت نسخةٌ «بلا رد» والرد أمامها. */
                $key = ($reply->client_id ?? 'u'.$pair($reply->user_id, $reply->receiver_id)).'|'.self::replyTitleKey((string) $nearest->title);
                $since = max($lastReplyAt[$key] ?? '', date('Y-m-d H:i:s', strtotime((string) $nearest->created_at) - 86400));

                foreach ($candidates as [, $original]) {
                    if ($original !== $nearest && ((string) $original->created_at <= $since
                        || self::replyTitleKey((string) $original->title) !== self::replyTitleKey((string) $nearest->title))) {
                        continue;
                    }
                    $threads[(int) $original->id][] = (int) $reply->id;
                }

                $lastReplyAt[$key] = (string) $reply->created_at;
            }
        }

        foreach ($threads as $id => $replyIds) {
            $replyIds = array_values(array_unique($replyIds));
            sort($replyIds);
            $threads[$id] = $replyIds;
        }

        static::$threadCache = ['key' => $key, 'threads' => $threads];

        return $threads;
    }

    /**
     * أيطابق عنوانُ الرد (بلا «رد على: ») عنوانَ الإشعار؟
     *
     * مساواةً بعد توحيد المسافات، أو **بادئةً** حين قُصّ أحدهما: الإشعار يُحفظ
     * عنوانُه مقصوصاً بـ«...» والردُّ بالنصّ كاملاً — أو يقصّ العمودُ (255) الردَّ
     * الأطول. البادئة لا تُقبل بلا قصّ: «شغل» ليس «شغل العميل من جديد».
     */
    private static function replyTitleMatches(string $replyTitle, string $originalTitle): bool
    {
        $reply = self::replyTitleKey($replyTitle);
        $original = self::replyTitleKey($originalTitle);

        if ($reply === '' || $original === '') {
            return false;
        }

        if ($reply === $original) {
            return true;
        }

        $clipped = fn (string $title) => preg_match('/(\.\.\.|…)\s*$/u', $title) === 1 || mb_strlen($title) >= 240;

        return ($clipped($originalTitle) && str_starts_with($reply, $original))
            || ($clipped($replyTitle) && str_starts_with($original, $reply));
    }

    /**
     * أيطابق عنوانُ الرد **نصَّ** الإشعار؟ نسخٌ سابقة من الشاشة بنت عنوان الرد من
     * نصّ الإشعار لا عنوانه: إشعارٌ عنوانُه «صور» ونصُّه «صور الاستاند حق العميل…»
     * جاء ردُّه «رد على: صور الاستاند حق العميل…».
     *
     * مساواةً، أو بادئةً من 12 حرفاً فأكثر (الرد يقتطع أوّل النص) — لا أقصر:
     * «رد على: اسحب» لا يُنسب إلى كل نصٍّ يبدأ بـ«اسحب».
     */
    private static function replyDescriptionMatches(string $replyTitle, string $description): bool
    {
        $reply = self::replyTitleKey($replyTitle);
        $text = self::replyTitleKey(strip_tags($description));

        return $reply !== '' && $text !== ''
            && ($reply === $text || (mb_strlen($reply) >= 12 && str_starts_with($text, $reply)));
    }

    /** العنوان موحَّد المسافات وبلا «...» في آخره — مفتاح المطابقة */
    private static function replyTitleKey(string $title): string
    {
        return trim((string) preg_replace('/(\.\.\.|…)\s*$/u', '', (string) preg_replace('/\s+/u', ' ', trim($title))));
    }

    /** إشعاراتٌ ردُّها قرارٌ على طلب تغيير حالة عميل — لا رسالةُ رد. */
    public const STATUS_REQUEST_TYPES = ['client_status_request', 'client_status_approved', 'client_status_rejected'];

    /** @var array{key: string, replies: array<int, array{text: string, by_id: ?int, by_name: ?string, at: ?string}>}|null */
    protected static ?array $decisionCache = null;

    /**
     * ردودُ طلبات تغيير الحالة: `[معرّف الإشعار => القرار]`.
     *
     * لا يُردّ على «طلب اعتماد حالة عميل» برسالة — ردُّه أن يَعتمِد المدير
     * الطلبَ أو يرفضه (`ClientController::approveStatusRequest/rejectStatusRequest`).
     * ولا رابط مخزَّن بين الإشعار والطلب؛ فيُنسب الإشعار إلى **أقرب طلبٍ** في
     * وقت إنشائه لنفس العميل ومن نفس الطالب (يُنشآن معاً في طلبٍ واحد):
     *
     *   - الطلبُ معلَّق ⇐ الإشعار بلا رد؛
     *   - معتمَدٌ أو مرفوض ⇐ الرد هو القرار، ومن اتخذه ومتى.
     *
     * وإشعارا «تم الاعتماد» و«تم الرفض» هما القرارُ نفسه يصل الطالب — فلا ينتظران ردّاً.
     *
     * @return array<int, array{text: string, by_id: ?int, by_name: ?string, at: ?string}>
     */
    public static function statusDecisionReplies(): array
    {
        if (! Schema::hasTable('client_status_requests')) {
            return [];
        }

        $notes = DB::table('notifications')->whereIn('type', self::STATUS_REQUEST_TYPES)->selectRaw('COUNT(*) as c, MAX(id) as m')->first();
        $requests = DB::table('client_status_requests')->selectRaw('COUNT(*) as c, MAX(id) as m, MAX(updated_at) as u')->first();
        $key = implode(':', [$notes->c ?? 0, $notes->m ?? 0, $requests->c ?? 0, $requests->m ?? 0, $requests->u ?? '']);

        if (static::$decisionCache !== null && static::$decisionCache['key'] === $key) {
            return static::$decisionCache['replies'];
        }

        $notifications = DB::table('notifications')
            ->whereIn('type', self::STATUS_REQUEST_TYPES)
            ->get(['id', 'type', 'user_id', 'receiver_id', 'client_id', 'created_at']);

        $requestNotes = $notifications->where('type', 'client_status_request');
        $allRequests = $requestNotes->isEmpty() ? collect() : DB::table('client_status_requests')
            ->whereIn('client_id', $requestNotes->pluck('client_id')->filter()->unique()->values())
            ->orderBy('id')
            ->get(['id', 'client_id', 'requested_by', 'old_status_id', 'new_status_id', 'status', 'approved_by', 'notes', 'created_at', 'updated_at']);
        $byPair = $allRequests->groupBy(fn ($r) => $r->client_id.'|'.$r->requested_by);
        // قراراتُ كل عميل بترتيبها — لطلبٍ بقي معلَّقاً ثم حُسمت حالة العميل بعده
        $decidedByClient = $allRequests->whereIn('status', ['approved', 'rejected'])->groupBy('client_id');

        /* إشعارُ القرار نفسُه («تم الاعتماد/الرفض») يصل الطالبَ لنفس العميل — دليلٌ
           لا يمرّ بجدول الطلبات، فيبقى الطلبُ مردوداً عليه وإن لم يُطابَق صفُّه. */
        $decisionNotes = $notifications->whereIn('type', ['client_status_approved', 'client_status_rejected'])
            ->groupBy(fn ($n) => $n->client_id.'|'.$n->receiver_id);

        $matched = [];
        $superseded = [];
        $byDecisionNote = [];
        foreach ($requestNotes as $note) {
            $at = strtotime((string) $note->created_at);
            $best = null;
            foreach ($byPair->get($note->client_id.'|'.$note->user_id, []) as $request) {
                $gap = abs(strtotime((string) $request->created_at) - $at);
                if ($best === null || $gap < $best[0]) {
                    $best = [$gap, $request];
                }
            }

            if ($best !== null && in_array($best[1]->status, ['approved', 'rejected'], true)) {
                $matched[(int) $note->id] = $best[1];

                continue;
            }

            /* بقي الطلب «معلَّقاً» لكن حُسمت حالة العميل بعده: نسخةٌ مكرَّرة منه
               اعتُمدت (ضغطةٌ مزدوجة تُنشئ طلبين أو ثلاثة)، أو غيّر المعتمِدُ الحالة
               بنفسه — فلا ينتظر هذا الطلبُ قراراً بعد. */
            $later = $best === null ? null : collect($decidedByClient->get($note->client_id, []))->first(fn ($r) => (int) $r->id > (int) $best[1]->id);
            if ($later) {
                $matched[(int) $note->id] = $later;
                $superseded[(int) $note->id] = true;

                continue;
            }

            $decision = collect($decisionNotes->get($note->client_id.'|'.$note->user_id, []))
                ->first(fn ($n) => strtotime((string) $n->created_at) >= $at);
            if ($decision) {
                $byDecisionNote[(int) $note->id] = $decision;
            }
        }

        $statusNames = DB::table('statuses')
            ->whereIn('id', collect($matched)->flatMap(fn ($r) => [$r->old_status_id, $r->new_status_id])->filter()->unique()->values())
            ->pluck('name', 'id');
        $userNames = DB::table('users')
            ->whereIn('id', collect($matched)->pluck('approved_by')->merge($notifications->whereIn('type', ['client_status_approved', 'client_status_rejected'])->pluck('user_id'))->filter()->unique()->values())
            ->pluck('name', 'id');

        $replies = [];
        foreach ($matched as $id => $request) {
            $text = $request->status === 'approved'
                ? 'تم اعتماد الطلب: من «'.($statusNames[$request->old_status_id] ?? '—').'» إلى «'.($statusNames[$request->new_status_id] ?? '—').'»'
                : 'تم رفض الطلب ('.($statusNames[$request->new_status_id] ?? '—').')';

            $replies[$id] = [
                'text' => (isset($superseded[$id]) ? 'حُسم بطلبٍ لاحق — ' : '').$text.($request->notes ? ' — '.$request->notes : ''),
                'by_id' => $request->approved_by ? (int) $request->approved_by : null,
                'by_name' => $userNames[$request->approved_by] ?? null,
                'at' => $request->updated_at ? date('Y-m-d H:i', strtotime((string) $request->updated_at)) : null,
            ];
        }

        foreach ($byDecisionNote as $id => $decision) {
            $replies[$id] = [
                'text' => $decision->type === 'client_status_approved' ? 'تم اعتماد الطلب' : 'تم رفض الطلب',
                'by_id' => $decision->user_id ? (int) $decision->user_id : null,
                'by_name' => $userNames[$decision->user_id] ?? null,
                'at' => $decision->created_at ? date('Y-m-d H:i', strtotime((string) $decision->created_at)) : null,
            ];
        }

        foreach ($notifications->whereIn('type', ['client_status_approved', 'client_status_rejected']) as $note) {
            $replies[(int) $note->id] = [
                'text' => $note->type === 'client_status_approved' ? 'قرار: تم اعتماد الطلب' : 'قرار: تم رفض الطلب',
                'by_id' => $note->user_id ? (int) $note->user_id : null,
                'by_name' => $userNames[$note->user_id] ?? null,
                'at' => $note->created_at ? date('Y-m-d H:i', strtotime((string) $note->created_at)) : null,
            ];
        }

        static::$decisionCache = ['key' => $key, 'replies' => $replies];

        return $replies;
    }
}
