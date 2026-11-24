<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * إعدادات الرسائل النصية — صفٌّ واحد يقرؤه `SmsGateway` قبل كل إرسال.
 *
 * كان الوصول إليها بـ `DB::table('sms_settings')->first()` من أربعة
 * مواضع، فكل موضع يفسّر القيم الغائبة بطريقته. هنا تفسير واحد: القيم
 * الافتراضية في `DEFAULTS`، ومعنى كل إعداد في تابعٍ يُسأل بدل أن يُقرأ
 * العمود خاماً في كل مكان.
 */
class SmsSetting extends Model
{
    protected $table = 'sms_settings';

    /** الصفّ محفوظ في الذاكرة طوال الطلب — لا داعي لقراءته مرّتين */
    protected static ?self $cached = null;

    protected static bool $resolved = false;

    protected $guarded = ['id'];

    protected $casts = [
        'is_active' => 'boolean',
        'allow_international' => 'boolean',
        'allowed_country_codes' => 'array',
        'strip_emoji' => 'boolean',
        'collapse_whitespace' => 'boolean',
        'append_signature' => 'boolean',
        'append_opt_out' => 'boolean',
        'quiet_hours_enabled' => 'boolean',
        'respect_working_days' => 'boolean',
        'working_days' => 'array',
        'retry_enabled' => 'boolean',
        'test_mode' => 'boolean',
        'whitelist_enabled' => 'boolean',
        'low_balance_alert' => 'boolean',
        'log_enabled' => 'boolean',
        'log_body' => 'boolean',
        'mask_recipient' => 'boolean',
        'notify_on_failure' => 'boolean',
        'cost_per_segment' => 'decimal:4',
    ];

    /**
     * القيم الافتراضية — تُستعمل حين لا يكون في الجدول صفّ بعد، وتُعرَض
     * في الشاشة كي لا يواجه المستخدم نموذجاً فارغاً.
     */
    public const DEFAULTS = [
        'provider' => 'msegat',
        'username' => '',
        'api_key' => '',
        'api_url' => null,
        'sender_name' => '',
        'fallback_sender_name' => null,
        'timeout_seconds' => 20,
        'is_active' => false,
        'default_country_code' => '966',
        'allow_international' => false,
        'allowed_country_codes' => [],
        'encoding_mode' => 'auto',
        'max_segments' => 4,
        'strip_emoji' => false,
        'collapse_whitespace' => true,
        'append_signature' => false,
        'signature_text' => null,
        'append_opt_out' => false,
        'opt_out_text' => null,
        'quiet_hours_enabled' => false,
        'quiet_hours_start' => '22:00',
        'quiet_hours_end' => '08:00',
        'quiet_hours_action' => 'defer',
        'respect_working_days' => false,
        'working_days' => [0, 1, 2, 3, 4],
        'timezone' => null,
        'rate_limit_per_minute' => 0,
        'daily_cap' => 0,
        'monthly_cap' => 0,
        'per_recipient_daily_cap' => 0,
        'duplicate_window_minutes' => 0,
        'retry_enabled' => false,
        'retry_attempts' => 2,
        'retry_delay_seconds' => 60,
        'retry_backoff' => 'exponential',
        'test_mode' => false,
        'test_redirect_number' => null,
        'blocked_numbers' => null,
        'whitelist_enabled' => false,
        'allowed_numbers' => null,
        'low_balance_alert' => false,
        'low_balance_threshold' => 100,
        'cost_per_segment' => 0,
        'cost_currency' => 'SAR',
        'log_enabled' => true,
        'log_body' => true,
        'mask_recipient' => false,
        'log_retention_days' => 0,
        'notify_on_failure' => false,
        'failure_threshold' => 5,
        'failure_notify_emails' => null,
    ];

    /**
     * المزوّدون المعروفون: الرمز ← [الاسم، نقطة الإرسال، نقطة الرصيد].
     *
     * العنوان هنا لا في `SmsGateway`: تغيير المزوّد إعدادٌ لا تعديل كود.
     */
    public const PROVIDERS = [
        'msegat' => [
            'label' => 'مسيقات (Msegat)',
            'send' => 'https://www.msegat.com/gw/sendsms.php',
            'balance' => 'https://www.msegat.com/gw/UserBalance.php',
        ],
        'taqnyat' => [
            'label' => 'تقنيات (Taqnyat)',
            'send' => 'https://api.taqnyat.sa/v1/messages',
            'balance' => 'https://api.taqnyat.sa/v1/account/balance',
        ],
        'unifonic' => [
            'label' => 'يونيفونك (Unifonic)',
            'send' => 'https://el.cloud.unifonic.com/rest/SMS/messages',
            'balance' => 'https://el.cloud.unifonic.com/rest/Account/GetBalance',
        ],
        'custom' => [
            'label' => 'بوابة مخصّصة',
            'send' => null,
            'balance' => null,
        ],
    ];

    public const ENCODING_MODES = ['auto', 'unicode', 'gsm'];

    public const QUIET_ACTIONS = ['defer', 'drop'];

    public const BACKOFF_MODES = ['fixed', 'exponential'];

    // ==================== الوصول ====================

    /**
     * الإعدادات الحالية — صفّ موجود، أو صفّ افتراضي غير محفوظ.
     *
     * لا يُنشئ صفّاً في قاعدة البيانات: قراءة الإعدادات تقع في كل طلب،
     * وكتابةُ صفّ عند القراءة تجعل أي زيارة تُحدث سجلاً.
     */
    public static function current(): self
    {
        if (! static::$resolved) {
            static::$cached = static::query()->orderBy('id')->first();
            static::$resolved = true;
        }

        return static::$cached ?? static::make(static::DEFAULTS);
    }

    /** يُنسى المحفوظ في الذاكرة — يُستدعى بعد الحفظ */
    public static function flush(): void
    {
        static::$cached = null;
        static::$resolved = false;
    }

    /** البوابة صالحة للإرسال فعلاً */
    public function isConfigured(): bool
    {
        return (bool) $this->is_active
            && filled($this->username)
            && filled($this->api_key)
            && filled($this->sender_name);
    }

    // ==================== تفسير الإعدادات ====================

    /** عنوان الإرسال: المخصّص إن كُتب، وإلا عنوان المزوّد */
    public function sendUrl(): ?string
    {
        return filled($this->api_url)
            ? $this->api_url
            : (self::PROVIDERS[$this->provider]['send'] ?? null);
    }

    public function balanceUrl(): ?string
    {
        return self::PROVIDERS[$this->provider]['balance'] ?? null;
    }

    public function providerLabel(): string
    {
        return self::PROVIDERS[$this->provider]['label'] ?? (string) $this->provider;
    }

    /** المنطقة الزمنية التي تُقاس بها أوقات الهدوء */
    public function zone(): string
    {
        return filled($this->timezone) ? $this->timezone : config('app.timezone', 'UTC');
    }

    public function now(): Carbon
    {
        return Carbon::now($this->zone());
    }

    /**
     * أوقتُ هدوءٍ الآن؟
     *
     * النافذة قد تعبر منتصف الليل (22:00 ← 08:00)، فالمقارنة حينها
     * «خارج النافذة المعكوسة» لا «بين الطرفين».
     */
    public function inQuietHours(?Carbon $at = null): bool
    {
        if (! $this->quiet_hours_enabled || blank($this->quiet_hours_start) || blank($this->quiet_hours_end)) {
            return false;
        }

        $at = $at ? $at->copy()->setTimezone($this->zone()) : $this->now();
        $minutes = $at->hour * 60 + $at->minute;

        $start = $this->minutesOf($this->quiet_hours_start);
        $end = $this->minutesOf($this->quiet_hours_end);

        if ($start === $end) {
            return false;
        }

        return $start < $end
            ? ($minutes >= $start && $minutes < $end)
            : ($minutes >= $start || $minutes < $end);
    }

    /** اليوم من أيام العمل المسموح الإرسال فيها */
    public function isWorkingDay(?Carbon $at = null): bool
    {
        if (! $this->respect_working_days) {
            return true;
        }

        $days = $this->working_days ?: [];

        if ($days === []) {
            return true;
        }

        $at = $at ? $at->copy()->setTimezone($this->zone()) : $this->now();

        return in_array((int) $at->dayOfWeek, array_map('intval', $days), true);
    }

    /**
     * أوّل لحظة يُسمح فيها بالإرسال ابتداءً من الآن.
     *
     * يتقدّم يوماً يوماً حتى يجد يوم عمل خارج ساعات الهدوء. الحدّ أسبوع:
     * إعدادٌ لا يسمح بأي وقت يجب أن يفشل صراحةً لا أن يدور بلا نهاية.
     */
    public function nextAllowedTime(?Carbon $from = null): ?Carbon
    {
        $at = ($from ? $from->copy()->setTimezone($this->zone()) : $this->now());

        for ($i = 0; $i < 8; $i++) {
            if (! $this->isWorkingDay($at)) {
                $at = $at->copy()->addDay()->startOfDay();

                continue;
            }

            if (! $this->inQuietHours($at)) {
                return $at;
            }

            // نهاية نافذة الهدوء اليوم، أو غداً إن كانت النافذة تعبر الليل
            $end = $this->minutesOf($this->quiet_hours_end);
            $candidate = $at->copy()->startOfDay()->addMinutes($end);

            $at = $candidate->lessThanOrEqualTo($at) ? $candidate->addDay() : $candidate;
        }

        return null;
    }

    /** الأرقام الممنوعة، موحَّدةَ الصيغة */
    public function blockedList(): array
    {
        return $this->numberList($this->blocked_numbers);
    }

    /** الأرقام المسموح بها حين تُفعَّل القائمة البيضاء */
    public function allowedList(): array
    {
        return $this->numberList($this->allowed_numbers);
    }

    /** عناوين البريد التي تُنبَّه عند تكرار الفشل */
    public function failureEmails(): array
    {
        return collect(preg_split('/[,;\s]+/', (string) $this->failure_notify_emails))
            ->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL))
            ->values()
            ->all();
    }

    /** كلفة رسالة بعدد أجزاء معلوم */
    public function costOf(int $segments): float
    {
        return round($segments * (float) $this->cost_per_segment, 4);
    }

    // ==================== الداخل ====================

    /** «22:30» أو «22:30:00» ← 1350 */
    protected function minutesOf(mixed $time): int
    {
        $parts = explode(':', (string) $time);

        return ((int) ($parts[0] ?? 0)) * 60 + ((int) ($parts[1] ?? 0));
    }

    /**
     * نصّ متعدّد الأسطر ← أرقام مجرّدة من غير الأرقام.
     *
     * تُترك كما كُتبت دون توحيد لمفتاح الدولة: المقارنة تقع على الرقم
     * الموحَّد وعلى آخر تسع خانات معاً، فيُطابَق الرقم كتبه المستخدم
     * محلياً أو دولياً.
     */
    protected function numberList(?string $raw): array
    {
        return collect(preg_split('/[\r\n,;]+/', (string) $raw))
            ->map(fn ($line) => preg_replace('/\D+/', '', (string) $line))
            ->filter(fn ($digits) => strlen((string) $digits) >= 7)
            ->unique()
            ->values()
            ->all();
    }
}
