<?php

namespace App\Services;

/**
 * إخفاء عناصر المستند حسب مفاتيح الإعدادات — بنيوياً لا بأسماء الأصناف.
 *
 * **لماذا؟**
 *
 * كان الإخفاء يعتمد على أن يسمّي كاتب القالب عناصره بأصناف متفق عليها
 * (`.tax-row`، `.qr-box`…). قياس القوالب الـ356 أظهر أن هذا الافتراض
 * يسقط عملياً: «عمود الضريبة» لم يكن يعمل في أي قالب من 246 يستعمله،
 * و«بيانات العميل» في 44 من 330.
 *
 * الحل هنا لا يسأل عن الأصناف إطلاقاً، بل يتتبّع **رمز الدمج نفسه**:
 *
 *   1. `annotate()` تضع `data-doc="show_tax"` على العنصر المحيط بكل رمز
 *      حسّاس، قبل تحويل الرموز إلى Blade.
 *   2. `prune()` تحذف العناصر المعلَّمة من الناتج بعد التصيير، وتعرف
 *      متى تحذف الخلية وحدها ومتى تحذف الصف أو العمود كاملاً.
 *
 * لا تعمل الطبقة إلا حين يُطفأ مفتاح فعلاً، فالمسار الافتراضي بلا تغيير.
 */
class DocumentTemplateVisibility
{
    /** المفتاح ← رموز الدمج التي يتحكّم بظهورها */
    public const CONTROLS = [
        'show_logo' => ['business.logo'],
        'show_qr' => ['document.qr_code'],
        'show_barcode' => ['document.barcode'],
        'show_customer' => [
            'customer.name', 'customer.code', 'customer.tax_number',
            'customer.phone', 'customer.address',
        ],
        'show_tax' => ['totals.tax', 'tax'],
        'show_discount' => ['totals.discount', 'discount'],
    ];

    /** عناصر بلا وسم إغلاق — لا تُوضع في مكدّس الأسلاف */
    protected const VOID = [
        'img', 'br', 'hr', 'input', 'meta', 'link', 'source', 'col', 'area', 'base', 'wbr',
    ];

    /** المفاتيح المطفأة في هذه الإعدادات */
    public function disabledKeys(array $settings): array
    {
        $disabled = [];

        foreach (array_keys(self::CONTROLS) as $key) {
            if (array_key_exists($key, $settings) && ! $settings[$key]) {
                $disabled[] = $key;
            }
        }

        return $disabled;
    }

    // ==================== التعليم ====================

    /**
     * يضع `data-doc="<key>"` على العنصر المحيط بكل رمز حسّاس.
     *
     * الرمز داخل سمة (`src="{{business.logo}}"`) يُعلَّم على الوسم نفسه،
     * والرمز في النص يُعلَّم على أقرب عنصر يحتويه.
     */
    public function annotate(string $content, array $keys): string
    {
        $wanted = $this->tokenMap($keys);

        if ($wanted === []) {
            return $content;
        }

        $tags = $this->scanTags($content);
        $stack = [];
        $marks = [];   // موضع الوسم ← المفتاح

        $cursor = 0;

        foreach ($tags as $tag) {
            // النص الواقع قبل هذا الوسم ينتمي إلى العنصر المفتوح حالياً
            $this->collectMarks(
                substr($content, $cursor, $tag['start'] - $cursor),
                $cursor,
                $wanted,
                $stack === [] ? null : end($stack)['start'],
                $marks
            );

            // رمز داخل سمات الوسم: العنصر المقصود هو الوسم نفسه
            $this->collectMarks($tag['html'], $tag['start'], $wanted, $tag['start'], $marks);

            if ($tag['closing']) {
                $this->popTo($stack, $tag['name']);
            } elseif (! $tag['selfClosing'] && ! in_array($tag['name'], self::VOID, true)) {
                $stack[] = ['name' => $tag['name'], 'start' => $tag['start']];
            }

            $cursor = $tag['start'] + strlen($tag['html']);
        }

        $this->collectMarks(
            substr($content, $cursor),
            $cursor,
            $wanted,
            $stack === [] ? null : end($stack)['start'],
            $marks
        );

        return $this->applyMarks($content, $marks);
    }

    /** الرمز ← المفتاح، لكل صيغ الكتابة الممكنة */
    protected function tokenMap(array $keys): array
    {
        $map = [];

        foreach ($keys as $key) {
            foreach (self::CONTROLS[$key] ?? [] as $token) {
                foreach (['{{' . $token . '}}', '{{ ' . $token . ' }}',
                    '{!!' . $token . '!!}', '{!! ' . $token . ' !!}'] as $form) {
                    $map[$form] = $key;
                }
            }
        }

        return $map;
    }

    /** كل الوسوم بمواضعها */
    protected function scanTags(string $content): array
    {
        preg_match_all('/<(\/?)([a-zA-Z][\w:-]*)\b[^>]*?(\/?)>/', $content, $matches, PREG_OFFSET_CAPTURE);

        $tags = [];

        foreach ($matches[0] as $index => $match) {
            $tags[] = [
                'html' => $match[0],
                'start' => $match[1],
                'closing' => $matches[1][$index][0] === '/',
                'name' => strtolower($matches[2][$index][0]),
                'selfClosing' => $matches[3][$index][0] === '/',
            ];
        }

        return $tags;
    }

    protected function collectMarks(string $text, int $offset, array $wanted, ?int $owner, array &$marks): void
    {
        if ($owner === null || $text === '') {
            return;
        }

        foreach ($wanted as $token => $key) {
            if (str_contains($text, $token)) {
                // العنصر الأقرب يفوز: أول مفتاح يُسجَّل هو الأعمق
                $marks[$owner] ??= $key;
            }
        }
    }

    protected function popTo(array &$stack, string $name): void
    {
        for ($i = count($stack) - 1; $i >= 0; $i--) {
            if ($stack[$i]['name'] === $name) {
                $stack = array_slice($stack, 0, $i);

                return;
            }
        }
    }

    /** يحقن السمة في الوسوم المعلَّمة — من الآخر للأول حفاظاً على المواضع */
    protected function applyMarks(string $content, array $marks): string
    {
        krsort($marks);

        foreach ($marks as $position => $key) {
            if (! preg_match('/^<([a-zA-Z][\w:-]*)/', substr($content, $position, 60), $match)) {
                continue;
            }

            $insertAt = $position + strlen($match[0]);
            $content = substr($content, 0, $insertAt)
                . ' data-doc="' . $key . '"'
                . substr($content, $insertAt);
        }

        return $content;
    }

    // ==================== الحذف ====================

    /**
     * يحذف العناصر المعلَّمة بالمفاتيح المطفأة من HTML مُصيَّر.
     */
    public function prune(string $html, array $keys): string
    {
        if ($keys === [] || ! str_contains($html, 'data-doc=')) {
            return $this->stripMarks($html);
        }

        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);

        $loaded = $document->loadHTML(
            '<?xml encoding="UTF-8"?><div id="doc-root">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            return $this->stripMarks($html);   // تعذّر التحليل: يُترك كما هو
        }

        $xpath = new \DOMXPath($document);

        // الحذف لا يجوز أن يُسقِط المستند: أي عطل هنا يعيد الأصل كما هو
        try {
            foreach ($keys as $key) {
                $nodes = $xpath->query('//*[@data-doc="' . $key . '"]');

                // القائمة حيّة أثناء الحذف، فتُنسخ أولاً
                foreach (iterator_to_array($nodes) as $node) {
                    $this->removeNode($node, $xpath);
                }
            }

            $this->removeEmptyRows($xpath);

            // `getElementById` لا يُعوَّل عليه مع `LIBXML_HTML_NOIMPLIED`
            $root = $xpath->query('//*[@id="doc-root"]')->item(0) ?? $document->documentElement;

            if (! $root instanceof \DOMElement) {
                return $this->stripMarks($html);
            }

            $output = '';

            foreach ($root->childNodes as $child) {
                $output .= $document->saveHTML($child);
            }
        } catch (\Throwable $e) {
            return $this->stripMarks($html);
        }

        return $this->stripMarks($output);
    }

    /**
     * يحذف العنصر بالحجم المناسب: خلية، أم عمود، أم صف، أم العنصر وحده.
     */
    protected function removeNode(\DOMElement $node, \DOMXPath $xpath): void
    {
        $cell = $this->closest($node, ['td', 'th']);

        if ($cell === null) {
            $node->parentNode?->removeChild($node);

            return;
        }

        $row = $this->closest($cell, ['tr']);

        if ($row === null) {
            $cell->parentNode?->removeChild($cell);

            return;
        }

        // جدول «تسمية / قيمة»: الصف كله يخصّ هذا المعنى فيُحذف كاملاً
        if ($this->cellCount($row) <= 2) {
            $row->parentNode?->removeChild($row);

            return;
        }

        // جدول أصناف: يُحذف العمود من كل الصفوف بما فيها صف الرأس
        $this->removeColumn($cell, $row, $xpath);
    }

    protected function removeColumn(\DOMElement $cell, \DOMElement $row, \DOMXPath $xpath): void
    {
        $index = $this->cellIndex($cell);
        $table = $this->closest($row, ['table']);

        if ($table === null || $index === null) {
            $cell->parentNode?->removeChild($cell);

            return;
        }

        foreach (iterator_to_array($xpath->query('.//tr', $table)) as $other) {
            $cells = $this->cells($other);

            if (isset($cells[$index])) {
                $cells[$index]->parentNode?->removeChild($cells[$index]);
            }
        }
    }

    protected function closest(\DOMNode $node, array $names): ?\DOMElement
    {
        $current = $node;

        while ($current instanceof \DOMElement) {
            if (in_array(strtolower($current->nodeName), $names, true)) {
                return $current;
            }

            $current = $current->parentNode;
        }

        return null;
    }

    /** @return array<int, \DOMElement> */
    protected function cells(\DOMElement $row): array
    {
        $cells = [];

        foreach ($row->childNodes as $child) {
            if ($child instanceof \DOMElement && in_array(strtolower($child->nodeName), ['td', 'th'], true)) {
                $cells[] = $child;
            }
        }

        return $cells;
    }

    protected function cellCount(\DOMElement $row): int
    {
        return count($this->cells($row));
    }

    protected function cellIndex(\DOMElement $cell): ?int
    {
        $row = $this->closest($cell, ['tr']);

        if ($row === null) {
            return null;
        }

        foreach ($this->cells($row) as $index => $one) {
            if ($one === $cell) {
                return $index;
            }
        }

        return null;
    }

    /** الصف الذي فقد كل خلاياه يترك فراغاً مرئياً في الجدول */
    protected function removeEmptyRows(\DOMXPath $xpath): void
    {
        foreach (iterator_to_array($xpath->query('//tr')) as $row) {
            if ($this->cellCount($row) === 0) {
                $row->parentNode?->removeChild($row);
            }
        }
    }

    protected function stripMarks(string $html): string
    {
        return (string) preg_replace('/\s*data-doc="[^"]*"/', '', $html);
    }
}
