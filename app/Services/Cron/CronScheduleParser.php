<?php

namespace App\Services\Cron;

use App\Models\CronJob;
use Cron\CronExpression;

/**
 * الترجمة بين ما يختاره المستخدم في الواجهة وتعبير Cron الذي يُنفَّذ.
 *
 * الواجهة تعرض «يومياً الساعة 02:00»، والمُشغّل لا يفهم إلا `0 2 * * *`.
 * كل تحويل بين الاتجاهين يمرّ من هنا، فيبقى تعريف واحد للجدولة لا تعريفان
 * يفترقان مع الوقت.
 */
class CronScheduleParser
{
    /**
     * بناء تعبير Cron من اختيار المستخدم.
     *
     * @param  string  $type  أحد مفاتيح CronJob::SCHEDULE_TYPES
     * @param  string|null  $time  بصيغة H:i أو H:i:s
     * @param  int|null  $day  يوم الأسبوع (0-6) أو يوم الشهر (1-31)
     * @param  string|null  $custom  التعبير الحرّ حين يكون النوع `cron`
     */
    public static function build(string $type, ?string $time = null, ?int $day = null, ?string $custom = null): string
    {
        if ($type === CronJob::SCHEDULE_CRON) {
            $expression = trim((string) $custom);

            if (! static::isValid($expression)) {
                throw new \InvalidArgumentException('تعبير Cron غير صالح: '.$expression);
            }

            return static::normalize($expression);
        }

        $template = CronJob::SCHEDULE_TYPES[$type]['expression'] ?? null;

        if ($template === null) {
            throw new \InvalidArgumentException('نمط جدولة غير معروف: '.$type);
        }

        [$hour, $minute] = static::splitTime($time);

        $expression = strtr($template, [
            '{m}' => (string) $minute,
            '{H}' => (string) $hour,
            '{d}' => (string) static::normalizeDay($type, $day),
        ]);

        // النتيجة مبنية من قوالبنا، لكن مدخلات المستخدم دخلت فيها —
        // فتُتحقَّق كما لو كانت تعبيراً حرّاً
        if (! static::isValid($expression)) {
            throw new \InvalidArgumentException('تعذّر بناء تعبير Cron صالح من الإعدادات المدخلة.');
        }

        return $expression;
    }

    public static function isValid(?string $expression): bool
    {
        $expression = trim((string) $expression);

        if ($expression === '') {
            return false;
        }

        // خمسة حقول فقط: `CronExpression` يقبل صيغاً مختصرة مثل `@daily`
        // وحقلاً سادساً للثواني، وكلاهما لا يفهمه cron في الخادم
        if (count(preg_split('/\s+/', $expression)) !== 5) {
            return false;
        }

        try {
            new CronExpression($expression);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public static function normalize(string $expression): string
    {
        return implode(' ', preg_split('/\s+/', trim($expression)));
    }

    /** موعد التشغيل التالي لتعبير معيّن، أو null إن كان غير صالح. */
    public static function nextRun(?string $expression, ?string $timezone = null): ?\DateTimeInterface
    {
        if (! static::isValid($expression)) {
            return null;
        }

        try {
            return (new CronExpression(static::normalize($expression)))
                ->getNextRunDate('now', 0, false, $timezone ?: config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * المواعيد القادمة — تُعرَض للمستخدم ليتأكّد أن التعبير يعني ما قصده.
     *
     * @return array<int, \DateTimeInterface>
     */
    public static function nextRuns(?string $expression, int $count = 5, ?string $timezone = null): array
    {
        if (! static::isValid($expression)) {
            return [];
        }

        try {
            return (new CronExpression(static::normalize($expression)))
                ->getMultipleRunDates($count, 'now', false, false, $timezone ?: config('app.timezone'));
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * شرح التعبير بالعربية.
     *
     * الأنماط الشائعة تُشرَح بجملة مفهومة، وما خرج عنها يُفكَّك حقلاً حقلاً
     * بدل أن يُترَك رموزاً — الغاية أن يفهم المسؤول ما اختاره قبل أن يحفظه.
     */
    public static function describe(?string $expression): string
    {
        if (! static::isValid($expression)) {
            return __('settings.cron.describe.invalid');
        }

        $expression = static::normalize($expression);
        [$minute, $hour, $dom, $month, $dow] = preg_split('/\s+/', $expression);

        $everyField = fn ($f) => $f === '*';

        // ---- الأنماط الشائعة ----
        if ($expression === '* * * * *') {
            return __('settings.cron.describe.every_minute');
        }

        if (preg_match('#^\*/(\d+) \* \* \* \*$#', $expression, $m)) {
            return static::everyMinutesLabel((int) $m[1]);
        }

        if (preg_match('#^\*/(\d+) (.+)$#', $minute.' '.$hour, $m) && $everyField($dom) && $everyField($month) && $everyField($dow) && $hour === '*') {
            return static::everyMinutesLabel((int) $m[1]);
        }

        $atTime = fn () => sprintf('%02d:%02d', (int) $hour, (int) $minute);

        // `L` = آخر يوم في الشهر. يفهمه محلّل التعبير المستعمل هنا، ولولا
        // شرحه صراحةً لبقي حرفاً غامضاً في الواجهة
        if (is_numeric($minute) && is_numeric($hour) && strtoupper($dom) === 'L' && $everyField($month) && $everyField($dow)) {
            return __('settings.cron.describe.last_day', ['time' => $atTime()]);
        }

        if (is_numeric($minute) && $everyField($hour) && $everyField($dom) && $everyField($month) && $everyField($dow)) {
            return __('settings.cron.describe.hourly_at', ['minute' => (int) $minute]);
        }

        if (is_numeric($minute) && is_numeric($hour) && $everyField($dom) && $everyField($month) && $everyField($dow)) {
            return __('settings.cron.describe.daily_at', ['time' => $atTime()]);
        }

        if (is_numeric($minute) && is_numeric($hour) && $everyField($dom) && $everyField($month) && is_numeric($dow)) {
            return __('settings.cron.describe.weekly_at', [
                'day' => CronJob::weekDays()[(int) $dow] ?? $dow,
                'time' => $atTime(),
            ]);
        }

        if (is_numeric($minute) && is_numeric($hour) && is_numeric($dom) && $everyField($month) && $everyField($dow)) {
            return __('settings.cron.describe.monthly_at', ['day' => (int) $dom, 'time' => $atTime()]);
        }

        if (is_numeric($minute) && is_numeric($hour) && is_numeric($dom) && ! $everyField($month) && $everyField($dow)) {
            return __('settings.cron.describe.months_at', [
                'day' => (int) $dom,
                'months' => $month,
                'time' => $atTime(),
            ]);
        }

        // ---- تفكيك عام ----
        $parts = [];
        $parts[] = __('settings.cron.describe.minute').': '.static::describeField($minute, __('settings.cron.describe.any_minute'));
        $parts[] = __('settings.cron.describe.hour').': '.static::describeField($hour, __('settings.cron.describe.any_hour'));
        $parts[] = __('settings.cron.describe.day_of_month').': '.static::describeField($dom, __('settings.cron.describe.any_day'));
        $parts[] = __('settings.cron.describe.month').': '.static::describeField($month, __('settings.cron.describe.any_month'));
        $parts[] = __('settings.cron.describe.day_of_week').': '.static::describeField($dow, __('settings.cron.describe.any_weekday'), CronJob::weekDays());

        return implode(__('settings.cron.describe.separator'), $parts);
    }

    /** صياغة «كل N دقيقة» بجمع عربي سليم. */
    private static function everyMinutesLabel(int $minutes): string
    {
        return match (true) {
            $minutes === 1 => __('settings.cron.describe.every_minute'),
            $minutes === 2 => __('settings.cron.describe.every_two_minutes'),
            $minutes <= 10 => __('settings.cron.describe.every_few_minutes', ['count' => $minutes]),
            default => __('settings.cron.describe.every_n_minutes', ['count' => $minutes]),
        };
    }

    /**
     * @param  array<int, string>  $names
     */
    private static function describeField(string $field, string $anyLabel, array $names = []): string
    {
        if ($field === '*') {
            return $anyLabel;
        }

        if (preg_match('#^\*/(\d+)$#', $field, $m)) {
            return __('settings.cron.describe.every_step', ['step' => $m[1]]);
        }

        if ($names !== [] && is_numeric($field)) {
            return $names[(int) $field] ?? $field;
        }

        if ($names !== [] && str_contains($field, ',')) {
            return implode(__('settings.cron.describe.list_separator'), array_map(
                fn ($v) => is_numeric($v) ? ($names[(int) $v] ?? $v) : $v,
                explode(',', $field)
            ));
        }

        return $field;
    }

    /**
     * @return array{0:int,1:int} [الساعة, الدقيقة]
     */
    private static function splitTime(?string $time): array
    {
        $time = trim((string) $time);

        if ($time === '' || ! preg_match('/^(\d{1,2}):(\d{1,2})(?::\d{1,2})?$/', $time, $m)) {
            return [0, 0];
        }

        return [min(23, max(0, (int) $m[1])), min(59, max(0, (int) $m[2]))];
    }

    /** يوم الأسبوع يُحصر في 0-6 ويوم الشهر في 1-31 قبل دخوله التعبير. */
    private static function normalizeDay(string $type, ?int $day): int
    {
        $day ??= $type === 'weekly' ? 0 : 1;

        return $type === 'weekly'
            ? max(0, min(6, $day))
            : max(1, min(31, $day));
    }
}
