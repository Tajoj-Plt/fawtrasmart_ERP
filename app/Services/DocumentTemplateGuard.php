<?php

namespace App\Services;

use Illuminate\Support\Facades\Blade;

/**
 * حارسُ قوالب المستندات — يُبقي لغةَ القالب لغةَ حقولٍ وشروط، لا لغةَ برمجة.
 *
 * ══════════════════ ما كان ══════════════════
 *
 * [DocumentTemplateRenderer] يحوّل رموزَ القالب إلى Blade ثمّ يصيّر الناتج
 * بـ`Blade::render()` — أي أنّ **ما يكتبه المستخدم في محرّر القوالب يُصرَّف
 * ويُنفَّذ PHP على الخادم**. والحمايةُ كانت أربعةَ تعابير نمطيّة تحذف
 * `<?php … ?>` المغلقة ووسمَ `<script>`؛ فيمرّ منها:
 *
 *   • التوجيهاتُ كلُّها: `@php … @endphp`، `@include`، `@inject`…
 *   • أيُّ تعبيرٍ يبدأ بـ`$`: `{{ $x . shell_exec('…') }}`
 *   • وسمُ PHP غيرُ المغلق: `<?php system('…');` بلا `?>`
 *   • شرطٌ فيه نداء: `@if(system('…'))`
 *
 * ولا يلزم حتى حفظُ القالب: مسارُ المعاينة يصيّر ما يُرسَل إليه مباشرةً.
 * فكلُّ من يملك «تعديل الإعدادات العامة» كان يملك الخادمَ نفسَه.
 *
 * ══════════════════ ما صار ══════════════════
 *
 * يُقرأ ما كتبه المستخدم **قبل** تحويل الرموز، ويُترك منه ثلاثةُ أشياء فقط —
 * وهي كلُّ ما تستعمله القوالبُ الثلاثمئة والثمانيةُ والثلاثون المخزَّنة:
 *
 *   ١) رمزُ حقل:   `{{ document.number }}`  `{{#items}}`  `{{/items}}`
 *   ٢) شرطٌ بسيط:  `@if(!empty(business.logo))`  `@if(totals.tax > 0)`
 *                  مع `@elseif` و`@else` و`@endif` و`@unless` و`@endunless`
 *   ٣) HTML وCSS عاديّان (وقواعدُ CSS مثل `@media` و`@page` ليست توجيهات)
 *
 * وما عدا ذلك لا يُحذف — يُطبع **نصّاً كما كُتب**: التوجيهُ يُهرَّب بـ`@@`،
 * والتعبيرُ بـ`@{{`، ووسمُ PHP يصير `&lt;?`. فمن كتب شيئاً غير مدعوم يراه في
 * المعاينة حرفاً بحرف ويعرف أنه لم يُنفَّذ، بدل أن يختفي بلا أثر.
 *
 * والأنماطُ (CSS) تُلصق بالقالب نفسِه قبل التصيير، فتمرّ على الحارس كذلك —
 * وليس فيها شروطٌ ولا حقول، فكلُّ ما يشبه Blade فيها يُهرَّب.
 */
class DocumentTemplateGuard
{
    /** التوجيهاتُ المسموحة — شروطٌ فقط. */
    protected const ALLOWED_DIRECTIVES = ['if', 'elseif', 'else', 'endif', 'unless', 'endunless'];

    /** ما يُفحص شرطُه من المسموح. */
    protected const CONDITIONAL_DIRECTIVES = ['if', 'elseif', 'unless'];

    /** الدوالُّ المسموحة داخل الشرط — قراءةٌ بلا أثر. */
    protected const ALLOWED_FUNCTIONS = ['empty', 'isset', 'count', 'is_numeric', 'floatval', 'intval', 'abs', 'trim'];

    /** كلماتٌ من اللغة لا مكان لها في شرط قالب. */
    protected const FORBIDDEN_WORDS = [
        'new', 'include', 'include_once', 'require', 'require_once', 'eval', 'exit', 'die',
        'print', 'echo', 'function', 'fn', 'use', 'global', 'static', 'clone', 'yield', 'throw',
        'unset', 'list', 'goto', 'declare',
    ];

    /** محتوى القالب (HTML برموزه وشروطه). */
    public function content(string $html): string
    {
        $html = $this->neutralizePhpTags($html);
        $html = $this->neutralizeComponentTags($html);
        $html = $this->neutralizeEchoes($html, allowTokens: true);

        return $this->neutralizeDirectives($html, allowConditionals: true);
    }

    /** أنماطُ القالب (CSS) — لا حقولَ فيها ولا شروط. */
    public function styles(string $css): string
    {
        $css = $this->neutralizePhpTags($css);
        // وسمُ الإغلاق يُخرج المتصفّحَ من كتلة الأنماط إلى HTML حرّ
        $css = (string) preg_replace('/<\s*\/\s*style/i', '&lt;/style', $css);
        $css = $this->neutralizeEchoes($css, allowTokens: false);

        return $this->neutralizeDirectives($css, allowConditionals: false);
    }

    // ═══════════════════════ وسوم PHP ═══════════════════════

    /**
     * `<?` بكل صوره — مغلقاً أو غيرَ مغلق. Blade لا يمسّ PHP الخام، والملفُّ
     * المصرَّف يُضمَّن كما هو، فوسمٌ مفتوحٌ بلا إغلاق يُنفَّذ حتى آخر القالب.
     */
    protected function neutralizePhpTags(string $text): string
    {
        return str_replace(['<?', '?>'], ['&lt;?', '?&gt;'], $text);
    }

    /**
     * وسومُ مكوّنات Blade (`<x-…>` و`<livewire:…>`): المصرِّف يحوّلها إلى
     * إنشاء صنفٍ، وخصائصُها المسبوقة بنقطتين (`:type="…"`) تعابيرُ PHP
     * تُنفَّذ. القالبُ المطبوع لا يستعمل مكوّنات، فتُطبع نصّاً.
     */
    protected function neutralizeComponentTags(string $text): string
    {
        return (string) preg_replace('/<(\s*\/?\s*)(x[-:]|livewire:)/i', '&lt;$1$2', $text);
    }

    // ═══════════════════════ التعبيرات ═══════════════════════

    /**
     * `{{ … }}` و`{!! … !!}`: يبقى رمزُ الحقل، ويُطبع غيرُه نصّاً.
     *
     * رمزُ الحقل اسمٌ بنقاط (`document.number`، `item.kind`) تسبقه `$`
     * اختياراً، أو فاتحُ/غالقُ حلقة البنود. وما فيه قوسٌ أو سهمٌ أو عامل
     * ليس رمزاً — ولو بدأ بـ`$` كما كان يكفي ليمرّ.
     */
    protected function neutralizeEchoes(string $text, bool $allowTokens): string
    {
        $isToken = fn (string $inner): bool => $allowTokens
            && preg_match('/^\s*(?:\$?[A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)*|[#\/]items)\s*$/', $inner) === 1;

        // تعليقُ Blade `{{-- --}}` يُحذف عند التصريف ولا يُنفَّذ — يُترك
        $text = (string) preg_replace_callback(
            '/(?<!@)\{\{(?!--)(.*?)\}\}/s',
            fn (array $m) => $isToken($m[1]) ? $m[0] : '@'.$m[0],
            $text
        );

        return (string) preg_replace_callback(
            '/(?<!@)\{!!(.*?)!!\}/s',
            fn (array $m) => $isToken($m[1]) ? $m[0] : '@'.$m[0],
            $text
        );
    }

    // ═══════════════════════ التوجيهات ═══════════════════════

    /**
     * كلُّ `@كلمة` هي توجيهُ Blade حقيقيّ — مبنيّاً كان أو مسجَّلاً من حزمة —
     * تُهرَّب إلّا الشروطَ المسموحة بشرطٍ سليم. و`@media` و`@page` وأخواتُها
     * ليست توجيهات، فلا تُمسّ ويبقى CSS كما كُتب.
     */
    protected function neutralizeDirectives(string $text, bool $allowConditionals): string
    {
        $out = '';
        $offset = 0;
        $length = strlen($text);

        while (preg_match('/(?<![A-Za-z0-9_@])@([A-Za-z_][A-Za-z0-9_]*)/', $text, $m, PREG_OFFSET_CAPTURE, $offset)) {
            $at = $m[0][1];
            $name = $m[1][0];
            $afterName = $at + strlen($m[0][0]);

            $out .= substr($text, $offset, $at - $offset);
            $lower = strtolower($name);

            if (! $this->isBladeDirective($name)) {
                // قاعدةُ CSS أو بريدٌ أو نصّ — ليس من شأن Blade
                $out .= $m[0][0];
                $offset = $afterName;

                continue;
            }

            if ($allowConditionals && in_array($lower, self::ALLOWED_DIRECTIVES, true)) {
                if (! in_array($lower, self::CONDITIONAL_DIRECTIVES, true)) {
                    $out .= $m[0][0];
                    $offset = $afterName;

                    continue;
                }

                [$condition, $end] = $this->readParenthesised($text, $afterName, $length);

                if ($condition !== null && $this->isSafeCondition($condition)) {
                    $out .= substr($text, $at, $end - $at);
                    $offset = $end;

                    continue;
                }
            }

            // توجيهٌ غير مسموح، أو شرطٌ فيه ما ليس شرطاً: يُطبع نصّاً
            $out .= '@'.$m[0][0];
            $offset = $afterName;
        }

        return $out.substr($text, $offset);
    }

    /** أهذا الاسمُ توجيهٌ يعرفه مصرِّف Blade؟ */
    protected function isBladeDirective(string $name): bool
    {
        static $custom = null;

        if ($custom === null) {
            $custom = array_change_key_case(Blade::getCustomDirectives(), CASE_LOWER);
        }

        return isset($custom[strtolower($name)])
            || method_exists(Blade::getFacadeRoot(), 'compile'.ucfirst($name))
            // كتلتا `@php` و`@verbatim` يعالجهما المصرِّف بتعبيرٍ نمطيّ قبل التوجيهات
            || in_array(strtolower($name), ['php', 'endphp', 'verbatim', 'endverbatim'], true);
    }

    /**
     * يقرأ `( … )` الموزونة بعد اسم التوجيه.
     *
     * @return array{0: ?string, 1: int} ما بين القوسين، وموضعُ ما بعد الغالق
     */
    protected function readParenthesised(string $text, int $from, int $length): array
    {
        $i = $from;
        while ($i < $length && ($text[$i] === ' ' || $text[$i] === "\t")) {
            $i++;
        }

        if ($i >= $length || $text[$i] !== '(') {
            return [null, $from];
        }

        $depth = 0;
        $quote = null;

        for ($j = $i; $j < $length; $j++) {
            $char = $text[$j];

            if ($quote !== null) {
                if ($char === '\\') {
                    $j++;
                } elseif ($char === $quote) {
                    $quote = null;
                }

                continue;
            }

            if ($char === '\'' || $char === '"') {
                $quote = $char;
            } elseif ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;

                if ($depth === 0) {
                    return [substr($text, $i + 1, $j - $i - 1), $j + 1];
                }
            }
        }

        return [null, $from];
    }

    /**
     * شرطُ قالب: مقارناتٌ بين حقولٍ وأرقامٍ ونصوص، تجمعها `&&` و`||` و`!`.
     *
     * يُفحص بمحلِّل PHP نفسِه لا بتعبيرٍ نمطيّ: كلُّ رمزٍ في الشرط إمّا من
     * المسموح صراحةً وإمّا يُرفض الشرطُ كلُّه.
     */
    protected function isSafeCondition(string $condition): bool
    {
        if (trim($condition) === '' || strlen($condition) > 500) {
            return false;
        }

        try {
            $tokens = token_get_all('<?php '.$condition.';');
        } catch (\Throwable) {
            return false;
        }

        array_shift($tokens);   // `<?php `
        array_pop($tokens);     // `;` التي أضفناها

        $allowedIds = [
            T_STRING, T_VARIABLE, T_LNUMBER, T_DNUMBER, T_CONSTANT_ENCAPSED_STRING, T_WHITESPACE,
            T_IS_EQUAL, T_IS_IDENTICAL, T_IS_NOT_EQUAL, T_IS_NOT_IDENTICAL,
            T_IS_GREATER_OR_EQUAL, T_IS_SMALLER_OR_EQUAL,
            T_BOOLEAN_AND, T_BOOLEAN_OR, T_LOGICAL_AND, T_LOGICAL_OR,
            T_EMPTY, T_ISSET, T_COALESCE,
        ];
        $allowedChars = ['(', ')', '!', '<', '>', '.', '[', ']', '+', '-', '*', '/', ','];

        $count = count($tokens);

        // آخرُ رمزٍ ذي معنى قبل الحاليّ — به يُعرف القوسُ: تجميعٌ هو أم نداء
        $previous = null;

        foreach ($tokens as $index => $token) {
            if (is_string($token)) {
                if (! in_array($token, $allowedChars, true)) {
                    return false;   // `=` إسناد، `;`، `{`، علامة التنفيذ الخلفية…
                }

                /* قوسٌ بعد متغيّرٍ أو نصٍّ أو قوسٍ مغلق نداءٌ لا تجميع:
                   `$f('x')` و`'system'('x')` و`(…)('x')` كلُّها تُنفَّذ. */
                if ($token === '(' && $previous !== null
                    && ($previous === ')' || $previous === ']'
                        || (is_array($previous) && in_array($previous[0], [T_VARIABLE, T_CONSTANT_ENCAPSED_STRING], true)))) {
                    return false;
                }

                $previous = $token;

                continue;
            }

            [$id, $text] = $token;

            if (! in_array($id, $allowedIds, true)) {
                return false;       // `->` `::` `new` `include` نصٌّ بمتغيّرات…
            }

            if ($id !== T_WHITESPACE) {
                $previous = $token;
            }

            if ($id !== T_STRING) {
                continue;
            }

            $word = strtolower($text);

            if (in_array($word, self::FORBIDDEN_WORDS, true)) {
                return false;
            }

            // اسمٌ يليه قوس = نداءُ دالّة: لا يُقبل إلّا من القائمة
            $next = $index + 1;
            while ($next < $count && is_array($tokens[$next]) && $tokens[$next][0] === T_WHITESPACE) {
                $next++;
            }

            if ($next < $count && $tokens[$next] === '(' && ! in_array($word, self::ALLOWED_FUNCTIONS, true)) {
                return false;
            }
        }

        return true;
    }
}
