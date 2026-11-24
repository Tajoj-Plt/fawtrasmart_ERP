<?php

namespace App\Support;

/**
 * شرائحُ مخطَّطات لوحة تحكّم العملاء — يعرّفها المستخدم لا الكود.
 *
 * «دورة حياة العميل» و«تقادم المديونية» كانت حدودُها وأسماؤها وألوانُها
 * ثوابتَ في `ClientDashboardService`. صارت صفوفاً في
 * «إعدادات وحدات العملاء ← المتابعة ← لوحة تحكّم العملاء»، تُحفظ في حقيبة
 * `client_dashboard_settings` وتُقرأ من هنا. («تصنيف RFM» حُذف من اللوحة
 * بطلب المستخدم، وحلّت محلَّه «أهداف العملاء» من إعداداتها هي.)
 *
 * **مصدرٌ واحد للتنقية.** الشاشةُ تحفظ عبر `normalize*()` واللوحةُ تقرأ عبرها
 * أيضاً — فصفٌّ تالفٌ كُتب في قاعدة البيانات بيدٍ أخرى لا يكسر اللوحة.
 *
 * **الترتيبُ معنى.** شرائحُ دورة الحياة تُفحص من الأعلى، ويدخل العميلُ
 * أوّل شريحةٍ تنطبق عليه — فالمجموعُ يساوي عددَ العملاء حتماً. ومن لا
 * تنطبق عليه شريحةٌ يقع في «غير مصنَّف» ظاهراً لا مُسقَطاً.
 *
 * **التقادمُ حدودٌ متّصلة.** يُرتَّب بالحدّ الأعلى وتُفتح آخرُ شريحة بلا
 * حدّ، فكلُّ دينٍ يقع في شريحةٍ واحدة ومجموعُها = إجمالي المديونية.
 */
final class ClientDashboardSegments
{
    public const GROUP = 'client_dashboard_settings';

    public const MAX_ROWS = 12;

    private const MAX_DAYS = 3650;

    /** الافتراضي = ما كانت اللوحةُ تحسبه قبل أن يصير قابلاً للتعديل */
    public const DEFAULT_LIFECYCLE = [
        ['label' => 'مستعاد', 'color' => '#0e9f6e', 'from' => 0, 'to' => 14, 'min_invoices' => null, 'min_gap' => 90],
        ['label' => 'نشط', 'color' => '#28c76f', 'from' => 0, 'to' => 30, 'min_invoices' => null, 'min_gap' => null],
        ['label' => 'فعّال', 'color' => '#00cfe8', 'from' => 31, 'to' => 60, 'min_invoices' => 3, 'min_gap' => null],
        ['label' => 'في خطر', 'color' => '#ff9f43', 'from' => 31, 'to' => 120, 'min_invoices' => null, 'min_gap' => null],
        ['label' => 'خامل', 'color' => '#7367f0', 'from' => 121, 'to' => 180, 'min_invoices' => null, 'min_gap' => null],
        ['label' => 'مفقود', 'color' => '#ea5455', 'from' => 181, 'to' => null, 'min_invoices' => null, 'min_gap' => null],
    ];

    public const DEFAULT_AGING = [
        ['label' => '0-30 يوم', 'color' => '#28c76f', 'to' => 30],
        ['label' => '31-60 يوم', 'color' => '#ff9f43', 'to' => 60],
        ['label' => '61-90 يوم', 'color' => '#ea5455', 'to' => 90],
        ['label' => '90+ يوم', 'color' => '#82868b', 'to' => null],
    ];

    // ═════════════════════════════ القراءة ═════════════════════════════

    /**
     * شرائحُ دورة الحياة بمفاتيحها (`s0`, `s1`…) + «بلا نشاط» + «غير مصنَّف».
     *
     * @return array<string, array<string, mixed>>
     */
    public static function lifecycle(): array
    {
        $rows = self::normalizeLifecycle(ClientSettings::option(self::GROUP, 'lifecycle_segments'))
            ?: self::normalizeLifecycle(self::DEFAULT_LIFECYCLE);

        $out = [];
        foreach ($rows as $i => $row) {
            $out['s'.$i] = $row + ['key' => 's'.$i, 'hint' => self::lifecycleHint($row)];
        }

        $blank = ['from' => null, 'to' => null, 'min_invoices' => null, 'min_gap' => null];
        $out['never'] = $blank + ['key' => 'never', 'label' => 'بلا نشاط', 'color' => '#b9b9c3', 'hint' => 'لا فاتورة مبيعات له أبداً'];
        $out['other'] = $blank + ['key' => 'other', 'label' => 'غير مصنَّف', 'color' => '#dfe1e6', 'hint' => 'له نشاط لكن لا تنطبق عليه أيُّ شريحةٍ من الإعدادات'];

        return $out;
    }

    /**
     * شرائحُ التقادم مرتَّبةً ومتّصلة، ولكلٍّ `from`/`to` بأيام التأخّر.
     *
     * @return array<string, array{key: string, label: string, color: string, from: int, to: ?int, hint: string}>
     */
    public static function aging(): array
    {
        $rows = self::normalizeAging(ClientSettings::option(self::GROUP, 'aging_segments'))
            ?: self::normalizeAging(self::DEFAULT_AGING);

        $out = [];
        $from = 0;
        foreach ($rows as $i => $row) {
            $row['from'] = $from;
            $out['s'.$i] = $row + ['key' => 's'.$i, 'hint' => self::agingHint($from, $row['to'])];
            $from = $row['to'] === null ? $from : $row['to'] + 1;
        }

        return $out;
    }

    /** مفتاحُ شريحة التقادم لعدد أيام تأخّر */
    public static function bucketFor(int $daysLate, array $aging): string
    {
        foreach ($aging as $key => $row) {
            if ($row['to'] === null || $daysLate <= $row['to']) {
                return $key;
            }
        }

        return (string) array_key_last($aging);
    }

    /** أتقع هذه الأيامُ في مدى الشريحة؟ `to = null` بلا حدّ أعلى */
    public static function inRange(?int $days, array $segment): bool
    {
        return $days !== null
            && $days >= (int) ($segment['from'] ?? 0)
            && ($segment['to'] === null || $days <= $segment['to']);
    }

    // ═════════════════════════════ التنقية ═════════════════════════════

    /** @return array<int, array{label: string, color: string, from: int, to: ?int, min_invoices: ?int, min_gap: ?int}> */
    public static function normalizeLifecycle(mixed $rows): array
    {
        $out = [];
        foreach (self::rowsOf($rows) as $row) {
            $label = self::label($row['label'] ?? null);
            if ($label === null) {
                continue;
            }

            [$from, $to] = self::range($row);
            $out[] = [
                'label' => $label,
                'color' => self::color($row['color'] ?? null),
                'from' => $from,
                'to' => $to,
                'min_invoices' => self::days($row['min_invoices'] ?? null),
                'min_gap' => self::days($row['min_gap'] ?? null),
            ];
        }

        return array_slice($out, 0, self::MAX_ROWS);
    }

    /**
     * صفوفُ التقادم مرتَّبةً بالحدّ الأعلى، بلا حدٍّ مكرَّر، وآخرُها مفتوح.
     *
     * @return array<int, array{label: string, color: string, to: ?int}>
     */
    public static function normalizeAging(mixed $rows): array
    {
        $out = [];
        foreach (self::rowsOf($rows) as $row) {
            $label = self::label($row['label'] ?? null);
            if ($label !== null) {
                $out[] = ['label' => $label, 'color' => self::color($row['color'] ?? null), 'to' => self::days($row['to'] ?? null)];
            }
        }

        usort($out, fn ($a, $b) => ($a['to'] ?? PHP_INT_MAX) <=> ($b['to'] ?? PHP_INT_MAX));

        $unique = [];
        $seen = [];
        foreach (array_slice($out, 0, self::MAX_ROWS) as $row) {
            $bound = $row['to'] ?? 'open';
            if (! isset($seen[$bound])) {
                $seen[$bound] = true;
                $unique[] = $row;
            }
        }

        if ($unique !== []) {
            $unique[count($unique) - 1]['to'] = null;
        }

        return $unique;
    }

    // ═════════════════════════════ الوصف ═════════════════════════════

    private static function lifecycleHint(array $row): string
    {
        $parts = ['آخر فاتورة '.self::rangeText($row['from'], $row['to'])];

        if ($row['min_invoices']) {
            $parts[] = $row['min_invoices'].' فواتير فأكثر';
        }
        if ($row['min_gap'] !== null) {
            $parts[] = 'بعد انقطاعٍ عن الشراء '.$row['min_gap'].' يوماً فأكثر';
        }

        return implode(' · ', $parts);
    }

    private static function agingHint(int $from, ?int $to): string
    {
        return match (true) {
            $from === 0 && $to === null => 'كل الديون',
            $from === 0 => 'غير مستحقٍّ بعد أو متأخّرٌ حتى '.$to.' يوماً',
            $to === null => 'متأخّرٌ أكثر من '.($from - 1).' يوماً',
            default => 'متأخّرٌ '.$from.'–'.$to.' يوماً',
        };
    }

    private static function rangeText(int $from, ?int $to): string
    {
        return match (true) {
            $to === null && $from === 0 => 'في أي وقت',
            $to === null => 'قبل '.$from.' يوماً فأكثر',
            $from === 0 => 'خلال '.$to.' يوماً',
            default => 'قبل '.$from.'–'.$to.' يوماً',
        };
    }

    // ═════════════════════════════ أدوات ═════════════════════════════

    /** @return array<int, array<string, mixed>> */
    private static function rowsOf(mixed $rows): array
    {
        return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
    }

    /** @return array{0: int, 1: ?int} */
    private static function range(array $row): array
    {
        $from = self::days($row['from'] ?? null) ?? 0;
        $to = self::days($row['to'] ?? null);

        return ($to !== null && $to < $from) ? [$to, $from] : [$from, $to];
    }

    private static function days(mixed $value): ?int
    {
        return $value === null || $value === '' || ! is_numeric($value)
            ? null
            : max(0, min(self::MAX_DAYS, (int) $value));
    }

    private static function label(mixed $value): ?string
    {
        $label = trim((string) $value);

        return $label === '' ? null : mb_substr($label, 0, 40);
    }

    private static function color(mixed $value): string
    {
        return is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? strtolower($value) : '#7367f0';
    }
}
