<?php

namespace Modules\Sitting\Services;

use App\Models\SystemLanguage;
use App\Support\TranslationFileParser;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Throwable;

/**
 * قراءة وكتابة مفاتيح الترجمة لكل لغة من شاشات الإدارة.
 *
 * لغة واحدة قد تملك عدة ملفات:
 *   resources/lang/{code}/{group}.php   → مجموعات (auth, messages, …)
 *   resources/lang/{code}.json          → ترجمة النصّ الكامل
 *
 * ملفات PHP تُقرأ بـ TranslationFileParser ولا تُضمَّن ولا تُنفَّذ إطلاقاً،
 * تماماً كالملفات المرفوعة — فمصدر الملف على القرص لا يغيّر أن محتواه
 * بيانات يحرّرها مستخدم، لا كوداً موثوقاً.
 */
class TranslationEditorService
{
    /** اسم المجموعة الافتراضي لملف JSON — ليس ملف مجموعات فلا اسم له */
    public const JSON_GROUP = '__json';

    /** حد أقصى للمفاتيح في الملف الواحد يمنع تعليق الشاشة على ملف ضخم */
    public const MAX_ENTRIES = 5000;

    /**
     * كل مجموعات الترجمة للغة، جاهزة للعرض في الجدول.
     *
     * @return array<int, array{name:string,label:string,relative:string,type:string,entries:array,error:?string,exists:bool}>
     */
    public function groups(SystemLanguage $language): array
    {
        $code = $this->safeCode($language->code);
        $groups = [];

        // ملفات المجموعات: resources/lang/{code}/*.php
        $directory = base_path("resources/lang/{$code}");
        if (File::isDirectory($directory)) {
            $files = collect(File::files($directory))
                ->filter(fn ($file) => strtolower($file->getExtension()) === 'php')
                ->sortBy(fn ($file) => $file->getFilename());

            foreach ($files as $file) {
                $groups[] = $this->readGroup($code, $file->getFilenameWithoutExtension(), 'php');
            }
        }

        // ملف JSON للغة
        if (File::exists(base_path("resources/lang/{$code}.json"))) {
            $groups[] = $this->readGroup($code, self::JSON_GROUP, 'json');
        }

        return $groups;
    }

    /** إجمالي المفاتيح في كل ملفات اللغة */
    public function totalEntries(array $groups): int
    {
        return array_sum(array_map(fn ($group) => count($group['entries']), $groups));
    }

    /**
     * قراءة مجموعة واحدة. الملف غير المقروء لا يُسقط الشاشة: يُعرض
     * مع سبب تعذّر قراءته حتى يعرف المستخدم ما الذي يصلحه.
     */
    public function readGroup(string $code, string $group, string $type): array
    {
        $relative = $this->relativePath($code, $group, $type);
        $absolute = base_path($relative);
        $entries = [];
        $error = null;
        $exists = File::exists($absolute);

        if ($exists) {
            try {
                $data = TranslationFileParser::parse(File::get($absolute), $type);
                $entries = $type === 'json' ? $this->flattenJson($data) : Arr::dot($data);
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }

        return [
            'name' => $group,
            'label' => $group === self::JSON_GROUP ? "{$code}.json" : "{$group}.php",
            'relative' => $relative,
            'type' => $type,
            'entries' => array_map(fn ($value) => $this->displayValue($value), $entries),
            'raw' => $entries,
            'error' => $error,
            'exists' => $exists,
        ];
    }

    /**
     * تطبيق تعديلات الجدول القادمة من الشاشات.
     *
     * الحمولة نصّ JSON واحد لا حقول منفصلة، لأن ملف ترجمة بمئات المفاتيح
     * يتجاوز حد max_input_vars في PHP (1000 افتراضياً) فتصل الحقول ناقصة
     * ويُكتب الملف مبتوراً — والحمولة الواحدة لا تخضع لهذا الحد.
     *
     * @return array<int, string> مسارات الملفات التي تغيّرت فعلاً
     */
    public function applyPayload(SystemLanguage $language, ?string $payload): array
    {
        if (blank($payload)) {
            return [];
        }

        $decoded = json_decode($payload, true);

        if (! is_array($decoded) || ! isset($decoded['groups']) || ! is_array($decoded['groups'])) {
            throw new RuntimeException(__('languages.validation.translations_payload_invalid'));
        }

        $code = $this->safeCode($language->code);
        $saved = [];

        foreach ($decoded['groups'] as $group) {
            if (! is_array($group) || ! isset($group['name'])) {
                continue;
            }

            $type = ($group['type'] ?? 'php') === 'json' ? 'json' : 'php';
            $name = $type === 'json' ? self::JSON_GROUP : $this->safeGroup((string) $group['name']);
            $entries = is_array($group['entries'] ?? null) ? $group['entries'] : [];

            if (count($entries) > self::MAX_ENTRIES) {
                throw new RuntimeException(__('languages.validation.translations_too_many', ['max' => self::MAX_ENTRIES]));
            }

            $written = $this->write($code, $name, $type, $entries);

            if ($written['changed']) {
                $saved[] = $written['relative'];
            }
        }

        return $saved;
    }

    /**
     * كتابة مجموعة على القرص.
     *
     * لا يُكتب شيء إن تطابق المحتوى مع الموجود — حتى الرفع لا يستثنى من ذلك:
     * إعادة توليد ملف قائم بمفاتيح مطابقة تمحو تعليقاته وتنسيقه بلا أي مقابل.
     *
     * @param  bool  $force  استبدال الملف حتى لو تعذّرت قراءة الحالي — للرفع
     *                       حيث نيّة المستخدم استبدال الملف لا تعديله
     * @return array{relative:string,changed:bool}
     */
    public function write(string $code, string $group, string $type, array $entries, bool $force = false): array
    {
        $clean = [];

        foreach ($entries as $key => $value) {
            $key = trim((string) $key);

            if ($key === '') {
                continue;
            }

            $clean[$key] = is_scalar($value) || $value === null ? $value : (string) json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        $current = $this->readGroup($code, $group, $type);

        // الملف الموجود الذي تعذّرت قراءته لا يُستبدل بصمت — قد يحوي ما لم يُعرض
        if (! $force && $current['exists'] && $current['error'] !== null) {
            throw new RuntimeException(__('languages.validation.translations_unreadable', [
                'file' => $current['relative'],
                'error' => $current['error'],
            ]));
        }

        $relative = $this->relativePath($code, $group, $type);

        $currentValues = array_map(fn ($value) => $this->displayValue($value), $current['raw']);
        $newValues = array_map(fn ($value) => $this->displayValue($value), $clean);

        if ($current['exists'] && $current['error'] === null && $currentValues === $newValues) {
            return ['relative' => $relative, 'changed' => false];
        }

        // القيمة التي لم تتغيّر تحتفظ بنوعها الأصلي (رقم/منطقي) بدل أن تصير نصاً
        foreach ($clean as $key => $value) {
            if (array_key_exists($key, $current['raw']) && $this->displayValue($current['raw'][$key]) === $this->displayValue($value)) {
                $clean[$key] = $current['raw'][$key];
            }
        }

        $absolute = base_path($relative);

        File::ensureDirectoryExists(dirname($absolute));

        if ($type === 'json') {
            File::put($absolute, json_encode($clean, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
        } else {
            File::put($absolute, $this->renderPhp(Arr::undot($clean)));
        }

        $this->forgetTranslationCache();

        return ['relative' => $relative, 'changed' => true];
    }

    /** حذف ملف مجموعة كاملة */
    public function deleteGroup(SystemLanguage $language, string $group, string $type): bool
    {
        $code = $this->safeCode($language->code);
        $name = $type === 'json' ? self::JSON_GROUP : $this->safeGroup($group);
        $absolute = base_path($this->relativePath($code, $name, $type));

        if (! File::exists($absolute)) {
            return false;
        }

        File::delete($absolute);
        $this->forgetTranslationCache();

        return true;
    }

    /* ==================== أدوات ==================== */

    public function relativePath(string $code, string $group, string $type): string
    {
        $code = $this->safeCode($code);

        if ($type === 'json' || $group === self::JSON_GROUP) {
            return "resources/lang/{$code}.json";
        }

        return "resources/lang/{$code}/{$this->safeGroup($group)}.php";
    }

    /** الرمز يُبنى منه مسار: يُقيَّد حتى لا يخرج عن مجلد اللغات */
    public function safeCode(?string $code): string
    {
        $safe = preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $code));

        if ($safe === '') {
            throw new RuntimeException(__('languages.validation.code_format'));
        }

        return $safe;
    }

    /** اسم المجموعة يصير اسم ملف: نفس التقييد ونفس السبب */
    public function safeGroup(string $group): string
    {
        $safe = preg_replace('/[^a-z0-9_-]/', '', strtolower(trim($group)));

        return $safe !== '' ? $safe : 'messages';
    }

    /**
     * ملف JSON مفاتيحه جمل كاملة قد تحوي نقاطاً، فلا يُسطَّح بـ Arr::dot
     * وإلا تفكّكت المفاتيح وضاعت الترجمة.
     */
    protected function flattenJson(array $data): array
    {
        $flat = [];

        foreach ($data as $key => $value) {
            $flat[(string) $key] = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value;
        }

        return $flat;
    }

    protected function displayValue($value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return '';
        }

        // مصفوفة فارغة تبقى قيمةً بعد Arr::dot، ولا تُحوَّل لنصّ بالإسناد المباشر
        if (is_array($value)) {
            return (string) json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        return (string) $value;
    }

    /**
     * توليد ملف PHP مقروء بصياغة المصفوفات الحديثة.
     * القيم تمرّ بـ var_export فتُهرَّب علامات الاقتباس والشرطات المائلة.
     */
    protected function renderPhp(array $data): string
    {
        return "<?php\n\n"
            ."/*\n"
            ." * ملف ترجمة مُدار من شاشة إدارة اللغات.\n"
            ." * أي تعديل يدوي هنا يُستبدل عند الحفظ من الشاشة.\n"
            ." */\n\n"
            .'return '.$this->export($data).";\n";
    }

    protected function export($value, int $depth = 1): string
    {
        if (! is_array($value)) {
            return var_export($value, true);
        }

        if ($value === []) {
            return '[]';
        }

        $pad = str_repeat('    ', $depth);
        $isList = array_is_list($value);
        $out = "[\n";

        foreach ($value as $key => $item) {
            $out .= $pad;

            if (! $isList) {
                $out .= var_export(is_int($key) ? $key : (string) $key, true).' => ';
            }

            $out .= $this->export($item, $depth + 1).",\n";
        }

        return $out.str_repeat('    ', max(0, $depth - 1)).']';
    }

    /**
     * لارافيل يحمّل ملفات اللغة مرة واحدة في الطلب؛ ومع تفعيل كاش الإعدادات
     * قد تبقى الترجمة القديمة معروضة بعد الحفظ حتى تُفرَّغ.
     */
    protected function forgetTranslationCache(): void
    {
        try {
            app('translator')->setLoaded([]);
        } catch (Throwable $e) {
            // المترجم غير متاح في سياقات نادرة (أوامر مبكرة) — لا شيء يُفرَّغ
        }
    }
}
