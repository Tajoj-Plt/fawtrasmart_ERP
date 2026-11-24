<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * دخولُ موظفٍ إلى ملف عميل (`clients.show`) — صفٌّ لكل دخول.
 *
 * كان الفتحُ خلال عشر دقائق من آخر دخولٍ يُدمج في الصفّ نفسه، فمن دخل
 * وخرج ورجع لم يُسجَّل رجوعُه («ما سجّل الدخول الثاني»). السؤالُ الذي يُجيب
 * عنه السجلّ هو «متى وصل» — وكلُّ وصولٍ جوابٌ مستقل.
 */
class ClientProfileView extends Model
{
    /** حدُّ ما يُعرض في تبويب «سجل الدخول» — الأحدث أولاً. */
    public const TAB_LIMIT = 300;

    private const ORDINALS = [
        1 => 'الأول', 'الثاني', 'الثالث', 'الرابع', 'الخامس', 'السادس', 'السابع', 'الثامن', 'التاسع', 'العاشر',
        'الحادي عشر', 'الثاني عشر', 'الثالث عشر', 'الرابع عشر', 'الخامس عشر', 'السادس عشر', 'السابع عشر',
        'الثامن عشر', 'التاسع عشر', 'العشرون',
    ];

    protected $fillable = [
        'client_id',
        'user_id',
        'viewed_at',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'client_id' => 'int',
        'user_id' => 'int',
        'viewed_at' => 'datetime',
        // أعمدةُ ملخّص summaryFor()
        'last_viewed_at' => 'datetime',
        'first_viewed_at' => 'datetime',
        'views_count' => 'int',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * تسجيلُ وصولٍ إلى ملف العميل. لا يرمي شيئاً: فشلُ التسجيل لا يجوز أن
     * يُسقط فتحَ الملف نفسه.
     */
    public static function record(int $clientId, ?int $userId): void
    {
        if (! $userId) {
            return;
        }

        try {
            static::create([
                'client_id' => $clientId,
                'user_id' => $userId,
                'viewed_at' => now(),
                'ip_address' => request()->ip(),
                'user_agent' => mb_substr((string) request()->userAgent(), 0, 255),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * صفٌّ لكل موظف: آخرُ دخول، وأوّلُه، وعددُ مرات الدخول — الأحدث أولاً.
     */
    public static function summaryFor(int $clientId): Collection
    {
        return static::query()
            ->where('client_id', $clientId)
            ->selectRaw('user_id, MAX(viewed_at) as last_viewed_at, MIN(viewed_at) as first_viewed_at, COUNT(*) as views_count')
            ->groupBy('user_id')
            ->orderByDesc('last_viewed_at')
            ->with('user.branch')
            ->get();
    }

    /**
     * كلُّ دخولٍ بتفصيله، الأحدث أولاً، ومع كلٍّ منها:
     *   `ordinal`            — ترتيبُه بين دخولات الموظف نفسه (1 = الأول)
     *   `user_total`         — عددُ دخولات الموظف كلِّها
     *   `previous_viewed_at` — دخولُه السابق، إن كان ضمن ما قُرئ
     *
     * الترتيبُ من العدد الكلّي لا من المقروء: ما يُقرأ آخرُ {@see self::TAB_LIMIT}
     * دخولٍ متّصلة، فالدخولُ الأحدث للموظف ترتيبُه = عددُه الكلّي، وما قبله
     * ينقص واحداً — بلا دوالّ نوافذ لا تعرفها كلُّ قاعدة.
     */
    public static function entriesFor(int $clientId, int $limit = self::TAB_LIMIT): Collection
    {
        $totals = static::where('client_id', $clientId)
            ->selectRaw('user_id, COUNT(*) as total')
            ->groupBy('user_id')
            ->pluck('total', 'user_id');

        $rows = static::where('client_id', $clientId)
            ->with('user.branch')
            ->orderByDesc('viewed_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $byUser = $rows->groupBy('user_id')->map->values();
        $position = [];

        foreach ($rows as $row) {
            $uid = $row->user_id;
            $index = $position[$uid] = ($position[$uid] ?? -1) + 1; // 0 = الأحدث
            $total = (int) ($totals[$uid] ?? 0);

            $row->setAttribute('user_total', $total);
            $row->setAttribute('ordinal', $total - $index);
            $row->setAttribute('previous_viewed_at', $byUser[$uid]->get($index + 1)?->viewed_at);
        }

        return $rows;
    }

    /** «الأول»، «الثاني»… ثم «رقم 21». */
    public static function ordinalLabel(int $n): string
    {
        return self::ORDINALS[$n] ?? ('رقم '.$n);
    }

    /** المدّةُ منذ الدخول السابق لنفس الموظف («ساعتين 5 دقائق») أو null. */
    public function gapLabel(): ?string
    {
        $previous = $this->getAttribute('previous_viewed_at');

        if (! $previous || ! $this->viewed_at) {
            return null;
        }

        // «ساعة و35 دقيقة» لا «30 دقيقة ثانية»: الثواني لا تُذكر إلا فيما دون الدقيقة
        return $this->viewed_at->diffForHumans($previous, [
            'syntax' => CarbonInterface::DIFF_ABSOLUTE,
            'parts' => 2,
            'join' => ' و',
            'minimumUnit' => $this->viewed_at->diffInSeconds($previous, true) < 60 ? 'second' : 'minute',
        ]);
    }

    /** «جوال · Chrome» من بصمة المتصفح. */
    public function getDeviceLabelAttribute(): string
    {
        $ua = (string) $this->user_agent;

        if ($ua === '') {
            return 'غير معروف';
        }

        $device = preg_match('/ipad|tablet/i', $ua) ? 'جهاز لوحي'
            : (preg_match('/mobile|android|iphone/i', $ua) ? 'جوال' : 'كمبيوتر');

        $browser = match (true) {
            str_contains($ua, 'Edg') => 'Edge',
            str_contains($ua, 'OPR') || str_contains($ua, 'Opera') => 'Opera',
            str_contains($ua, 'Firefox') => 'Firefox',
            str_contains($ua, 'Chrome') => 'Chrome',
            str_contains($ua, 'Safari') => 'Safari',
            default => '',
        };

        return $browser !== '' ? $device.' · '.$browser : $device;
    }
}
