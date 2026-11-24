<?php

namespace App\Services;

use App\Support\TemplateStyleOptions;

/**
 * يحوّل خيارات لوحة التصميم إلى CSS، ويقرأها منه مرة أخرى.
 *
 * **لماذا كتلة مُدارة؟**
 *
 * القوالب الـ356 تحمل CSS مكتوباً بخط اليد. لو ولّدنا التنسيق فوقه كاملاً
 * ضاع عمل سابق، ولو أعدنا قراءة الخيارات بتحليل CSS الحر أخطأنا. لذا يُلحَق
 * ما تولّده اللوحة في **كتلة معلَّمة** في آخر التنسيق:
 *
 *     … CSS الأصلي كما هو …
 *     ⟦بداية الكتلة⟧
 *     ⟦القيم بصيغة JSON داخل تعليق⟧
 *     … القواعد المولَّدة …
 *     ⟦نهاية الكتلة⟧
 *
 * القيم تُقرأ من تعليق JSON لا من القواعد، فالذهاب والإياب مضمون. وما
 * خارج الكتلة لا يُلمس أبداً.
 */
class TemplateStyleSheet
{
    protected const OPEN = '/* ===== بداية تنسيق لوحة التصميم — يُدار تلقائياً ===== */';
    protected const CLOSE = '/* ===== نهاية تنسيق لوحة التصميم ===== */';

    /** أغلفة الورق التي يولّدها المصيّر */
    protected const WRAPPERS = [
        '.document-a4.document-a4',
        '.document-thermal-80.document-thermal-80',
        '.document-thermal-58.document-thermal-58',
    ];

    // ==================== قراءة ====================

    /**
     * الخيارات المحفوظة داخل التنسيق — فارغة إن لم تُستعمل اللوحة بعد.
     */
    public function extract(?string $css): array
    {
        if (! $css || ! preg_match('/\/\* dt-style:(.*?) \*\//s', $css, $match)) {
            return [];
        }

        $values = json_decode(trim($match[1]), true);

        return is_array($values) ? $this->sanitize($values) : [];
    }

    /**
     * التنسيق بلا الكتلة المُدارة — هذا ما يُعرض في محرّر CSS.
     */
    public function stripManaged(?string $css): string
    {
        $css = (string) $css;

        $pattern = '/\s*' . preg_quote(self::OPEN, '/') . '.*?' . preg_quote(self::CLOSE, '/') . '\s*/s';

        return trim((string) preg_replace($pattern, "\n", $css));
    }

    // ==================== كتابة ====================

    /**
     * يدمج خيارات اللوحة مع التنسيق المكتوب يدوياً.
     */
    public function compose(?string $css, array $values): string
    {
        $base = $this->stripManaged($css);
        $values = $this->sanitize($values);

        if ($values === []) {
            return $base;
        }

        $block = self::OPEN . "\n"
            . '/* dt-style:' . json_encode($values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . " */\n"
            . $this->build($values) . "\n"
            . self::CLOSE;

        return $base === '' ? $block : $base . "\n\n" . $block;
    }

    /**
     * القواعد المولَّدة من الخيارات.
     *
     * المُحدِّد يكرّر الصنف (`.document-a4.document-a4`) ليتفوّق على قواعد
     * الغلاف التي يضيفها المصيّر **بعد** هذا التنسيق — دون اللجوء إلى
     * `!important` الذي كان سيبتلع أنماط العناصر المكتوبة داخل الوسم نفسه.
     */
    public function build(array $values): string
    {
        $rules = [];

        // ---------- الصفحة ----------
        // `!important` هنا ضروري: المصيّر يضيف قواعد الغلاف (padding: 20mm)
        // **بعد** هذا التنسيق، فبلا التوكيد تفوز قاعدته لتأخّرها في المصدر.
        $page = [];
        $this->push($page, $values, 'page_padding', fn ($v) => "padding: {$v}mm !important");
        $this->push($page, $values, 'page_bg', fn ($v) => "background-color: {$v} !important");

        $border = $values['page_border'] ?? null;
        if ($border === 'frame' || $border === 'top') {
            $color = $values['page_border_color'] ?? '#d5d8e0';
            $page[] = $border === 'frame'
                ? "border: 1px solid {$color} !important"
                : "border-top: 4px solid {$color} !important";
        }

        $this->rule($rules, $this->selector(), $page);

        // ---------- لون النص وتباعد الأسطر ----------
        // التوريث من الغلاف وحده لا يكفي: أي قاعدة يكتبها القالب على عنصر
        // داخلي تتغلّب على اللون الموروث. لذا تُذكر العناصر الحاملة للنص صراحةً.
        $textTargets = ', p, div, span, td, th, li, strong, b, em, small, label';

        $this->rule($rules, $this->selector($textTargets), array_filter([
            isset($values['text_color']) ? 'color: ' . $values['text_color'] . ' !important' : null,
        ]));

        $this->rule($rules, $this->selector($textTargets), array_filter([
            isset($values['line_height']) ? 'line-height: ' . $values['line_height'] . ' !important' : null,
        ]));

        // ---------- العناوين ----------
        // كثير من القوالب تكتب العنوان `<div class="doc-title">` لا `<h1>`،
        // فالاعتماد على وسم العنوان وحده يترك اللوحة بلا أثر ظاهر.
        $headingTargets = ' h1, h2, h3, h4, [class*="title"], [class*="heading"]';

        $this->rule($rules, $this->selector($headingTargets), array_filter([
            isset($values['heading_color']) ? 'color: ' . $values['heading_color'] . ' !important' : null,
        ]));

        $headingSize = $values['heading_size'] ?? null;
        if ($headingSize !== null) {
            $this->rule($rules, $this->selector(' h1, [class*="title"]'),
                ['font-size: ' . $headingSize . 'px !important']);
            $this->rule($rules, $this->selector(' h2'),
                ['font-size: ' . round($headingSize * 0.8) . 'px !important']);
            $this->rule($rules, $this->selector(' h3, h4'),
                ['font-size: ' . round($headingSize * 0.68) . 'px !important']);
        }

        // ---------- الجدول ----------
        // بلا `!important` هنا عمداً: الجداول المستعملة للتخطيط تحمل
        // `style="border:0"` داخل الوسم، والتوكيد كان سيرسم لها حدوداً.
        $cell = [];
        if (isset($values['table_border_width']) || isset($values['table_border_color'])) {
            $width = $values['table_border_width'] ?? 1;
            $color = $values['table_border_color'] ?? '#d5d8e0';
            $cell[] = $width > 0 ? "border: {$width}px solid {$color}" : 'border: 0';
        }
        $this->push($cell, $values, 'table_cell_padding', fn ($v) => "padding: {$v}px");
        $this->rule($rules, $this->selector(' table th, table td'), $cell);

        $this->rule($rules, $this->selector(' table, table td, table th'), array_filter([
            isset($values['table_font_size'])
                ? 'font-size: ' . $values['table_font_size'] . 'px !important'
                : null,
        ]));

        // رأس الجدول بعد لون النص العام ليتغلّب عليه
        $head = [];
        $this->push($head, $values, 'table_head_bg', fn ($v) => "background-color: {$v} !important");
        $this->push($head, $values, 'table_head_color', fn ($v) => "color: {$v} !important");
        $this->rule($rules, $this->selector(' table th'), $head);

        if (! empty($values['table_stripe'])) {
            $stripe = $values['table_stripe_color'] ?? '#fafbfd';
            $this->rule($rules, $this->selector(' table tbody tr:nth-child(even) td'), [
                "background-color: {$stripe} !important",
            ]);
        }

        // ---------- الشعار والرموز ----------
        $this->rule($rules, $this->selector(' img[class*="logo"], .company-logo, .modern-logo'), array_filter([
            isset($values['logo_height']) ? 'max-height: ' . $values['logo_height'] . 'px !important' : null,
        ]));

        if (isset($values['qr_size'])) {
            $size = $values['qr_size'];
            $this->rule($rules, $this->selector(' .qr-box, .t-qr, .modern-qr, img[alt*="QR"]'), [
                "width: {$size}px !important",
                "height: {$size}px !important",
            ]);
        }

        return implode("\n", $rules);
    }

    // ==================== التحقّق ====================

    /**
     * يُسقِط المفاتيح المجهولة ويضبط القيم داخل حدودها.
     *
     * القيم تُطبع داخل `<style>` — لون غير صالح هنا يعني حقن CSS.
     */
    public function sanitize(array $values): array
    {
        $clean = [];

        foreach ($values as $key => $value) {
            $option = TemplateStyleOptions::option((string) $key);

            if ($option === null || $value === null || $value === '') {
                continue;
            }

            $clean[$key] = match ($option['type']) {
                'color' => $this->color($value),
                'number' => $this->number($value, $option),
                'select' => in_array($value, array_keys($option['choices']), true) ? $value : null,
                'switch' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
                default => null,
            };

            if ($clean[$key] === null) {
                unset($clean[$key]);
            }
        }

        return $clean;
    }

    protected function color(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        return preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? strtolower($value) : null;
    }

    protected function number(mixed $value, array $option): int|float|null
    {
        if (! is_numeric($value)) {
            return null;
        }

        $value = (float) $value;
        $value = max($option['min'], min($option['max'], $value));

        // الخطوة الصحيحة تُخزَّن عدداً صحيحاً حتى لا تظهر «6.0» في CSS
        return ($option['step'] ?? 1) >= 1 ? (int) round($value) : round($value, 2);
    }

    // ==================== أدوات ====================

    /** المُحدِّد لكل أغلفة الورق، مع لاحقة اختيارية */
    protected function selector(string $suffix = ''): string
    {
        if ($suffix === '') {
            return implode(', ', self::WRAPPERS);
        }

        $parts = [];

        // اللاحقة قد تحمل أكثر من مُحدِّد: `' table th, table td'`
        foreach (explode(',', $suffix) as $piece) {
            $piece = trim($piece);

            foreach (self::WRAPPERS as $wrapper) {
                // جزء فارغ يعني الغلاف نفسه — بلا هذا يخرج مُحدِّد ينتهي
                // بمُركِّب نسب بلا هدف، فيُسقِط المتصفح القاعدة كاملة
                $parts[] = $piece === '' ? $wrapper : $wrapper . ' ' . $piece;
            }
        }

        return implode(', ', array_unique($parts));
    }

    protected function push(array &$declarations, array $values, string $key, callable $format): void
    {
        if (isset($values[$key])) {
            $declarations[] = $format($values[$key]);
        }
    }

    protected function rule(array &$rules, string $selector, array $declarations): void
    {
        $declarations = array_values(array_filter($declarations));

        if ($declarations === []) {
            return;
        }

        $rules[] = $selector . " {\n    " . implode(";\n    ", $declarations) . ";\n}";
    }
}
