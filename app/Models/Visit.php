<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Visit extends Model
{
    use HasFactory;

    public const STAGE_ARRIVED = 'arrived';

    public const STAGE_IN_PROGRESS = 'in_progress';

    public const STAGE_PAUSED = 'paused';

    public const STAGE_COMPLETED = 'completed';

    public const STAGE_CANCELLED = 'cancelled';

    protected $fillable = [
        'employee_id',
        'client_id',
        'visit_date',
        'status',
        'employee_latitude',
        'employee_longitude',
        'arrival_time',
        'departure_time',
        'notes',
        'visit_order',
        'client_latitude',
        'client_longitude',
        'distance',
        'recording_method',
        'is_approved',
        'approved_by',
        'visit_number',
        'visit_type',
        'purpose',
        'stage',
        'started_at',
        'paused_at',
        'total_paused_seconds',
        'gps_accuracy',
        'outcome',
        'outcome_notes',
        'cancel_reason',
        'client_signature_path',
        'employee_signature_path',
        'signed_contact_id',
    ];

    protected $casts = [
        'visit_date' => 'datetime',
        'arrival_time' => 'datetime',
        'departure_time' => 'datetime',
        'started_at' => 'datetime',
        'paused_at' => 'datetime',
        'is_approved' => 'boolean',
        'distance' => 'decimal:2',
        'gps_accuracy' => 'decimal:2',
        'employee_latitude' => 'decimal:8',
        'employee_longitude' => 'decimal:8',
        'client_latitude' => 'decimal:8',
        'client_longitude' => 'decimal:8',
        'total_paused_seconds' => 'integer',
    ];

    // علاقة كثير إلى واحد مع جدول الموظفين (employees)
    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    // علاقة كثير إلى واحد مع جدول العملاء (clients)
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    // علاقة كثير إلى واحد مع المسؤول الذي وافق على الزيارة
    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // scope للزيارات القريبة
    public function scopeNearby($query, $latitude, $longitude, $radius = 1000)
    {
        return $query->whereRaw('
            ST_Distance_Sphere(
                point(client_longitude, client_latitude),
                point(?, ?)
            ) <= ?
        ', [$longitude, $latitude, $radius]);
    }

    public function signedContact()
    {
        return $this->belongsTo(Contact::class, 'signed_contact_id');
    }

    public function assetInspections()
    {
        return $this->hasMany(VisitAssetInspection::class);
    }

    public function competitorObservations()
    {
        return $this->hasMany(VisitCompetitorObservation::class);
    }

    public function followUpTasks()
    {
        return $this->hasMany(VisitFollowUpTask::class);
    }

    /**
     * المدة الفعلية المنقضية بالثواني، بعد خصم مدة الإيقاف المؤقت.
     *
     * `diffInSeconds()` بلا `$absolute = true` صريحة ترجع فرقاً موقَّعاً في
     * Carbon 3 (كانت دائماً موجبة في Carbon 2) — فحين يسبق `$start` الوقتَ
     * الحالي (الحالة الطبيعية أثناء الزيارة) كانت الدالّة تعيد رقماً
     * سالباً، و`max(0, ...)` يقصّه إلى صفرٍ دائماً.
     *
     * **تحذيرٌ لمن يستدعي هذه الدالّة من قالب Blade**: صبُّ Eloquent
     * التلقائي لعمودَي `started_at`/`paused_at` يُعاد تفسيره من السلسلة
     * الخام في *كل* وصولٍ بحسب `date_default_timezone_get()` وقت ذلك
     * الوصول بعينه — و`LanguageSettings::apply()` (مُركِّبُ
     * `View::composer('*', …)` يعمل على كل تصييرِ صفحة) قد يبدّل تلك
     * المنطقة أثناء تصيير الصفحة نفسها. فاستدعاءُ هذه الخاصّية بتأخّرٍ
     * داخل Blade (بعد أن يكون مُركِّبٌ آخر بدّل المنطقة) يُخرج رقماً مختلفاً
     * عمّا يُخرجه نفسُ الاستدعاء في المتحكّم قبل التصيير — راجع
     * `VisitWorkspaceController::show()`: يحسبها هناك فور الجلب ويمرّرها
     * قيمةً جاهزة، لا يترك القالب يستدعيها بنفسه.
     */
    public function getElapsedSecondsAttribute(): int
    {
        // بلا `started_at` تحديداً (لا سقوطٍ إلى `arrival_time`): مساحة عمل
        // الزيارة تُبقي المؤقّت متوقّفاً حتى يُضغط «بدء الزيارة» فعلياً
        // (راجع تعليق `VisitWorkspaceController::show()` و`visit_workspace.blade.php`
        // «لم تبدأ الزيارة بعد: المؤقّت متوقّف»)، فزيارةٌ وصلت (`arrival_time`
        // مسجَّلة، عبر التتبّع التلقائي أو زيارةٍ مفتوحةٍ سابقاً) لكن لم تُبدَأ
        // بعد كانت تُظهر مدّةً منقضيةً منذ الوصول بدل الصفر — يبدو للموظف
        // كأنّ المؤقّت يحمل وقتاً من جلسةٍ سابقة.
        if (! $this->started_at) {
            return 0;
        }

        $start = $this->started_at;

        $end = $this->departure_time ?? now();
        $elapsed = $end->diffInSeconds($start, true) - $this->total_paused_seconds;

        if ($this->paused_at) {
            $elapsed -= $this->paused_at->diffInSeconds(now(), true);
        }

        return (int) max(0, $elapsed);
    }

    public function isPaused(): bool
    {
        return $this->stage === self::STAGE_PAUSED && $this->paused_at !== null;
    }

    public function isCompleted(): bool
    {
        return $this->stage === self::STAGE_COMPLETED || $this->departure_time !== null;
    }

    /**
     * زيارةٌ تُدار من مساحة عمل الزيارة: لا يُغلقها إلا «إنهاء الزيارة» أو
     * «إلغاؤها» من هناك (`VisitWorkspaceController::complete()`/`cancel()`).
     *
     * العلامة `recording_method = manual` (تضعها `open()` عند الإنشاء أو عند
     * تبنّي زيارةٍ سجّلها التتبّع) أو `started_at` (لا يكتبه إلا زرّ «بدء
     * الزيارة»). لا يصلح `stage` هنا: افتراضُ العمود `arrived`، فكل زيارةٍ
     * آليّةٍ تحمله أيضاً.
     */
    public function isWorkspaceManaged(): bool
    {
        return $this->recording_method === 'manual' || $this->started_at !== null;
    }

    /**
     * ما يجوز للانصراف الآلي أن يُغلقه — نقيضُ {@see isWorkspaceManaged()}.
     *
     * كان الانصراف التلقائي (مهلة ١٠ دقائق أو الابتعاد ١٠٠ متر، من نبض
     * الموقع في `master.blade.php` ومن مهمّة `ProcessAutoDepartures` ومن
     * «إخفاء من الخريطة») يكتب `departure_time` على زيارة مساحة العمل
     * المفتوحة، فتظهر «زيارة منتهية» والمندوب لم يُنهِها بعد.
     */
    public function scopeAutoDepartable($query)
    {
        return $query->whereNull('started_at')
            ->where(function ($q) {
                $q->whereNull('recording_method')->orWhere('recording_method', '!=', 'manual');
            });
    }

    /**
     * الزياراتُ المفتوحة فعلاً — ما تعرضه شاشة «الزيارات الجارية والمنسية».
     *
     * ثلاثةُ شروطٍ مجتمعة، وإسقاطُ أيّها يُفرغ الشاشة أو يُغرقها:
     *
     *   • **لم تُنهَ** (`departure_time` فارغ) و**لم تُلغَ** (`stage`). لا يصلح
     *     `status` هنا: عمودٌ من نوع `enum('present','absent')` — حضورُ
     *     الموظف لا حالةُ الزيارة — فشرطُ `status in ('active','started')`
     *     لا يطابق صفّاً واحداً أبداً، وهو سببُ خلوّ تبويب «الجارية» دائماً.
     *   • **زيارةُ مساحة عمل** ({@see isWorkspaceManaged()}): بدأها المندوب
     *     (`started_at`) أو أنشأتها مساحةُ العمل (`recording_method = manual`).
     *     بدونه يدخل الصفُّ التلقائيُّ القديم (`arrived`/`auto` بلا بدء،
     *     أكثرُ من سبعة آلاف صفٍّ بلا `departure_time`) فتُظهر الشاشةُ
     *     آلافَ «المنسيّات» التي ليست زياراتٍ أصلاً ولا يُنهيها زرّ.
     *
     * التقسيمُ بعدها إلى «جارية» و«منسية» بالقِدَم وحده، في المتحكّم.
     */
    public function scopeOpenWorkspaceVisit($query)
    {
        return $query->whereNull('departure_time')
            ->where(fn ($q) => $q->whereNull('stage')
                ->orWhereNotIn('stage', [self::STAGE_COMPLETED, self::STAGE_CANCELLED]))
            ->where(fn ($q) => $q->whereNotNull('started_at')
                ->orWhere('recording_method', 'manual'));
    }

    /**
     * عملاءُ زيارات مساحة العمل المفتوحة الآن لهذا المستخدم (اليوم).
     *
     * تُضمّ إلى نطاق قوائم العملاء في شاشات الإنشاء (`ClientVisibilityScope`
     * و`EmployeeClientVisit::eligibleClientIdsForCreate()`): مستنداتُ الزيارة
     * (فاتورة، سند قبض، إشعار دائن، تبديل) تُفتح من داخلها بـ`client_id`،
     * وكان العميل يغيب عن السيلكت متى لم يكن في خطّ سير اليوم أو عملاء
     * المندوب — فلا يُختار. الوصول إليه فُحص عند فتح الزيارة
     * (`VisitWorkspaceController::open()` ← `canAccessClient`).
     */
    public static function openWorkspaceClientIdsFor(int $userId): \Illuminate\Support\Collection
    {
        return static::where('employee_id', $userId)
            ->whereDate('visit_date', now()->toDateString())
            ->whereNull('departure_time')
            ->where(function ($q) {
                $q->where('recording_method', 'manual')->orWhereNotNull('started_at');
            })
            ->pluck('client_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    /** زيارة مساحة عملٍ مفتوحةٌ الآن بين هذا الموظف وهذا العميل في هذا اليوم؟ */
    public static function hasOpenWorkspaceVisit(int $employeeId, int $clientId, $date = null): bool
    {
        return static::where('employee_id', $employeeId)
            ->where('client_id', $clientId)
            ->whereDate('visit_date', ($date ?: now())->toDateString())
            ->whereNull('departure_time')
            ->where(function ($q) {
                $q->where('recording_method', 'manual')->orWhereNotNull('started_at');
            })
            ->exists();
    }

    /**
     * عملاءُ زياراتٍ **بدأها** المندوب اليوم ولم تُنهَ — «جارية» في العرض.
     *
     * غيرُ {@see openWorkspaceClientIdsFor()}: تلك تعدّ مساحةَ عملٍ فُتحت ولم
     * تبدأ (`stage = arrived` بلا `started_at`) مفتوحةً، وهو صحيحٌ للحراسة (يبقى
     * العميلُ متاحاً في شاشات الإنشاء، ولا يُكمَل صفُّ خطّ السير في منتصفها)،
     * وخطأٌ للعرض: كان الدخولُ إلى مساحة العمل يُظهر الزيارة «جارية» بلا «بدء الزيارة».
     */
    public static function startedWorkspaceClientIdsFor(int $userId): \Illuminate\Support\Collection
    {
        return static::where('employee_id', $userId)
            ->whereDate('visit_date', now()->toDateString())
            ->whereNotNull('started_at')
            ->whereNull('departure_time')
            ->where(fn ($q) => $q->whereNull('stage')->orWhere('stage', '!=', self::STAGE_CANCELLED))
            ->pluck('client_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    /**
     * زيارةٌ **وقعت فعلاً** — لعدّها زيارةً أو دليلَ حضورٍ في يوم خطّ السير:
     * بدأها المندوب (`started_at`)، أو يدويةٌ أُنهيت أو قديمةٌ سبقت مساحة العمل
     * (`stage` فارغ). **فتحُ مساحة العمل وحده** (`arrived` بلا بدء) ليس زيارة —
     * كان يُحسب دليلاً فيصير صفُّ اليوم «مكتملاً» بعد مضيّه ولم يزره أحد.
     * والتسجيلُ التلقائي بالموقع بلا بدءٍ خارجها كما كان.
     *
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $query
     */
    public static function constrainActualVisit($query)
    {
        return $query->where(fn ($q) => $q->whereNotNull('started_at')
            ->orWhere(fn ($manual) => $manual->where('recording_method', 'manual')
                ->where(fn ($stage) => $stage->whereNull('stage')->orWhere('stage', self::STAGE_COMPLETED))));
    }
}
