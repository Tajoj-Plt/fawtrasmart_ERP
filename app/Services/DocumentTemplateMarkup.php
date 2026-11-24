<?php

namespace App\Services;

use App\Support\DocumentFields;

/**
 * تحويل محتوى القالب بين صيغة التخزين وصيغة المحرّر المرئي.
 *
 * **لماذا هذه الطبقة أصلاً؟**
 *
 * صيغة التخزين تضع رموزاً نصية عارية بين وسوم HTML:
 *
 *     <tbody>
 *         {{#items}}
 *         <tr><td>{{name}}</td></tr>
 *         {{/items}}
 *     </tbody>
 *
 * أي محرّر مرئي يمرّ على هذا يحذف النص العاري لأن `<tbody>` لا يقبل نصاً
 * مباشراً — فتضيع الحلقة ويصبح القالب معطوباً بمجرد فتحه.
 *
 * الحل: تُحوَّل هذه الرموز إلى **سمات على العناصر نفسها** قبل فتح المحرّر،
 * وتُعاد إلى صيغتها الأصلية عند الحفظ:
 *
 *     <tr data-repeat="items"><td>…</td></tr>          ← الحلقة
 *     <th data-if="settings.show_tax">الضريبة</th>      ← الشرط
 *     <span class="doc-field" data-field="business.name">اسم المنشأة</span>
 *
 * السمات تنجو من أي محرّر لأنها جزء من بنية HTML لا نصّ حر.
 */
class DocumentTemplateMarkup
{
    /** يحفظ ما لم نستطع تمثيله بسمة، ليعود كما هو عند الحفظ */
    protected const RAW_MARKER = 'doc-raw';

    // ==================== التخزين ← المحرّر ====================

    /**
     * يحوّل محتوى القالب المخزَّن إلى HTML آمن للمحرّر المرئي.
     */
    public function toEditor(?string $content): string
    {
        $content = (string) $content;

        if ($content === '') {
            return '';
        }

        $content = $this->loopToAttribute($content);
        $content = $this->conditionToAttribute($content);
        $content = $this->fieldToChip($content);

        return $this->remainingBladeToPlaceholder($content);
    }

    /**
     * `{{#items}}<tr>…</tr>{{/items}}` ← يصبح سمة على الصف.
     *
     * الحلقة قد تحيط بأكثر من صف، فتُوضع السمة على كل صف بداخلها.
     */
    protected function loopToAttribute(string $content): string
    {
        return preg_replace_callback(
            '/\{\{#items\}\}(.*?)\{\{\/items\}\}/s',
            function (array $match) {
                $inner = $match[1];

                // كل `<tr>` داخل الحلقة يحمل السمة
                $marked = preg_replace('/<tr\b(?![^>]*data-repeat)/i', '<tr data-repeat="items"', $inner, -1, $count);

                if ($count > 0) {
                    return $marked;
                }

                // حلقة لا تحيط بصفوف (قوالب الطابعة الحرارية مثلاً): تُغلَّف
                // بعنصر يحمل السمة، ويُعلَّم بأنه غلاف مُضاف ليُزال عند الحفظ.
                // بدون هذه العلامة يبقى الغلاف ويتراكم `<div>` جديد في كل حفظ.
                return '<div data-repeat="items" data-repeat-wrapper="1">' . $inner . '</div>';
            },
            $content
        );
    }

    /**
     * `@if(cond) <el>…</el> @endif` ← يصبح `data-if` على العنصر.
     *
     * تُعالَج الحالة الشائعة فقط: شرط يحيط بعنصر واحد كامل. أي شرط أعقد
     * يبقى كما هو ويُحفظ عبر `remainingBladeToPlaceholder`.
     */
    protected function conditionToAttribute(string $content): string
    {
        // الشرط: بلا وسوم ولا أسطر جديدة، ويسمح بمستوى واحد من الأقواس المتداخلة.
        // بدون هذا القيد يبتلع النمط مقاطع كاملة من المستند بحثاً عن `@endif`
        // بعيد، فيحذف كل ما بينهما عند الاستبدال.
        $condition = '(?:[^()<>\r\n]|\([^()<>\r\n]*\))+?';

        // جسم الشرط: عنصر واحد كامل لا يعبر شرطاً آخر
        $body = '(<(\w+)\b[^>]*>(?:(?!@if\b|@endif\b|@else\b).)*?<\/\3>)';

        $pattern = '/@if\s*\(\s*(' . $condition . ')\s*\)\s*' . $body . '\s*@endif/s';

        return preg_replace_callback($pattern, function (array $match) {
            [, $condition, $element, $tag] = $match;

            $normalized = $this->normalizeCondition($condition);

            if ($normalized === null) {
                return $match[0]; // شرط غير معروف: يُترك للمعالج التالي
            }

            return preg_replace(
                '/^<' . $tag . '\b/i',
                '<' . $tag . ' data-if="' . e($normalized) . '"',
                $element,
                1
            );
        }, $content);
    }

    /**
     * يبسّط شروط الإعدادات إلى المفتاح وحده.
     *
     * `$settings['show_tax'] ?? true` و `settings.show_tax` كلاهما ← `settings.show_tax`
     */
    protected function normalizeCondition(string $condition): ?string
    {
        $condition = trim($condition);

        if (preg_match('/\$?settings\s*\[\s*[\'"](\w+)[\'"]\s*\]/', $condition, $match)) {
            $key = 'settings.' . $match[1];

            return isset(DocumentFields::CONDITIONS[$key]) ? $key : null;
        }

        if (preg_match('/^\$?(settings\.\w+)/', $condition, $match)) {
            return isset(DocumentFields::CONDITIONS[$match[1]]) ? $match[1] : null;
        }

        return null;
    }

    /**
     * `{{business.name}}` ← شريحة مرئية يراها المستخدم بتسميتها العربية.
     *
     * `contenteditable="false"` يجعلها كتلة واحدة لا يمكن تحرير نصّها جزئياً
     * ولا كسر الرمز بالخطأ — تُحذف كاملة أو تبقى كاملة.
     */
    protected function fieldToChip(string $content): string
    {
        // حقول الصورة: تبقى داخل src فلا تُحوَّل إلى شريحة
        $content = preg_replace_callback(
            '/\{\{\s*(' . $this->fieldPattern() . ')\s*\}\}|\{!!\s*(' . $this->fieldPattern() . ')\s*!!\}/',
            function (array $match) {
                $key = $match[1] !== '' ? $match[1] : ($match[2] ?? '');

                if ($key === '' || DocumentFields::isImage($key)) {
                    return $match[0];
                }

                return $this->chip($key);
            },
            $content
        );

        // حقول سطر الصنف داخل الصفوف المتكررة
        foreach (array_keys(DocumentFields::ITEM_FIELDS) as $key) {
            $content = preg_replace(
                '/\{\{\s*' . preg_quote($key, '/') . '\s*\}\}/',
                $this->chip($key, true),
                $content
            );
        }

        return $content;
    }

    protected function chip(string $key, bool $isItem = false): string
    {
        return self::chipHtml($key, $isItem);
    }

    /**
     * صيغة الشريحة داخل المحرّر — يستعملها أيضاً مولّد الكتل الجاهزة.
     *
     * الصيغة واحدة في مكان واحد: أي اختلاف بينها وبين ما يتوقّعه
     * `chipToField` عند الحفظ يعني حقلاً يُحفظ كنصّ عادي بلا قيمة.
     */
    public static function chipHtml(string $key, bool $isItem = false): string
    {
        $label = DocumentFields::label($key) ?? $key;

        return '<span class="doc-field' . ($isItem ? ' doc-field-item' : '') . '"'
            . ' data-field="' . e($key) . '" contenteditable="false">'
            . e($label) . '</span>';
    }

    /**
     * أي Blade متبقٍّ (شرط معقّد، دالة، حلقة أخرى) يُحفظ داخل عنصر محايد.
     *
     * بدون هذا يحذفه المحرّر بصمت ويفقد المستخدم منطقاً كتبه.
     */
    protected function remainingBladeToPlaceholder(string $content): string
    {
        return preg_replace_callback(
            '/(@(?:if|elseif|else|endif|foreach|endforeach|forelse|empty|endforelse|php|endphp)\b[^\n<]*)/',
            fn (array $match) => '<span class="' . self::RAW_MARKER . '" contenteditable="false"'
                . ' data-raw="' . e($match[1]) . '">' . e(trim($match[1])) . '</span>',
            $content
        );
    }

    // ==================== المحرّر ← التخزين ====================

    /**
     * يعيد HTML المحرّر إلى صيغة التخزين.
     */
    public function fromEditor(?string $html): string
    {
        $html = (string) $html;

        if ($html === '') {
            return '';
        }

        $html = $this->placeholderToBlade($html);
        $html = $this->chipToField($html);
        $html = $this->attributeToCondition($html);

        return $this->balance($this->attributeToLoop($html));
    }

    /**
     * يوازن العلامات المفتوحة والمغلقة قبل الحفظ.
     *
     * المحرّر المرئي قد ينسخ عنصراً خاماً يحمل `@if` بلا `@endif` المقابل
     * (نسخ كتلة، حذف جزئي، تراجع). العلامة غير المتوازنة لا تكسر السطر
     * الذي فيها فحسب، بل تمنع تصريف القالب كاملاً فتظهر صفحة خطأ بدل
     * المستند. الموازنة هنا تجعل أسوأ الحالات نقصاً في عنصر لا انهياراً.
     */
    protected function balance(string $content): string
    {
        foreach ([
            ['/@if\s*\(/', '/@endif\b/', "\n@endif"],
            ['/\{\{#items\}\}/', '/\{\{\/items\}\}/', "\n{{/items}}"],
        ] as [$openPattern, $closePattern, $closing]) {
            $open = preg_match_all($openPattern, $content);
            $close = preg_match_all($closePattern, $content);

            if ($open > $close) {
                $content .= str_repeat($closing, $open - $close);
                continue;
            }

            // إغلاق زائد بلا فتح: يُحذف من آخره حتى يتساويا
            for ($i = 0; $i < $close - $open; $i++) {
                $content = preg_replace($closePattern . 'u', '', $content, 1)
                    ?? $this->removeLastMatch($content, $closePattern);
            }
        }

        return $content;
    }

    /** يحذف آخر مطابقة للنمط — الإغلاق الزائد الأخير هو الأولى بالحذف */
    protected function removeLastMatch(string $content, string $pattern): string
    {
        if (! preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
            return $content;
        }

        [$text, $offset] = end($matches[0]);

        return substr($content, 0, $offset) . substr($content, $offset + strlen($text));
    }

    protected function placeholderToBlade(string $html): string
    {
        return $this->unwrapMarkedSpan($html, self::RAW_MARKER, 'data-raw', fn (string $value) => $value);
    }

    protected function chipToField(string $html): string
    {
        return $this->unwrapMarkedSpan($html, 'doc-field', 'data-field', fn (string $key) => '{{' . $key . '}}');
    }

    /**
     * يعيد كل `<span>` يحمل صنفاً وسمةً بعينهما إلى صيغته النصية.
     *
     * **ترتيب السمات ليس مضموناً.** المحرّر المرئي يعيد كتابة الوسم كما
     * يراه هو: نكتب `class` ثم `data-field`، ويعيدها هو `data-field` ثم
     * `contenteditable` ثم `class`. النمط الذي كان يشترط `class` قبل
     * `data-field` لم يكن يطابق شيئاً بعد أوّل فتح للمحرّر، فتُحفظ الشريحة
     * وسماً خاماً بدل رمز الحقل — يظهر في المعاينة نصّاً ميتاً، ويكسر
     * التصيير حين يمرّ مُحوّل الرموز على السمة نفسها. الفحص هنا يقرأ
     * السمات بعد استخراجها، فلا يهمّه ترتيبها.
     *
     * @param  callable(string): string  $replace
     */
    protected function unwrapMarkedSpan(string $html, string $class, string $attribute, callable $replace): string
    {
        return (string) preg_replace_callback(
            '/<span\b([^>]*)>(?:(?!<span\b).)*?<\/span>/is',
            function (array $match) use ($class, $attribute, $replace) {
                $attributes = $match[1];

                if (! $this->hasClass($attributes, $class)) {
                    return $match[0];
                }

                $value = $this->attributeValue($attributes, $attribute);

                return $value === null ? $match[0] : $replace($value);
            },
            $html
        );
    }

    /** قيمة سمة بعينها من نصّ سمات وسم — بأي نوع من علامات الاقتباس */
    protected function attributeValue(string $attributes, string $attribute): ?string
    {
        $pattern = '/\b' . preg_quote($attribute, '/') . '\s*=\s*(?:"([^"]*)"|\'([^\']*)\')/i';

        if (! preg_match($pattern, $attributes, $match)) {
            return null;
        }

        $value = ($match[1] ?? '') !== '' ? $match[1] : ($match[2] ?? '');

        return htmlspecialchars_decode($value, ENT_QUOTES);
    }

    /** هل يحمل الوسم هذا الصنف؟ الفحص على الأصناف كاملةً لا كنصّ جزئي */
    protected function hasClass(string $attributes, string $class): bool
    {
        $value = $this->attributeValue($attributes, 'class');

        return $value !== null && in_array($class, preg_split('/\s+/', trim($value)) ?: [], true);
    }

    protected function attributeToCondition(string $html): string
    {
        return preg_replace_callback(
            '/<(\w+)\b([^>]*?)\s*data-if="([^"]+)"([^>]*)>/i',
            function (array $match) {
                [, $tag, $before, $condition, $after] = $match;
                $key = str_replace('settings.', '', htmlspecialchars_decode($condition, ENT_QUOTES));

                // العنصر يُغلَّف لاحقاً — نضع علامة بداية هنا ونكملها بمرور ثانٍ
                return "@if(\$settings['{$key}'] ?? true)\n<{$tag}{$before}{$after} data-close-if=\"1\">";
            },
            $html
        );
    }

    protected function attributeToLoop(string $html): string
    {
        // إغلاق الشروط: كل عنصر يحمل `data-close-if` يُغلق بـ @endif بعد وسم إغلاقه
        $html = $this->closeMarkedElements($html, 'data-close-if', '@endif');

        // الأغلفة التي أضفناها نحن تُزال بالكامل قبل معالجة الصفوف الحقيقية
        $html = $this->unwrapAddedWrappers($html);

        // الصفوف المتكررة
        return $this->closeMarkedElements($html, 'data-repeat', '{{/items}}', '{{#items}}');
    }

    /**
     * يزيل الغلاف الذي أضافه `loopToAttribute` ويعيد الحلقة حول محتواه.
     *
     * بدون هذا يبقى الغلاف في المحتوى المحفوظ، فيُضاف غلاف جديد فوقه عند
     * الفتح التالي ويتراكم `<div>` إضافي مع كل حفظ.
     */
    protected function unwrapAddedWrappers(string $html): string
    {
        $pattern = '/<div\b[^>]*\sdata-repeat-wrapper="1"[^>]*>/i';

        while (preg_match($pattern, $html, $match, PREG_OFFSET_CAPTURE)) {
            $openStart = $match[0][1];
            $openEnd = $openStart + strlen($match[0][0]);

            $closeAt = $this->findClosingTag($html, 'div', $openEnd);

            if ($closeAt === null) {
                // غلاف بلا إغلاق: تُزال السمة فقط حتى لا تدور الحلقة بلا نهاية
                return preg_replace($pattern, '<div>', $html, 1);
            }

            $html = substr($html, 0, $openStart)
                . "{{#items}}\n"
                . substr($html, $openEnd, $closeAt - $openEnd)
                . "\n{{/items}}"
                . substr($html, $closeAt + strlen('</div>'));
        }

        return $html;
    }

    /**
     * يغلق العناصر المعلَّمة بسمة معيّنة بإضافة نص قبلها وبعد وسم إغلاقها.
     *
     * يتتبّع تداخل الوسم نفسه ليجد وسم الإغلاق الصحيح بدل أول وسم يصادفه.
     */
    protected function closeMarkedElements(string $html, string $attribute, string $closing, string $opening = ''): string
    {
        $offset = 0;

        while (preg_match('/<(\w+)\b[^>]*\s' . preg_quote($attribute, '/') . '="[^"]*"[^>]*>/i', $html, $match, PREG_OFFSET_CAPTURE, $offset)) {
            $tag = strtolower($match[1][0]);
            $openStart = $match[0][1];
            $openEnd = $openStart + strlen($match[0][0]);

            // إزالة السمة من الوسم نفسه
            $cleanTag = preg_replace('/\s' . preg_quote($attribute, '/') . '="[^"]*"/i', '', $match[0][0]);

            $closeAt = $this->findClosingTag($html, $tag, $openEnd);

            if ($closeAt === null) {
                // عنصر بلا وسم إغلاق (صورة مثلاً): يُغلق فوراً بعد وسمه.
                // تركه بلا إغلاق يترك `@if` معلّقاً فينهار تصريف القالب كله.
                $html = substr($html, 0, $openStart)
                    . ($opening !== '' ? $opening . "\n" : '')
                    . $cleanTag
                    . "\n" . $closing
                    . substr($html, $openEnd);

                $offset = $openStart + strlen($opening) + strlen($cleanTag) + strlen($closing) + 2;
                continue;
            }

            $closeTagLength = strlen('</' . $tag . '>');

            $html = substr($html, 0, $openStart)
                . ($opening !== '' ? $opening . "\n" : '')
                . $cleanTag
                . substr($html, $openEnd, $closeAt - $openEnd)
                . '</' . $tag . '>'
                . "\n" . $closing
                . substr($html, $closeAt + $closeTagLength);

            $offset = $openStart + strlen($opening) + strlen($cleanTag);
        }

        return $html;
    }

    /**
     * موضع وسم الإغلاق المطابق — يحترم التداخل.
     */
    protected function findClosingTag(string $html, string $tag, int $from): ?int
    {
        $depth = 1;
        $offset = $from;
        $pattern = '/<(\/?)' . preg_quote($tag, '/') . '\b[^>]*>/i';

        while (preg_match($pattern, $html, $match, PREG_OFFSET_CAPTURE, $offset)) {
            $isClosing = $match[1][0] === '/';
            $position = $match[0][1];

            $depth += $isClosing ? -1 : 1;

            if ($depth === 0) {
                return $position;
            }

            $offset = $position + strlen($match[0][0]);
        }

        return null;
    }

    /** نمط regex يطابق أي مفتاح حقل عام */
    protected function fieldPattern(): string
    {
        return implode('|', array_map(
            fn ($key) => preg_quote($key, '/'),
            DocumentFields::keys()
        ));
    }
}
