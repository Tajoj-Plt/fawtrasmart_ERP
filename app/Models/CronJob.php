<?php

namespace App\Models;

use App\Models\Concerns\HasTranslatedLabels;
use Carbon\Carbon;
use Cron\CronExpression;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Log;

/**
 * مهمة مجدولة يتحكّم بها المسؤول من الإعدادات.
 *
 * `cron_expression` هو مصدر الحقيقة عند التنفيذ دائماً؛ أما `schedule_type`
 * وأخواته فهي ما اختاره المستخدم في الواجهة، ويُشتَقّ منها التعبير عند
 * الحفظ. الاعتماد على التعبير وحده يجعل التنفيذ لا يعرف شيئاً عن الواجهة.
 */
class CronJob extends Model
{
    use HasTranslatedLabels;

    protected $table = 'cron_jobs';

    /**
     * الأعمدة التي يُسمح بتحديثها على مهمّة محميّة.
     *
     * لقطة التنفيذ فقط: المُنفِّذ يجب أن يكتب نتيجته وإلا لم تعمل المهمة
     * أصلاً. أمّا الجدولة والحالة والمعالج والمعاملات فمقفلة.
     */
    protected const PROTECTED_WRITABLE = [
        'last_run_at', 'last_finished_at', 'last_status', 'last_duration_ms',
        'last_error', 'next_run_at', 'success_count', 'failure_count',
        'consecutive_failures', 'updated_at',
    ];

    /**
     * حماية المهامّ المحميّة على مستوى النموذج لا الشاشة وحدها.
     *
     * **لماذا هنا؟** لأن الحماية في المتحكّم تحمي مساراً واحداً: يبقى
     * `CronJob::where(...)->update(['status' => 'disabled'])` من أي خدمة أو
     * أمر أو تِنكر نافذاً. ومزامنة الاشتراك إن أُوقفت عمل النظام بحدود
     * اشتراكٍ قديم لا يعرف أنه انتهى.
     *
     * فالمنع في `saving` و`deleting`: لا مسار يتخطّاه.
     */
    protected static function booted(): void
    {
        static::updating(function (self $job) {
            if (! $job->isProtected()) {
                return;
            }

            $changed = array_keys($job->getDirty());
            $forbidden = array_diff($changed, self::PROTECTED_WRITABLE);

            if ($forbidden !== []) {
                // القيم المرفوضة تُعاد إلى أصلها بدل رمي استثناء: المُنفِّذ
                // يحدّث اللقطة في نفس الحفظ، ورميُ استثناء يُفشل تنفيذاً سليماً
                foreach ($forbidden as $attribute) {
                    $job->setAttribute($attribute, $job->getOriginal($attribute));
                }

                Log::warning(
                    'محاولة تعديل مهمّة مجدولة محميّة: '.$job->key,
                    ['attributes' => $forbidden]
                );
            }
        });

        static::deleting(function (self $job) {
            if ($job->isProtected()) {
                Log::warning('محاولة حذف مهمّة مجدولة محميّة: '.$job->key);

                return false;
            }
        });
    }

    protected $fillable = [
        'key', 'name', 'description', 'type', 'handler', 'parameters',
        'schedule_type', 'cron_expression', 'schedule_time', 'schedule_day', 'timezone',
        'status', 'is_system', 'is_protected', 'prevent_overlap', 'timeout',
        'notify_on_failure', 'notify_on_success',
        'last_run_at', 'last_finished_at', 'last_status', 'last_duration_ms', 'last_error',
        'next_run_at', 'success_count', 'failure_count', 'consecutive_failures',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'parameters' => 'array',
        'is_system' => 'boolean',
        'is_protected' => 'boolean',
        'prevent_overlap' => 'boolean',
        'notify_on_failure' => 'boolean',
        'notify_on_success' => 'boolean',
        'last_run_at' => 'datetime',
        'last_finished_at' => 'datetime',
        'next_run_at' => 'datetime',
        'timeout' => 'integer',
        'success_count' => 'integer',
        'failure_count' => 'integer',
        'consecutive_failures' => 'integer',
        'last_duration_ms' => 'integer',
    ];

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_DISABLED = 'disabled';

    /** الحالات الثلاث. «موقوفة» تعليق مؤقّت، و«معطّلة» إيقاف إداري. */
    public const STATUSES = [
        self::STATUS_ACTIVE => ['label' => 'نشطة', 'color' => 'success', 'icon' => 'play-circle'],
        self::STATUS_PAUSED => ['label' => 'موقوفة مؤقتاً', 'color' => 'warning', 'icon' => 'pause-circle'],
        self::STATUS_DISABLED => ['label' => 'معطّلة', 'color' => 'secondary', 'icon' => 'slash'],
    ];

    /** أنواع المهام. النوع تصنيفيّ للعرض والتصفية، والتنفيذ يحدّده `handler`. */
    public const TYPES = [
        'command' => ['label' => 'أمر Artisan', 'icon' => 'terminal', 'color' => 'primary'],
        'job' => ['label' => 'مهمة Job', 'icon' => 'cpu', 'color' => 'info'],
        'backup' => ['label' => 'نسخ احتياطي', 'icon' => 'database', 'color' => 'dark'],
        'notification' => ['label' => 'إشعارات', 'icon' => 'bell', 'color' => 'warning'],
        'report' => ['label' => 'تقارير', 'icon' => 'file-text', 'color' => 'success'],
        'cleanup' => ['label' => 'تنظيف', 'icon' => 'trash-2', 'color' => 'danger'],
        'maintenance' => ['label' => 'صيانة قاعدة البيانات', 'icon' => 'tool', 'color' => 'secondary'],
        'custom' => ['label' => 'مهمة مخصّصة', 'icon' => 'settings', 'color' => 'info'],
    ];

    public const SCHEDULE_EVERY_MINUTE = 'every_minute';

    public const SCHEDULE_CRON = 'cron';

    /**
     * أنماط الجدولة الجاهزة.
     *
     * `expression` قالب: `{m}` الدقيقة و`{H}` الساعة و`{d}` اليوم، تُملأ من
     * `schedule_time` و`schedule_day`. القيمة null تعني تعبيراً حرّاً.
     */
    public const SCHEDULE_TYPES = [
        'every_minute' => ['label' => 'كل دقيقة', 'expression' => '* * * * *', 'needs_time' => false, 'needs_day' => false],
        'every_5_minutes' => ['label' => 'كل 5 دقائق', 'expression' => '*/5 * * * *', 'needs_time' => false, 'needs_day' => false],
        'every_10_minutes' => ['label' => 'كل 10 دقائق', 'expression' => '*/10 * * * *', 'needs_time' => false, 'needs_day' => false],
        'every_15_minutes' => ['label' => 'كل 15 دقيقة', 'expression' => '*/15 * * * *', 'needs_time' => false, 'needs_day' => false],
        'every_30_minutes' => ['label' => 'كل 30 دقيقة', 'expression' => '*/30 * * * *', 'needs_time' => false, 'needs_day' => false],
        'hourly' => ['label' => 'كل ساعة', 'expression' => '{m} * * * *', 'needs_time' => true, 'needs_day' => false],
        'daily' => ['label' => 'يومياً', 'expression' => '{m} {H} * * *', 'needs_time' => true, 'needs_day' => false],
        'weekly' => ['label' => 'أسبوعياً', 'expression' => '{m} {H} * * {d}', 'needs_time' => true, 'needs_day' => true],
        'monthly' => ['label' => 'شهرياً', 'expression' => '{m} {H} {d} * *', 'needs_time' => true, 'needs_day' => true],
        'cron' => ['label' => 'تعبير Cron مخصّص', 'expression' => null, 'needs_time' => false, 'needs_day' => false],
    ];

    /** أيام الأسبوع بترقيم cron: 0 = الأحد */
    public const WEEK_DAYS = [
        0 => 'الأحد', 1 => 'الإثنين', 2 => 'الثلاثاء', 3 => 'الأربعاء',
        4 => 'الخميس', 5 => 'الجمعة', 6 => 'السبت',
    ];

    /**
     * القوائم المعروضة في الشاشات، بتسمياتها المترجَمة.
     *
     * الثوابت أعلاه تبقى مرجع المفاتيح والأيقونات والتعابير — هذه الدوالّ
     * تستبدل التسمية وحدها.
     */
    public static function statuses(): array
    {
        return self::withLabels(self::STATUSES, 'cron.statuses.');
    }

    public static function types(): array
    {
        return self::withLabels(self::TYPES, 'cron.types.');
    }

    public static function scheduleTypes(): array
    {
        return self::withLabels(self::SCHEDULE_TYPES, 'cron.frequencies.');
    }

    /** أيام الأسبوع بترقيم cron — 0 = الأحد. */
    public static function weekDays(): array
    {
        $days = __('settings.cron.week_days');

        if (! is_array($days) || count($days) !== 7) {
            return self::WEEK_DAYS;
        }

        return array_combine(array_keys(self::WEEK_DAYS), array_values($days));
    }

    private static function withLabels(array $list, string $prefix): array
    {
        $out = [];

        foreach ($list as $key => $meta) {
            $out[$key] = ['label' => self::tr($prefix.$key, $meta['label'])] + $meta;
        }

        return $out;
    }

    // ==================== العلاقات ====================

    public function executions(): HasMany
    {
        return $this->hasMany(CronJobExecution::class);
    }

    public function backups(): HasMany
    {
        return $this->hasMany(Backup::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // ==================== النطاقات ====================

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * ما يُعرض في شاشة المهام — بلا المحميّة.
     *
     * المحميّة تُنفَّذ ولا تُدار: `CronJobRunner` يستعلم بلا هذا النطاق فتعمل،
     * والشاشة تستعلم به فلا تظهر. ومهمّة تظهر بأزرارٍ معطَّلة تدعو إلى
     * المحاولة وتوليد الأسئلة — وما لا يُدار لا يُعرض.
     */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->where('is_protected', false)->orWhereNull('is_protected');
        });
    }

    public function isProtected(): bool
    {
        return (bool) $this->is_protected;
    }

    // ==================== الحالة ====================

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isRunning(): bool
    {
        return $this->last_status === CronJobExecution::STATUS_RUNNING;
    }

    /** المهام النظامية تُعطَّل ولا تُحذف — حذفها يكسر وظيفة قائمة في النظام. */
    public function isDeletable(): bool
    {
        return ! $this->is_system && ! $this->is_protected;
    }

    /** المحميّة لا تُعدَّل بحال — ولا تُوقَف. */
    public function isEditable(): bool
    {
        return ! $this->is_protected;
    }

    public function statusLabel(): string
    {
        return isset(self::STATUSES[$this->status])
            ? self::tr('cron.statuses.'.$this->status, self::STATUSES[$this->status]['label'])
            : $this->status;
    }

    public function statusColor(): string
    {
        return self::STATUSES[$this->status]['color'] ?? 'secondary';
    }

    public function typeLabel(): string
    {
        return isset(self::TYPES[$this->type])
            ? self::tr('cron.types.'.$this->type, self::TYPES[$this->type]['label'])
            : $this->type;
    }

    public function typeIcon(): string
    {
        return self::TYPES[$this->type]['icon'] ?? 'settings';
    }

    /**
     * هل حان وقت التشغيل؟
     *
     * `CronExpression` قد يرمي عند تعبير تالف — والتعبير التالف يجب أن
     * يُسكِت مهمّته وحدها لا أن يوقف دورة المجدول كلّها.
     */
    public function isDue(?\DateTimeInterface $now = null): bool
    {
        if (! $this->isActive()) {
            return false;
        }

        try {
            return (new CronExpression($this->cron_expression))
                ->isDue($now ?? now(), $this->timezone ?: config('app.timezone'));
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * أمستحقّةٌ الآن — بالموعد أو بالتعويض عن فوات؟
     *
     * هذه ما يسأله المجدول، لا {@see isDue()}: تلك تصدُق في **دقيقة** الموعد
     * وحدها، ودورةُ المجدول تأتي من زيارةٍ لا من ساعةٍ مضمونة (انظر
     * `config/cron.php`). فمهمّةُ 06:00 كانت تفوت اليومَ كلَّه إن لم يزر
     * أحدٌ في تلك الدقيقة — وقد قِيس ذلك: ثلاثةَ عشرَ من سبعٍ وعشرين مهمّةً
     * نشطةً فوَّتت موعدَها الأخير، ومهمّتان أسبوعيّتان لم تعملا قطّ.
     *
     * فالقاعدة: مستحقّةٌ إن كان آخرُ موعدٍ لها بعد آخر تشغيلٍ فعليّ. وذلك
     * يُعوِّض **مرّةً واحدة** لا مرّةً لكلّ موعدٍ فائت: المهامُّ هنا كلُّها
     * «عالِج ما حان» لا «نفّذ خطوةً»، فتشغيلةٌ واحدةٌ تُدرك ما تراكم.
     *
     * ودقيقةُ التسامح: آخرُ موعدٍ يُعدّ مُنفَّذاً إن كان التشغيلُ في دقيقته
     * نفسِها — فلا تُعاد المهمّةُ في الدورة التالية من الدقيقة ذاتها.
     */
    public function isPending(?\DateTimeInterface $now = null): bool
    {
        if (! $this->isActive()) {
            return false;
        }

        $now = $now ? Carbon::instance(Carbon::parse($now)) : now();

        if (! config('cron.catchup', true)) {
            return $this->isDue($now);
        }

        $previous = $this->previousRunDate($now);

        if (! $previous) {
            // تعبيرٌ تالف — الموعدُ لا يُعرَف، فلا يُخترَع
            return false;
        }

        // سقفُ القِدَم: فائتٌ أقدمُ منه لا يُعوَّض، فلا يُشعل عملاً عن ماضٍ بعيد
        $maxHours = (int) config('cron.catchup_max_hours', 48);
        if ($maxHours > 0 && $previous->lt($now->copy()->subHours($maxHours))) {
            return $this->isDue($now);
        }

        if (! $this->last_run_at) {
            return true;
        }

        return $previous->gt(Carbon::parse($this->last_run_at)->startOfMinute());
    }

    /**
     * آخرُ موعدٍ كان يجب أن تعمل فيه (مُحتسَباً، لا مقروءاً من عمود).
     *
     * `allowCurrentDate = true` كي تُحتسب الدقيقةُ الحالية موعداً إن كانت
     * كذلك — فمهمّةُ هذه الدقيقة لا تُؤجَّل إلى الدورة التالية.
     */
    public function previousRunDate(?\DateTimeInterface $now = null): ?Carbon
    {
        try {
            return Carbon::instance((new CronExpression($this->cron_expression))
                ->getPreviousRunDate($now ?? now(), 0, true, $this->timezone ?: config('app.timezone')));
        } catch (\Throwable) {
            return null;
        }
    }

    /** موعد التشغيل التالي، أو null إن كان التعبير غير صالح. */
    public function nextRunDate(): ?\DateTimeInterface
    {
        try {
            return (new CronExpression($this->cron_expression))
                ->getNextRunDate('now', 0, false, $this->timezone ?: config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }
    }

    /** مفتاح القفل الذي يمنع تداخل تشغيلَين للمهمة نفسها. */
    public function lockKey(): string
    {
        return 'cron_job_lock:'.$this->id;
    }
}
