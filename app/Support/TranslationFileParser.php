<?php

namespace App\Support;

use RuntimeException;

/**
 * قارئ ملفات الترجمة — JSON و PHP.
 *
 * ملف الترجمة **بيانات لا برنامج**. لو ضُمِّن ملف PHP مرفوع بـ include
 * لنُفِّذ كل ما فيه على الخادم، وهي ثغرة تنفيذ أوامر عن بُعد كاملة يستطيع
 * عبرها أي حساب إداري مخترَق السيطرة على الخادم.
 *
 * لذلك لا يُنفَّذ الملف إطلاقاً: يُقرأ نصاً، ويُفكَّك بمحلّل لغوي
 * (token_get_all) يقبل **مصفوفة حرفية فقط**. أي متغيّر أو استدعاء دالة أو
 * تضمين أو أي بنية تنفيذية تُرفض. والمكتوب على القرص لاحقاً يُولَّد من
 * المصفوفة المفهومة لا من الملف المرفوع.
 *
 * ما يُقبل داخل الملف:
 *   - علامة BOM أو أسطر فارغة قبل \u200E<?php، وملف بلا وسم فتح أصلاً
 *   - \u200Edeclare(strict_types=1);\u200E قبل الـ return
 *   - المصفوفات بالشكلين \u200E[...]\u200E و \u200Earray(...)\u200E مع الفاصلة الأخيرة
 *   - النصوص المفردة والمزدوجة و heredoc/nowdoc الخالية من المتغيّرات
 *   - ربط النصوص بالنقطة: \u200E'a' . 'b'
 *   - الأرقام موجبة وسالبة، و true/false/null
 */
class TranslationFileParser
{
    /** القيم الاسمية الوحيدة المقبولة داخل المصفوفة */
    protected const ALLOWED_CONSTANTS = ['true' => true, 'false' => false, 'null' => null];

    /**
     * يفكّك محتوى ملف ترجمة ويعيد مصفوفته.
     *
     * @throws RuntimeException إن كان المحتوى غير صالح أو يحوي بنية تنفيذية
     */
    public static function parse(string $contents, string $extension): array
    {
        return match (strtolower($extension)) {
            'json' => self::parseJson($contents),
            'php' => self::parsePhp($contents),
            default => throw new RuntimeException(__('languages.validation.translation_file_type')),
        };
    }

    protected static function parseJson(string $contents): array
    {
        $decoded = json_decode(self::stripBom($contents), true);

        if (! is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException(__('languages.validation.translation_file_invalid_json'));
        }

        return $decoded;
    }

    /**
     * يقبل الشكل القياسي لملفات لغة لارافيل:
     *   <?php return ['key' => 'value', 'nested' => ['a' => 'b']];
     */
    protected static function parsePhp(string $contents): array
    {
        $tokens = self::tokenize(self::normalizeSource($contents));

        if ($tokens === []) {
            throw new RuntimeException(__('languages.validation.translation_file_empty'));
        }

        if (! self::isType($tokens[0], T_OPEN_TAG)) {
            throw self::fail('translation_file_must_return', $tokens[0]);
        }

        $index = 1;
        self::skipDeclare($tokens, $index);

        if (! self::isType($tokens[$index] ?? null, T_RETURN)) {
            throw self::fail('translation_file_must_return', $tokens[$index] ?? null);
        }

        $index++;
        $value = self::parseValue($tokens, $index);

        if (! is_array($value)) {
            throw self::fail('translation_file_must_return', $tokens[$index - 1] ?? null);
        }

        // بعد الفاصلة المنقوطة لا يُسمح إلا بوسم إغلاق
        if (self::isChar($tokens[$index] ?? null, ';')) {
            $index++;
        }

        foreach (array_slice($tokens, $index) as $token) {
            if (self::isType($token, T_CLOSE_TAG)) {
                continue;
            }

            // بعد وسم الإغلاق لا يُقبل إلا سطر فارغ: أي نصّ آخر يعني ملفاً ليس ملف ترجمة
            if (self::isType($token, T_INLINE_HTML) && trim($token['text']) === '') {
                continue;
            }

            throw self::fail('translation_file_extra_code', $token);
        }

        return $value;
    }

    /* ==================== تجهيز المصدر ==================== */

    /**
     * أخطاء شائعة لا علاقة لها بمحتوى الترجمة تُصلَّح هنا بدل رفض الملف:
     * علامة BOM التي يضيفها بعض المحرّرات، وأسطر فارغة قبل \u200E<?php،
     * وملف كُتب بلا وسم فتح أصلاً.
     */
    protected static function normalizeSource(string $contents): string
    {
        $contents = self::stripBom($contents);

        // فراغ قبل وسم الفتح يجعل التوكن الأول نصاً خارجياً فيُرفض الملف
        if (ltrim($contents) !== '' && str_starts_with(ltrim($contents), '<?')) {
            return ltrim($contents);
        }

        // ملف بلا وسم فتح: يُقبل إن كان return أو مصفوفة مباشرة
        $trimmed = ltrim($contents);
        if ($trimmed !== '' && ! str_contains($contents, '<?')) {
            $body = str_starts_with(strtolower($trimmed), 'return') ? $trimmed : 'return '.$trimmed;

            return "<?php\n".$body;
        }

        return $contents;
    }

    protected static function stripBom(string $contents): string
    {
        foreach (["\xEF\xBB\xBF", "\xFF\xFE", "\xFE\xFF"] as $bom) {
            if (str_starts_with($contents, $bom)) {
                return substr($contents, strlen($bom));
            }
        }

        return $contents;
    }

    /**
     * يحوّل الملف إلى رموز موحّدة الشكل ['type','text','line'] بعد إسقاط
     * الفراغات والتعليقات. التوحيد ضروري ليحمل كل رمز رقم سطره فتصير
     * رسائل الخطأ دالّة على موضع المشكلة بدل أن تكون عامة.
     */
    protected static function tokenize(string $contents): array
    {
        $raw = @token_get_all($contents);
        $normalized = [];
        $line = 1;

        foreach ($raw as $token) {
            if (is_array($token)) {
                $line = $token[2];

                if (in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                    $line += substr_count($token[1], "\n");

                    continue;
                }

                $normalized[] = ['type' => $token[0], 'text' => $token[1], 'line' => $token[2]];
                $line += substr_count($token[1], "\n");

                continue;
            }

            $normalized[] = ['type' => $token, 'text' => $token, 'line' => $line];
        }

        return $normalized;
    }

    /** تخطّي \u200Edeclare(strict_types=1);\u200E إن وُجد قبل الـ return */
    protected static function skipDeclare(array $tokens, int &$index): void
    {
        while (self::isType($tokens[$index] ?? null, T_DECLARE)) {
            while (isset($tokens[$index]) && ! self::isChar($tokens[$index], ';')) {
                $index++;
            }
            $index++;
        }
    }

    /* ==================== التفكيك ==================== */

    protected static function isType($token, int $type): bool
    {
        return is_array($token) && $token['type'] === $type;
    }

    protected static function isChar($token, string $char): bool
    {
        return is_array($token) && $token['type'] === $char;
    }

    /**
     * قيمة واحدة، مع السماح بربط النصوص الثابتة بالنقطة.
     */
    protected static function parseValue(array $tokens, int &$index)
    {
        $value = self::parseOperand($tokens, $index);

        while (self::isChar($tokens[$index] ?? null, '.')) {
            $operator = $tokens[$index];
            $index++;
            $next = self::parseOperand($tokens, $index);

            if (is_array($value) || is_array($next)) {
                throw self::fail('translation_file_invalid_php', $operator);
            }

            $value = self::stringify($value).self::stringify($next);
        }

        return $value;
    }

    /**
     * قيمة مفردة: مصفوفة أو نص أو رقم أو true/false/null.
     */
    protected static function parseOperand(array $tokens, int &$index)
    {
        $token = $tokens[$index] ?? null;

        if ($token === null) {
            throw self::fail('translation_file_unexpected_end', $tokens[$index - 1] ?? null);
        }

        // array( ... )
        if (self::isType($token, T_ARRAY)) {
            $index++;
            if (! self::isChar($tokens[$index] ?? null, '(')) {
                throw self::fail('translation_file_invalid_php', $tokens[$index] ?? $token);
            }
            $index++;

            return self::parseArray($tokens, $index, ')');
        }

        // [ ... ]
        if (self::isChar($token, '[')) {
            $index++;

            return self::parseArray($tokens, $index, ']');
        }

        // نص بين علامات اقتباس بلا إقحام متغيّرات
        if (self::isType($token, T_CONSTANT_ENCAPSED_STRING)) {
            $index++;

            return self::decodeString($token['text']);
        }

        // heredoc / nowdoc بلا متغيّرات
        if (self::isType($token, T_START_HEREDOC)) {
            return self::parseHeredoc($tokens, $index);
        }

        // رقم موجب
        if (self::isType($token, T_LNUMBER)) {
            $index++;

            return (int) $token['text'];
        }

        if (self::isType($token, T_DNUMBER)) {
            $index++;

            return (float) $token['text'];
        }

        // رقم سالب
        if (self::isChar($token, '-') || self::isChar($token, '+')) {
            $sign = $token['type'] === '-' ? -1 : 1;
            $index++;
            $next = $tokens[$index] ?? null;

            if (self::isType($next, T_LNUMBER)) {
                $index++;

                return $sign * (int) $next['text'];
            }
            if (self::isType($next, T_DNUMBER)) {
                $index++;

                return $sign * (float) $next['text'];
            }

            throw self::fail('translation_file_invalid_php', $next ?? $token);
        }

        // true / false / null حصراً — أي اسم آخر يعني ثابتاً أو استدعاء دالة
        if (self::isType($token, T_STRING)) {
            $name = strtolower($token['text']);

            if (array_key_exists($name, self::ALLOWED_CONSTANTS)) {
                $index++;

                return self::ALLOWED_CONSTANTS[$name];
            }
        }

        throw self::fail('translation_file_unsafe', $token);
    }

    protected static function parseArray(array $tokens, int &$index, string $closing): array
    {
        $result = [];

        while (true) {
            $token = $tokens[$index] ?? null;

            if ($token === null) {
                throw self::fail('translation_file_unclosed_array', $tokens[$index - 1] ?? null);
            }

            if (self::isChar($token, $closing)) {
                $index++;

                return $result;
            }

            $first = self::parseValue($tokens, $index);

            if (self::isType($tokens[$index] ?? null, T_DOUBLE_ARROW)) {
                $arrow = $tokens[$index];
                $index++;
                $value = self::parseValue($tokens, $index);

                if (! is_string($first) && ! is_int($first)) {
                    throw self::fail('translation_file_invalid_key', $arrow);
                }

                $result[$first] = $value;
            } else {
                $result[] = $first;
            }

            $next = $tokens[$index] ?? null;

            if (self::isChar($next, ',')) {
                $index++;

                continue;
            }

            if (self::isChar($next, $closing)) {
                $index++;

                return $result;
            }

            if ($next === null) {
                throw self::fail('translation_file_unclosed_array', $tokens[$index - 1] ?? null);
            }

            throw self::fail('translation_file_missing_comma', $next);
        }
    }

    /**
     * heredoc و nowdoc: مقبولان ما داما بلا إقحام متغيّرات — وهو ما يظهر
     * كرمز T_VARIABLE أو T_CURLY_OPEN بين بداية النص ونهايته.
     */
    protected static function parseHeredoc(array $tokens, int &$index): string
    {
        $start = $tokens[$index];
        $isNowdoc = str_contains($start['text'], "'");
        $index++;
        $buffer = '';

        while (isset($tokens[$index]) && ! self::isType($tokens[$index], T_END_HEREDOC)) {
            if (! self::isType($tokens[$index], T_ENCAPSED_AND_WHITESPACE)) {
                throw self::fail('translation_file_unsafe', $tokens[$index]);
            }

            $buffer .= $tokens[$index]['text'];
            $index++;
        }

        if (! isset($tokens[$index])) {
            throw self::fail('translation_file_unexpected_end', $start);
        }

        $index++;

        return $isNowdoc ? $buffer : self::decodeDoubleQuoted($buffer);
    }

    protected static function stringify($value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '';
        }

        return (string) $value;
    }

    /**
     * يفكّ ترميز نصّ حرفي. الرمز T_CONSTANT_ENCAPSED_STRING لا يُنتَج أصلاً
     * إلا للنصوص الخالية من إقحام المتغيّرات، فلا خطر تنفيذ هنا.
     */
    protected static function decodeString(string $raw): string
    {
        $quote = $raw[0] ?? "'";
        $inner = substr($raw, 1, -1);

        if ($quote === "'") {
            return str_replace(['\\\\', "\\'"], ['\\', "'"], $inner);
        }

        return self::decodeDoubleQuoted($inner);
    }

    protected static function decodeDoubleQuoted(string $inner): string
    {
        $map = [
            '\\n' => "\n", '\\r' => "\r", '\\t' => "\t", '\\v' => "\v",
            '\\f' => "\f", '\\e' => "\e", '\\\\' => '\\', '\\$' => '$', '\\"' => '"',
        ];

        $decoded = strtr($inner, $map);

        // \xHH و \u{HHHH}
        $decoded = preg_replace_callback('/\\\\x([0-9A-Fa-f]{1,2})/', fn ($m) => chr(hexdec($m[1])), $decoded);

        return preg_replace_callback('/\\\\u\{([0-9A-Fa-f]+)\}/', fn ($m) => mb_chr(hexdec($m[1]), 'UTF-8'), $decoded);
    }

    /* ==================== الأخطاء ==================== */

    /**
     * رسالة خطأ تدلّ على موضع المشكلة: الرسالة العامة وحدها كانت تترك
     * المستخدم يخمّن أي سطر في ملف من مئات الأسطر هو السبب.
     */
    protected static function fail(string $key, $token = null): RuntimeException
    {
        $message = __('languages.validation.'.$key);

        if (is_array($token)) {
            $message .= ' '.__('languages.validation.at_line', ['line' => $token['line']]);

            $near = trim((string) $token['text']);
            if ($near !== '' && mb_strlen($near) <= 40) {
                $message .= ' '.__('languages.validation.near_token', ['token' => $near]);
            }
        }

        return new RuntimeException($message);
    }
}
