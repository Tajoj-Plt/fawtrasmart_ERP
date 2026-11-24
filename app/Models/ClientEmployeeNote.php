<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientEmployeeNote extends Model
{
    /**
     * «دوام كامل» — المسؤول موجودٌ على مدار الساعة (محلٌّ لا يغلق، أو مالكٌ
     * يُجاب في أيّ وقت). قيمةُ القائمة `full`، وتُخزَّن `00:00 → 24:00` في
     * العمودين نفسيهما فلا يحتاج الجدول عموداً ثالثاً ولا يعود `hasShift()`
     * كاذباً. و`24:00` لا يساوي `00:00` فلا تسقطه قاعدة «البداية ≠ النهاية».
     */
    public const FULL_TIME_VALUE = 'full';

    public const FULL_TIME_START = '00:00';

    public const FULL_TIME_END = '24:00';

    public const FULL_TIME_LABEL = 'دوام كامل (24 ساعة)';

    protected $fillable = [
        'client_id',
        'employee_id',
        'employee_name',
        'employee_position',
        'employee_phone',
        // دوام المسؤول — يُسجَّل مرّةً على المسؤول نفسه ويُقرأ في كل زيارة
        // (خطوةٌ إلزاميّة في مساحة عمل الزيارة)، وتغييرُه بعد تسجيله يحتاج
        // صلاحية `clients_change_contact_shift`
        'shift_start',
        'shift_end',
        'is_primary',
        'invoice_id',
        'note',
        'image',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    /** هل سُجِّل دوام هذا المسؤول؟ شرطُ الخطوة الثانية في مساحة عمل الزيارة. */
    public function hasShift(): bool
    {
        return ! empty($this->shift_start) && ! empty($this->shift_end);
    }

    /** «من 8 صباحاً إلى 8 مساءً» — نفس صياغة `ClientNoteController::addnotes()`. */
    public function getShiftLabelAttribute(): ?string
    {
        if (! $this->hasShift()) {
            return null;
        }

        if ($this->isFullTime()) {
            return self::FULL_TIME_LABEL;
        }

        return 'من '.self::formatShiftTime($this->shift_start).' إلى '.self::formatShiftTime($this->shift_end);
    }

    /**
     * ساعات اليوم الأربع والعشرون — تملأ قائمتَي «من» و«إلى» في كل مكانٍ
     * يُسجَّل فيه دوام المسؤول (مودال إضافة موظف العميل في شاشة العميل
     * وفي مساحة عمل الزيارة، ومودال تغيير الدوام).
     *
     * كانت القائمة قبلها **أزواجاً جاهزة** (فتراتٍ ثابتة من ١٢ ساعة أو
     * ٢٤): مسؤولٌ يداوم من ٩ صباحاً إلى ٣ عصراً لم يكن له خيارٌ يصفه، فيُختار
     * له أقربُ زوجٍ خاطئ. صارت ساعةً ساعة، والمستخدم يركّب المدى بنفسه.
     *
     * @return array<string, string> «HH:00» => التسمية العربية
     */
    public static function shiftHourOptions(): array
    {
        $options = [];

        for ($h = 0; $h < 24; $h++) {
            $label = self::formatShiftTime(str_pad((string) $h, 2, '0', STR_PAD_LEFT).':00');

            if ($h === 0) {
                $label .= ' (منتصف الليل)';
            } elseif ($h === 12) {
                $label .= ' (الظهر)';
            }

            $options[str_pad((string) $h, 2, '0', STR_PAD_LEFT).':00'] = $label;
        }

        return $options;
    }

    /**
     * يفكّ قيمة قائمة الدوام الواحدة («HH:MM|HH:MM») إلى بدايةٍ ونهاية.
     *
     * الحقل في الشاشة قائمةٌ واحدة «من – إلى» (راجع
     * `client::partials.shift-select`)، فالتحقّق من صلاحيّة طرفيها يقع هنا
     * مرّةً واحدة بدل تكراره في كل متحكّمٍ يستقبلها.
     *
     * @return array{0: string, 1: string}|null null = قيمةٌ غير صالحة
     */
    public static function parseShiftRange(?string $value): ?array
    {
        if (! is_string($value)) {
            return null;
        }

        // «دوام كامل» من القائمة، أو قيمته المخزَّنة إن أُعيدت كما هي
        if ($value === self::FULL_TIME_VALUE || $value === self::FULL_TIME_START.'|'.self::FULL_TIME_END) {
            return [self::FULL_TIME_START, self::FULL_TIME_END];
        }

        if (! str_contains($value, '|')) {
            return null;
        }

        [$start, $end] = explode('|', $value, 2);
        $hours = array_keys(self::shiftHourOptions());

        // البداية = النهاية مدىً بلا طول، لا دواماً
        if (! in_array($start, $hours, true) || ! in_array($end, $hours, true) || $start === $end) {
            return null;
        }

        return [$start, $end];
    }

    /** القيمة التي تختارها القائمة لهذا المسؤول، أو فارغة إن لا دوام له. */
    public function getShiftRangeValueAttribute(): string
    {
        if (! $this->hasShift()) {
            return '';
        }

        return $this->isFullTime() ? self::FULL_TIME_VALUE : $this->shift_start.'|'.$this->shift_end;
    }

    /** هل دوام هذا المسؤول «دوام كامل» (على مدار الساعة)؟ */
    public function isFullTime(): bool
    {
        return self::isFullTimeRange($this->shift_start, $this->shift_end);
    }

    /** الزوج المخزَّن لـ«دوام كامل» — يقرؤه من يبني وصف الدوام من الطلب لا من الصفّ. */
    public static function isFullTimeRange(?string $start, ?string $end): bool
    {
        return $start === self::FULL_TIME_START && $end === self::FULL_TIME_END;
    }

    public static function formatShiftTime(?string $time): string
    {
        if (! $time) {
            return 'غير محدد';
        }

        $hour = (int) substr($time, 0, 2);
        $minute = substr($time, 3, 2);
        $h = $hour % 12 ?: 12;

        return $h.':'.$minute.' '.($hour >= 12 ? 'مساءً' : 'صباحاً');
    }
}
