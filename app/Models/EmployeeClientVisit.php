<?php

namespace App\Models;

use App\Enums\VisitStatus;
use App\Helpers\ClientSettingsHelper;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class EmployeeClientVisit extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        // من أضاف العميل من «خط سيري اليومي» — فارغٌ لصفوف الخطّة الأسبوعية
        'added_by',
        'client_id',
        'visit_date',
        // ساعةُ العودة المتوقَّعة — `visit_date` مصبوبٌ `date` فلا يحملها
        'planned_time',
        'justification',
        'day_of_week',
        'year',
        'status',
        'week_number',
        'justification_date',
        'justification_approved',
        'approved_by',
        'is_moved',
        'moved_from_day',
        'moved_from_week',
        'moved_from_year',
        'moved_at',
        'sequence',
        'visit_status',
        'started_at',
        'completed_at',
        'cancelled_at',
        'cancel_reason',
        'missed_reason',
        'actual_duration_minutes',
    ];

    protected $casts = [
        'visit_date' => 'date',
        'justification_date' => 'datetime',
        'is_moved' => 'boolean',
        'moved_at' => 'datetime',
        'visit_status' => VisitStatus::class,
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'actual_duration_minutes' => 'integer',
    ];

    /**
     * إعفاء العميل من فحص الموقع (`clients.exempt_visit_location` — يُفعَّل من
     * ملفّه، انظر `ClientController::toggleExemptLocation()`) لجولةٍ واحدة لا
     * إلى الأبد: أوّل زيارةٍ **جديدة** تُجدوَل له بعد التفعيل (صفٌّ جديد هنا،
     * أيّاً كان مصدره — خطّ سير يومي، إضافة جماعية، أو بدء زيارة فور إنشاء
     * العميل) تُسقط الإعفاء تلقائياً، فيعود الموظف يحتاج التحقّق من الموقع من
     * جديد ما لم يحمل صلاحية الدور العامّة `clients_visit_any_location`.
     * بلا هذا: تفعيلٌ واحدٌ يبقى نافذاً على كل زياراته القادمة إلى الأبد رغم
     * أنه أُريد لظرفٍ واحد. `created` لا `creating`: يضمن استقرار صفّ الزيارة
     * نفسه في القاعدة قبل تعديل صفّ العميل.
     */
    protected static function booted(): void
    {
        static::created(function (self $visit) {
            $client = $visit->client_id ? Client::find($visit->client_id) : null;

            if (! $client || ! $client->exempt_visit_location) {
                return;
            }

            $client->exempt_visit_location = false;
            $client->save();

            \App\Support\OperationLog::record(
                'exempt_location_toggled',
                'Clients',
                $client,
                'إلغاء استبعاد الموقع للعميل «'.$client->trade_name.'» تلقائياً — أُضيفت له زيارةٌ جديدة إلى خطّ السير'
            );
        });
    }

    // العلاقة مع الموظف
    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    // العلاقة مع العميل
    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id')->with(['locations', 'status_client']);
    }

    // الموظف الذي اعتمد التبرير
    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // نطاق للحالة (مثلاً عرض الزيارات النشطة فقط)
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    // نطاق لعرض الزيارات غير النشطة
    public function scopeInactive($query)
    {
        return $query->where('status', 'unactive');
    }

    // نطاق لعرض الزيارات غير المكتملة التي تحتاج تبرير
    public function scopeNeedsJustification($query)
    {
        return $query->where('status', '!=', 'active')
            ->where(function ($q) {
                $q->whereNull('justification')
                    ->orWhere('justification', '=', '')
                    ->orWhere('justification_approved', 0)
                    ->orWhereNull('justification_approved');
            })
            ->where(fn ($q) => static::excludePeriodCoveredDates($q));
    }

    /**
     * زيارات هذا الموظف التي تحتاج تبريراً نصّياً فعلاً — الأمس وما قبله،
     * بلا تبرير، **وغير مغطاةٍ بفترة تبرير معتمدة** (`VisitJustification`).
     *
     * **المصدر الوحيد لهذا الاستعلام.** كان مكرَّراً حرفياً في
     * `CheckIncompleteVisits` و`IncompleteVisitsController::showJustificationForm()`
     * بلا استثناء فترات التبرير — وهو الخلل الذي يُعيد فتح شهرٍ سبق
     * اعتماد إعفائه كاملاً بمجرد تغيّر صلاحية الموظف: الاستثناء هنا لا
     * يعتمد على أي صلاحيةٍ حالية، فقط على وجود سجلّ `VisitJustification`
     * معتمَدٍ يغطّي `visit_date`.
     */
    public static function needingJustificationQuery(int $employeeId)
    {
        $today = Carbon::now()->toDateString();

        return static::where('employee_id', $employeeId)
            ->where('status', 'unactive')
            ->where(function ($query) {
                $query->whereNull('justification')->orWhere('justification', '');
            })
            ->where(function ($query) use ($today) {
                $query->whereNotNull('visit_date')->where('visit_date', '<', $today)
                    ->orWhereNull('visit_date');
            })
            ->where(fn ($q) => static::excludePeriodCoveredDates($q));
    }

    /**
     * تبريراتٌ نصّيةٌ قُدِّمت (الأمس وما قبله) ولا تزال بانتظار اعتماد
     * الإدارة — نفس استثناء فترات التبرير المعتمدة: زيارةٌ غطّتها فترةٌ
     * معتمدة لاحقاً لا يجب أن تبقى «معلّقة» إلى الأبد بانتظار اعتمادٍ فرديّ
     * لن يأتي.
     */
    public static function pendingApprovalQuery(int $employeeId)
    {
        $today = Carbon::now()->toDateString();

        return static::where('employee_id', $employeeId)
            ->whereNotNull('justification')
            ->where('justification', '!=', '')
            ->where(function ($query) {
                $query->whereNull('justification_approved')->orWhere('justification_approved', 0);
            })
            ->where(function ($query) use ($today) {
                $query->whereNotNull('visit_date')->where('visit_date', '<', $today)
                    ->orWhereNull('visit_date');
            })
            ->where(fn ($q) => static::excludePeriodCoveredDates($q));
    }

    /** استثناء الزيارات التي تغطّيها فترة تبريرٍ معتمدة — راجع {@see VisitJustification::coversDate()}. */
    protected static function excludePeriodCoveredDates($query)
    {
        return $query->whereNotExists(function ($sub) {
            $sub->selectRaw('1')
                ->from('visit_justifications')
                ->whereColumn('visit_justifications.employee_id', 'employee_client_visits.employee_id')
                ->whereColumn('visit_justifications.client_id', 'employee_client_visits.client_id')
                ->where('visit_justifications.status', VisitJustification::STATUS_APPROVED)
                ->whereColumn('visit_justifications.date_from', '<=', 'employee_client_visits.visit_date')
                ->whereColumn('visit_justifications.date_to', '>=', 'employee_client_visits.visit_date');
        });
    }

    // ═══════════════════════ دورة حياة الزيارة ═══════════════════════

    public function scopePlanned($query)
    {
        return $query->where('visit_status', VisitStatus::PLANNED);
    }

    public function scopeStarted($query)
    {
        return $query->where('visit_status', VisitStatus::STARTED);
    }

    public function scopeCompleted($query)
    {
        return $query->where('visit_status', VisitStatus::COMPLETED);
    }

    public function scopeMissed($query)
    {
        return $query->where('visit_status', VisitStatus::MISSED);
    }

    public function scopeCancelled($query)
    {
        return $query->where('visit_status', VisitStatus::CANCELLED);
    }

    /** بدء الزيارة الآن. */
    public function startVisit(): void
    {
        $this->update([
            'visit_status' => VisitStatus::STARTED,
            'started_at' => now(),
        ]);
    }

    /**
     * إنهاء الزيارة الآن — تضبط أيضاً العمود القديم `status: active` عبر
     * نفس منطق {@see markCompletedFor()} كي تبقى نقطة الكتابة موحّدة ولا
     * تنكسر الأنظمة القارئة له (نقاط الأداء، شاشات الإنشاء المقيَّدة).
     */
    public function completeVisit(): void
    {
        $completedAt = now();

        $this->update([
            'status' => 'active',
            'visit_status' => VisitStatus::COMPLETED,
            'completed_at' => $completedAt,
            'actual_duration_minutes' => $this->started_at
                ? $this->started_at->diffInMinutes($completedAt)
                : null,
        ]);
    }

    /** إلغاء الزيارة بسبب. */
    public function cancelVisit(string $reason): void
    {
        $this->update([
            'visit_status' => VisitStatus::CANCELLED,
            'cancelled_at' => now(),
            'cancel_reason' => $reason,
        ]);
    }

    /** تعليم الزيارة فائتة. */
    public function markMissed(?string $reason = null): void
    {
        $this->update([
            'visit_status' => VisitStatus::MISSED,
            'missed_reason' => $reason,
        ]);
    }

    // هل التبرير معتمد؟
    public function isJustificationApproved()
    {
        return $this->justification_approved == 1;
    }

    // هل التبرير مرفوض؟
    public function isJustificationRejected()
    {
        return $this->justification_approved == 2;
    }

    // هل التبرير معلق (بانتظار الموافقة)؟
    public function isJustificationPending()
    {
        return $this->justification_approved == 0;
    }

    /**
     * معرّفات عملاء خط سير الموظف لتاريخٍ معيّن (اليوم افتراضاً).
     *
     * **المصدر الوحيد لهذا الاستعلام.** كان مكرَّراً بخمس صيغٍ مختلفة عبر
     * Sales وAccount وClient، بعضها يعيد حساب الأسبوع الميلادي يدوياً
     * بطريقةٍ تختلف عن `WeekHelper` — و`visit_date` عمودٌ مضبوطٌ فعلاً على
     * كل صفّ (تُبنى خطوط السير يدوياً من شاشتها فقط)، فالمقارنة عليه
     * مباشرةً أبسط وأوثق من مطابقة يوم الأسبوع ورقم الأسبوع والسنة معاً.
     */
    public static function clientIdsForDate(int $employeeId, ?Carbon $date = null): Collection
    {
        return static::where('employee_id', $employeeId)
            ->whereDate('visit_date', ($date ?: now())->toDateString())
            ->pluck('client_id');
    }

    /** أهذا اليوم يومُ خط سير هذا الموظف لهذا العميل؟ */
    public static function isItineraryDayFor(int $employeeId, int $clientId, ?Carbon $date = null): bool
    {
        return static::clientIdsForDate($employeeId, $date)->contains($clientId);
    }

    /**
     * معرّفات العملاء المسموح عرضهم في قوائم شاشات الإنشاء لهذا الموظف —
     * أو `null` فلا قيد (كل العملاء).
     *
     * القرار من إعداد «نطاق قوائم العملاء في شاشات الإنشاء» في «إعدادات
     * وحدات العملاء» ← خطوط السير (افتراضُه التقييد، وهو ما كانت خمس
     * شاشاتٍ تفرضه بلا إعداد) — **لا صلاحية**: كل موظفٍ يصل الشاشة
     * يخضع لنفس القيد بصرف النظر عن كتالوج صلاحياته.
     */
    public static function eligibleClientIdsForCreate(int $employeeId, ?Carbon $date = null): ?Collection
    {
        if (! ClientSettingsHelper::createScreensRestrictToItinerary()) {
            return null;
        }

        // عميلُ الزيارة الجارية يُضاف دائماً — راجع Visit::openWorkspaceClientIdsFor()
        return static::clientIdsForDate($employeeId, $date)
            ->map(fn ($id) => (int) $id)
            ->merge(Visit::openWorkspaceClientIdsFor($employeeId))
            ->unique()
            ->values();
    }

    /**
     * تعليم زيارة اليوم بين الموظف والعميل «مكتملة».
     *
     * **المصدر الوحيد لهذا التحديث.** كان يتكرّر في خمسة مواضع (ملاحظة
     * الزيارة، الملاحظة الرئيسية، الفاتورة، تحويل عرض السعر، السند) بلا
     * تقييدٍ بالتاريخ في أغلبها — فيُكمِل صفّ يومٍ آخر بالخطأ، أو يُكمِل
     * كل صفوف الموظف مع هذا العميل دفعةً واحدة. لا أثر إن لم توجد زيارةٌ
     * مجدولة اليوم أصلاً.
     */
    public static function markCompletedFor(int $employeeId, int $clientId, ?Carbon $date = null, ?int $durationMinutes = null): void
    {
        /* زيارة مساحة عملٍ مفتوحة ⇒ لا شيء: ملاحظتُها وفاتورتُها وسندُها
           كلّها تنادي هنا، وكانت تُكمل صفَّ اليوم في منتصف الزيارة — فيختفي
           العميل من خريطة المندوب ويُسجَّل وقتُ الإكمال وقتَ الملاحظة، ثمّ
           يجد `complete()` الصفَّ مكتملاً فلا يكتب المدّة. الإكمال هنا يأتي
           من «إنهاء الزيارة» وحده، وهي تُغلق الزيارة قبل أن تنادي. */
        if (Visit::hasOpenWorkspaceVisit($employeeId, $clientId, $date)) {
            return;
        }

        $update = [
            'status' => 'active',
            'visit_status' => VisitStatus::COMPLETED,
            'completed_at' => now(),
        ];

        // فقط حين تُمرَّر فعلاً: أغلب المنادين (فاتورة، سند، ملاحظة…) لا
        // يعرفون مدّةً أصلاً، وكتابة `null` عندهم تمسح مدّةً سجّلتها
        // مساحة عمل الزيارة قبل قليل.
        if ($durationMinutes !== null) {
            $update['actual_duration_minutes'] = max(0, $durationMinutes);
        }

        /* الصفوف المكتملة تُستثنى: عميلٌ زاره المندوب صباحاً ثمّ عاد إليه
           مساءً له صفّان لليوم نفسه، وتحديثُ الاثنين معاً يدوس مدّة الزيارة
           الأولى بمدّة الثانية فتضيع إحداهما. الزيارة المسائية تُكمِل صفَّها
           هي — الصفَّ الذي أنشأه «سأعود لاحقاً» وما زال `planned`. */
        static::where('employee_id', $employeeId)
            ->where('client_id', $clientId)
            ->whereDate('visit_date', ($date ?: now())->toDateString())
            ->where(function ($q) {
                $q->where('visit_status', '!=', VisitStatus::COMPLETED)
                    ->orWhereNull('visit_status');
            })
            ->update($update);
    }

    /**
     * عملاءُ هذا المندوب الذين **انتهى دورهم اليوم**: لهم صفوف خطّ سيرٍ
     * لليوم، وكلُّها مكتملة.
     *
     * يختفون من خريطته ومن خطّ سير يومه — «كأنّه خلّص زياراته». ويعودون
     * بمجرّد وجود صفٍّ واحدٍ غير مكتمل، وهو ما يصنعه «سأعود لاحقاً» عند
     * إنهاء الزيارة (`VisitWorkspaceController::complete()`).
     *
     * الإخفاء للمندوب وحده وفي هاتين الشاشتين وحدهما: العميل يبقى في فهرس
     * العملاء والبحث وملفّه — يحتاجه المندوب لمراجعة فاتورةٍ أو رصيد.
     */
    /**
     * عملاءُ هذا المندوب الذين **ما زال لهم صفُّ خطّ سيرٍ غير مكتملٍ اليوم**
     * — أي عملٌ باقٍ عليهم، ومنه العودةُ التي جدولها «سأعود لهذا العميل
     * اليوم».
     *
     * نقيضُ {@see finishedTodayClientIdsFor()} لكن ليس متمّمَه: عميلٌ بلا
     * أيّ صفٍّ اليوم لا يظهر في أيٍّ منهما.
     *
     * تقرؤه المواضع الثلاثة التي تقرّر «هل يمرّ المندوب بالزيارة قبل عرض
     * العميل؟» — `ClientController::show()`، وبطاقات الفهرس، ونوافذ
     * الخريطة. كان شرطها «هل تمّت زيارةٌ اليوم؟» وحده، فبعد إنهاء الزيارة
     * الأولى يُفتح ملفُّ العميل ولو كانت له عودةٌ مجدولة — والمطلوب زيارةٌ
     * ثانية لا تصفّحُ ملفّ.
     */
    public static function pendingItineraryClientIdsFor(int $userId): Collection
    {
        return static::where('employee_id', $userId)
            ->whereDate('visit_date', now()->toDateString())
            ->where(function ($q) {
                $q->where('visit_status', '!=', VisitStatus::COMPLETED)
                    ->orWhereNull('visit_status');
            })
            ->pluck('client_id')
            ->unique()
            ->values();
    }

    /**
     * من يُخفى فعلاً عن المندوب: {@see finishedTodayClientIdsFor()} متى كان
     * الإخفاء مفعَّلاً في إعدادات وحدة العملاء («العميل بعد إنهاء الزيارة»)،
     * ولا أحد متى أُطفئ.
     *
     * المواضع الثلاثة التي تُخفي (خريطة المندوب، وجدول الفهرس، وتحويل
     * `clients.show`) تقرأ هذه لا تلك: الأخرى تجيب «من انتهى دوره اليوم؟»
     * وهو سؤالٌ صحيحٌ بصرف النظر عن الإخفاء — قائمة «أُنهيت زياراتهم اليوم»
     * تحتاجه كما هو.
     */
    public static function hiddenFinishedClientIdsFor(int $userId): Collection
    {
        if (! \App\Helpers\ClientSettingsHelper::hideFinishedVisitClients()) {
            return collect();
        }

        return static::finishedTodayClientIdsFor($userId);
    }

    public static function finishedTodayClientIdsFor(int $userId): Collection
    {
        $today = now()->toDateString();

        $rows = static::where('employee_id', $userId)
            ->whereDate('visit_date', $today)
            ->get(['client_id', 'visit_status']);

        $isCompleted = fn ($row) => $row->visit_status === VisitStatus::COMPLETED->value
            || $row->visit_status === VisitStatus::COMPLETED;

        $byItinerary = $rows->groupBy('client_id')
            ->filter(fn ($group) => $group->every($isCompleted))
            ->keys();

        /* ومن أنهى زيارته من مساحة العمل بلا صفّ خطّ سيرٍ لليوم أصلاً (دخله
           من الخريطة أو الفهرس): `markCompletedFor()` لا يجد صفّاً يُكمله،
           فكان العميل يبقى ظاهراً بعد «إنهاء الزيارة». الزيارة المنتهية
           اليوم تكفي — ما لم يكن له صفٌّ غير مكتمل («سأعود لهذا العميل»). */
        $pending = $rows->reject($isCompleted)->pluck('client_id')->unique();

        $byWorkspace = Visit::where('employee_id', $userId)
            ->whereDate('visit_date', $today)
            ->where('stage', Visit::STAGE_COMPLETED)
            ->pluck('client_id')
            ->diff($pending);

        return $byItinerary->merge($byWorkspace)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    /**
     * تعليم زيارة اليوم بين الموظف والعميل «بدأت» — نظيرةُ
     * {@see markCompletedFor()} لأوّل طرف دورة الحياة.
     *
     * تكتبها مساحة عمل الزيارة (`VisitWorkspaceController::start()`) كي
     * يظهر وقت البدء والمدّة في سجلّ زيارات العميل: سجلّ خط السير هذا هو
     * ما تعرضه `clients.show`، بينما المؤقّت نفسه يعيش في `Visit`.
     * لا أثر إن لم توجد زيارةٌ مجدولة لهذا اليوم، ولا تدوس بدايةً مسجّلة
     * سابقاً (إعادة فتح الشاشة لا تُصفّر وقت البدء).
     */
    public static function markStartedFor(int $employeeId, int $clientId, ?Carbon $date = null): void
    {
        static::where('employee_id', $employeeId)
            ->where('client_id', $clientId)
            ->whereDate('visit_date', ($date ?: now())->toDateString())
            ->whereNull('started_at')
            ->update([
                'visit_status' => VisitStatus::STARTED,
                'started_at' => now(),
            ]);
    }

    /** المدّة الفعلية منسّقةً («1 س 25 د» / «45 د»)، أو `null` إن لم تُسجَّل. */
    public function getDurationLabelAttribute(): ?string
    {
        $minutes = $this->actual_duration_minutes;

        // سجلّاتٌ أقدم من عمود المدّة (أو أُكملت من فاتورة/سند لا من
        // مساحة عمل الزيارة) تحمل الطرفين بلا مدّةٍ محسوبة — نحسبها هنا
        // بدل إظهار «—» على زيارةٍ نعرف بدايتها ونهايتها.
        if ($minutes === null && $this->started_at && $this->completed_at) {
            $minutes = (int) $this->started_at->diffInMinutes($this->completed_at, true);
        }

        if ($minutes === null) {
            return null;
        }

        if ($minutes < 60) {
            return $minutes.' د';
        }

        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        return $rest ? $hours.' س '.$rest.' د' : $hours.' س';
    }
}
