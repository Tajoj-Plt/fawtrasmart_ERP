<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * صفّ دائم لكل جلسة دخول — يُفتح عند الدخول ويُغلق عند الخروج.
 *
 * الفرق عن جدول `sessions`: ذاك حالةٌ آنية تديرها Laravel وتُمحى عند
 * الخروج، وهذا تاريخٌ لا يُمحى.
 */
class SessionLog extends Model
{
    protected $fillable = [
        'user_id', 'attempted_email', 'session_id', 'status', 'logout_reason',
        'login_at', 'logout_at', 'last_seen_at', 'duration_seconds',
        'ip_address', 'user_agent', 'browser', 'os', 'device_type',
        'guard', 'remembered',
    ];

    protected $casts = [
        'login_at' => 'datetime',
        'logout_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'duration_seconds' => 'integer',
        'remembered' => 'boolean',
    ];

    /** الحالات ودلالتها */
    public const STATUSES = [
        'active' => 'جلسة مفتوحة',
        'ended' => 'خرج',
        'expired' => 'انتهت المهلة',
        'failed' => 'محاولة فاشلة',
        'locked' => 'حظر مؤقّت',
    ];

    /** أسباب إغلاق الجلسة */
    public const REASONS = [
        'manual' => 'خروج بطلب المستخدم',
        'expired' => 'انقطاع أو انتهاء المهلة',
        'forced' => 'إنهاء من المسؤول',
        'other_device' => 'خروج من الأجهزة الأخرى',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ==================== الاستعلام ====================

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeSuccessful(Builder $query): Builder
    {
        return $query->whereIn('status', ['active', 'ended', 'expired']);
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->whereIn('status', ['failed', 'locked']);
    }

    // ==================== العرض ====================

    public function statusLabel(): string
    {
        return self::tr('statuses.'.$this->status, self::STATUSES[$this->status] ?? (string) $this->status);
    }

    public function reasonLabel(): ?string
    {
        if (! $this->logout_reason) {
            return null;
        }

        return self::tr('reasons.'.$this->logout_reason, self::REASONS[$this->logout_reason] ?? $this->logout_reason);
    }

    /** الترجمة إن وُجدت، وإلا النصّ العربي المكتوب في الثابت */
    protected static function tr(string $key, string $fallback): string
    {
        $full = 'settings.session_logs.'.$key;
        $translated = __($full);

        return is_string($translated) && $translated !== $full ? $translated : $fallback;
    }

    /** لون الشارة في الشاشة */
    public function statusTone(): string
    {
        return match ($this->status) {
            'active' => 'ok',
            'ended' => 'neutral',
            'expired' => 'wait',
            'failed', 'locked' => 'no',
            default => 'neutral',
        };
    }

    /**
     * المدّة مقروءة.
     *
     * الجلسة المفتوحة تُقاس حتى اللحظة، فالمدّة المعروضة تنمو مع بقائها —
     * عرض «—» لجلسة قائمة منذ ساعتين إخفاءٌ لمعلومة موجودة.
     */
    public function durationForHumans(): string
    {
        $seconds = $this->duration_seconds
            ?? ($this->status === 'active' && $this->login_at ? now()->diffInSeconds($this->login_at) : null);

        if ($seconds === null) {
            return '—';
        }

        $seconds = (int) abs($seconds);

        if ($seconds < 60) {
            return __('settings.session_logs.lengths.seconds', ['count' => $seconds]);
        }

        $minutes = intdiv($seconds, 60);

        if ($minutes < 60) {
            return __('settings.session_logs.lengths.minutes', ['count' => $minutes]);
        }

        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        if ($hours < 24) {
            return $rest === 0
                ? __('settings.session_logs.lengths.hours', ['count' => $hours])
                : __('settings.session_logs.lengths.hours_minutes', ['hours' => $hours, 'minutes' => $rest]);
        }

        return __('settings.session_logs.lengths.days', ['count' => intdiv($hours, 24)]);
    }

    /** اسم من حاول الدخول — المستخدم إن عُرف، وإلا البريد المكتوب */
    public function actorName(): string
    {
        return $this->user?->name
            ?: ($this->attempted_email ?: __('settings.session_logs.unknown_actor'));
    }

    // ==================== تحليل الجهاز ====================

    /**
     * يقرأ المتصفّح والنظام ونوع الجهاز من ترويسة العميل.
     *
     * التحليل هنا لا في المتحكّم: `AuthController` كان يحلّلها بنسخة
     * ثالثة من الشيفرة نفسها لتسجيل الأجهزة الموثوقة.
     *
     * @return array{browser: string, os: string, device_type: string}
     */
    public static function parseAgent(?string $agent): array
    {
        $agent = (string) $agent;

        // الترتيب مقصود: Edge وOpera يذكران Chrome في ترويستهما، وChrome
        // يذكر Safari — فالفحص من الأخصّ إلى الأعمّ
        $browser = match (true) {
            (bool) preg_match('/Edg[e\/]/i', $agent) => 'Edge',
            (bool) preg_match('/OPR\/|Opera/i', $agent) => 'Opera',
            (bool) preg_match('/SamsungBrowser/i', $agent) => 'Samsung Internet',
            (bool) preg_match('/Firefox|FxiOS/i', $agent) => 'Firefox',
            (bool) preg_match('/Chrome|CriOS/i', $agent) => 'Chrome',
            (bool) preg_match('/Safari/i', $agent) => 'Safari',
            (bool) preg_match('/curl|wget|python|okhttp|postman/i', $agent) => 'أداة برمجية',
            default => 'غير معروف',
        };

        $os = match (true) {
            (bool) preg_match('/Windows NT 10|Windows NT 11/i', $agent) => 'Windows 10/11',
            (bool) preg_match('/Windows/i', $agent) => 'Windows',
            (bool) preg_match('/iPhone|iPad|iPod/i', $agent) => 'iOS',
            (bool) preg_match('/Android/i', $agent) => 'Android',
            (bool) preg_match('/Mac OS X|Macintosh/i', $agent) => 'macOS',
            (bool) preg_match('/Linux/i', $agent) => 'Linux',
            default => 'غير معروف',
        };

        $device = match (true) {
            (bool) preg_match('/bot|crawler|spider|curl|wget|python|okhttp/i', $agent) => 'bot',
            (bool) preg_match('/iPad|Tablet/i', $agent) => 'tablet',
            (bool) preg_match('/Mobile|iPhone|Android/i', $agent) => 'mobile',
            default => 'desktop',
        };

        return ['browser' => $browser, 'os' => $os, 'device_type' => $device];
    }
}
