<?php

namespace App\Services\Maintenance;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * فحصُ ما ستفعله الترحيلات المعلّقة **قبل** تشغيلها.
 *
 * **لماذا؟** شاشة التحديث كانت تنادي `migrate --force` على العمياء: يضغط
 * المسؤول زرّاً واحداً فتُنفَّذ ملفّاتٌ لا يعرف محتواها، ورسالةُ التأكيد
 * الوحيدة كانت سؤالاً بلا جواب («هل أخذت نسخةً احتياطية؟»). وفي هذا المستودع
 * ٦٣ ملفّ ترحيلٍ يحذف في `up()` عموداً أو جدولاً أو صفوفاً — فليست الحالةُ
 * نظريّة: ترحيلٌ واحدٌ منها يمرّ في التحديث يعني عموداً مملوءاً بالبيانات
 * يختفي بلا أن يُعرَض على أحد.
 *
 * فالفحص هنا يقرأ جسم `up()` نصّاً — لا يُنفّذه — ويصنّف كل عمليةٍ فيه:
 *
 * - **آمن** `safe`: إنشاءُ جدولٍ أو إضافةُ عمودٍ أو فهرس. لا يمسّ صفّاً قائماً.
 * - **مُعدِّل** `altering`: `->change()` أو `renameColumn` أو `Schema::rename`.
 *   لا يحذف صفّاً، لكنه يعيد تعريف عمودٍ فيه بيانات — وتضييقُ نوعٍ أو طولٍ
 *   يقصّ ما لا يتّسع.
 * - **متلف** `destructive`: `dropColumn` أو `dropIfExists` أو `truncate` أو
 *   `delete()` أو `DROP/TRUNCATE/DELETE` خاماً. هذا ما يحذف البيانات فعلاً.
 *
 * ثم يسأل القاعدةَ عن حجم الأثر: كم صفّاً في الجدول الذي سيُمسّ، وهل العمود
 * المراد حذفه موجودٌ أصلاً (فقد يكون الترحيل بلا أثرٍ على هذه القاعدة).
 * فيصير التحذير رقماً لا عبارة.
 *
 * **حدود هذا الفحص — تُقال صراحةً ولا تُخفى:** التحليل نصّيٌّ بتعابير نمطية،
 * لا تنفيذٌ ولا تحليلٌ نحوي. فهو يقرأ الأنماط المعتادة في هذا المستودع، ويجوز
 * أن يفوته حذفٌ مكتوبٌ بطريقةٍ غير معتادة (اسمُ جدولٍ في متغيّر، أو استعلامٌ
 * يُبنى في متغيّر ثم يُنفَّذ). ولذلك هو **طبقةُ إنذارٍ فوق النسخة الاحتياطية
 * لا بديلٌ عنها** — و`SystemUpdateService` يأخذ النسخة قبل الترحيل بصرف النظر
 * عن نتيجة هذا الفحص.
 */
class MigrationSafetyScanner
{
    public const SAFE = 'safe';

    public const ALTERING = 'altering';

    public const DESTRUCTIVE = 'destructive';

    /**
     * الأنماط المفحوصة.
     *
     * المفتاح اسمُ العملية، والقيمة تعبيرُها النمطي ودرجتُها ووصفُها العربي.
     * و`->delete()` مشروطةٌ بأن تكون على `DB::table(...)`: الحذفُ المقصود حذفُ
     * صفوف، لا استدعاءٌ آخر يصادف الاسم نفسه.
     *
     * @var array<string, array{re: string, level: string, label: string}>
     */
    private const PATTERNS = [
        /*
            حذفُ الأعمدة والجداول **لم يعد يقع** أثناء التحديث: تعترضه
            `App\Support\Migration\SchemaReconciler` في التصريف، فيبقى العمود
            مكانه ويُحال الجدولُ إلى `retired_*` بدل حذفه. فوسمُه «متلفاً»
            بعد ذلك يوقف التحديث حمايةً من شيءٍ لن يحدث — وهو ما كان يقع:
            يقف عند أوّل حذفٍ فلا ينزل ما بعده أبداً.

            فصار «مُعدِّلاً»: يُعرَض في التقرير — المستخدم يجب أن يعرف أن
            عموداً بقي أو جدولاً تقاعد — ولا يوقف شيئاً.
        */
        'dropColumn' => [
            're' => '/->\s*dropColumn\s*\(\s*(?<args>[^;]*?)\)\s*;/s',
            'level' => self::ALTERING,
            'label' => 'إبقاء عمود طُلب حذفه',
        ],
        'dropIfExists' => [
            're' => '/Schema::\s*dropIfExists\s*\(\s*[\'"](?<table>[^\'"]+)[\'"]/',
            'level' => self::ALTERING,
            'label' => 'إحالة جدول إلى التقاعد',
        ],
        'dropTable' => [
            're' => '/Schema::\s*drop\s*\(\s*[\'"](?<table>[^\'"]+)[\'"]/',
            'level' => self::ALTERING,
            'label' => 'إحالة جدول إلى التقاعد',
        ],
        'truncate' => [
            're' => '/DB::\s*table\s*\(\s*[\'"](?<table>[^\'"]+)[\'"]\s*\)[^;]*?->\s*truncate\s*\(/s',
            'level' => self::DESTRUCTIVE,
            'label' => 'تفريغ جدول',
        ],
        'delete' => [
            're' => '/DB::\s*table\s*\(\s*[\'"](?<table>[^\'"]+)[\'"]\s*\)(?<chain>[^;]*?)->\s*delete\s*\(\s*\)/s',
            'level' => self::DESTRUCTIVE,
            'label' => 'حذف صفوف',
        ],
        'rawDestroy' => [
            're' => '/(?:statement|unprepared)\s*\(\s*[\'"](?<sql>[^\'"]*\b(?:DROP|TRUNCATE|DELETE)\b[^\'"]*)/i',
            'level' => self::DESTRUCTIVE,
            'label' => 'أمر SQL متلف',
        ],
        'renameColumn' => [
            're' => '/->\s*renameColumn\s*\(\s*[\'"](?<from>[^\'"]+)[\'"]\s*,\s*[\'"](?<to>[^\'"]+)[\'"]/',
            'level' => self::ALTERING,
            'label' => 'إعادة تسمية عمود',
        ],
        'renameTable' => [
            're' => '/Schema::\s*rename\s*\(\s*[\'"](?<table>[^\'"]+)[\'"]\s*,\s*[\'"](?<to>[^\'"]+)[\'"]/',
            'level' => self::ALTERING,
            'label' => 'إعادة تسمية جدول',
        ],
        'change' => [
            're' => '/->\s*change\s*\(\s*\)/',
            'level' => self::ALTERING,
            'label' => 'تعديل تعريف عمود',
        ],
    ];

    /**
     * جداولُ الإعدادات وكتالوجات النظام — يملكها النظام لا المستخدم.
     *
     * ── لماذا استثناء أصلاً؟ ──
     *
     * قاعدةُ «لا حذف» وُضعت لبيانات المستخدم: فاتورةٌ، عميل، قيدٌ محاسبي،
     * عمودٌ فيه أرقام. أمّا هذه الجداول فهي وصفُ النظام لنفسه: أسماءُ
     * الصلاحيات، وربطُها بالأدوار، ومفاتيحُ التطبيقات، والمهامُّ المجدولة،
     * وحالاتُ المستندات. وصفٌّ فيها ليس بياناتٍ أدخلها أحد، بل إعلانٌ من
     * الكود عن نفسه.
     *
     * وحين تُلغى ميزةٌ، الترحيلُ الذي يُلغيها يسحب صلاحيّتها من الأدوار
     * ويُزيل مفتاحَ تطبيقها. فإن عُدّ ذلك «حذفَ بيانات» وُقِف التحديثُ كلُّه
     * عنده — وما بعده من عشرات الترحيلات يبقى معلَّقاً، والنظامُ محبوسٌ على
     * شاشة التحديث. ثمنٌ باهظٌ ثمنُه صفٌّ في `role_has_permissions`، وما
     * حُذف منه يعيده `permissions:sync` وسيدرُ الأدوار في أي وقت.
     *
     * ── وحدودُ الاستثناء ضيّقةٌ عمداً ──
     *
     * يسري على **حذفِ صفوفٍ بشرط** (`->where(...)->delete()`) في هذه
     * الأسماء وحدها. أمّا:
     *   • حذفُ عمودٍ أو جدولٍ — ولو في هذه الجداول — فيبقى متلفاً،
     *   • و`truncate` أو `delete()` بلا شرط — ولو في هذه الجداول — يبقى
     *     متلفاً: إفراغُ `permissions` كلِّه ليس إلغاءَ ميزة،
     *   • وأيُّ جدولٍ خارج القائمة يبقى على أصله.
     *
     * @var array<int, string>
     */
    private const SYSTEM_TABLES = [
        // كتالوج الصلاحيات وربطها (Spatie)
        'permissions',
        'role_has_permissions',
        'model_has_permissions',
        'model_has_roles',
        // مفاتيح تشغيل الوحدات والمهام المجدولة
        'application_settings',
        'cron_jobs',
        // كتالوجات وإعدادات الشاشات
        'statuses',
        'serial_settings',
        'general_client_settings',
        'branch_settings',
        'branch_setting_branch',
        'sales_invoice_settings',
        'purchase_invoice_settings',
    ];

    /** ذاكرةٌ للطلب الواحد: الجدول نفسه يتكرّر في عدّة ترحيلات. */
    private array $rowCounts = [];

    /**
     * فحص الترحيلات المعلّقة.
     *
     * @param  array<int, string>  $pending  أسماء الملفّات بلا امتداد
     * @param  array<string, string>  $files  خريطة الاسم ← المسار، من `Migrator`
     * @return array{
     *     files: array<int, array{name: string, level: string, operations: array<int, array<string, mixed>>, unreadable: bool}>,
     *     counts: array{safe: int, altering: int, destructive: int},
     *     destructive: bool,
     *     altering: bool,
     *     impact: array<int, array<string, mixed>>
     * }
     */
    public function scan(array $pending, array $files): array
    {
        $result = [];
        $impact = [];
        $counts = [self::SAFE => 0, self::ALTERING => 0, self::DESTRUCTIVE => 0];

        foreach ($pending as $name) {
            $path = $files[$name] ?? null;
            $source = ($path !== null && is_readable($path)) ? @file_get_contents($path) : false;

            if ($source === false) {
                // ملفٌّ مذكورٌ في القائمة ولا يُقرأ: يُعرَض ولا يُدَّعى أنه آمن
                $result[] = [
                    'name' => $name,
                    'level' => self::ALTERING,
                    'operations' => [],
                    'unreadable' => true,
                ];
                $counts[self::ALTERING]++;

                continue;
            }

            $operations = $this->inspect($this->upBody($source));
            $level = $this->levelOf($operations);

            $result[] = [
                'name' => $name,
                'level' => $level,
                'operations' => $operations,
                'unreadable' => false,
            ];

            foreach ($operations as $operation) {
                if ($operation['applies'] && $operation['level'] === self::DESTRUCTIVE) {
                    $impact[] = $operation + ['migration' => $name];
                }
            }

            $counts[$level]++;
        }

        return [
            'files' => $result,
            'counts' => [
                'safe' => $counts[self::SAFE],
                'altering' => $counts[self::ALTERING],
                'destructive' => $counts[self::DESTRUCTIVE],
            ],
            'destructive' => $counts[self::DESTRUCTIVE] > 0,
            'altering' => $counts[self::ALTERING] > 0,
            'impact' => $impact,
        ];
    }

    /**
     * جسم `up()` وحده.
     *
     * `down()` في كل ترحيلٍ تقريباً فيه `dropIfExists` — وهو الطبيعي: تراجعُ
     * إنشاءِ جدولٍ حذفُه. فلو فُحص الملفّ كاملاً لخرج ٦٤٩ ملفّاً من ٧٢١ موسوماً
     * بالخطر، وتحذيرٌ يظهر دائماً تحذيرٌ لا يُقرأ. والشاشة لا تنادي `down()` أبداً.
     */
    private function upBody(string $source): string
    {
        $start = strpos($source, 'function up');

        if ($start === false) {
            return $source;
        }

        $end = strpos($source, 'function down', $start);

        return $end === false ? substr($source, $start) : substr($source, $start, $end - $start);
    }

    /**
     * العمليات الموجودة في جسم `up()`، مع أثرها على القاعدة الحالية.
     *
     * @return array<int, array<string, mixed>>
     */
    private function inspect(string $body): array
    {
        $blocks = $this->schemaBlocks($body);
        $operations = [];

        foreach (self::PATTERNS as $key => $pattern) {
            $found = @preg_match_all($pattern['re'], $body, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

            // نمطٌ لم يُصرَّف يُرجِع `false` لا صفراً؛ لولا التمييز لصار خطأٌ
            // في تعبيرٍ نمطي «لا خطر في هذا الملفّ»
            if ($found === false || $found === 0) {
                continue;
            }

            foreach ($matches as $match) {
                $offset = $match[0][1];
                $table = isset($match['table']) ? $match['table'][0] : $this->tableAt($blocks, $offset);

                $operation = [
                    'op' => $key,
                    'label' => $pattern['label'],
                    'level' => $pattern['level'],
                    'table' => $table,
                    'columns' => [],
                    // `all` يمسّ الجدول كلَّه، و`filtered` صفوفاً مطابقةً لشرط.
                    // الفرق ليس تفصيلاً: «يحذف صفوفاً من جدولٍ فيه ١١٢٤ صفّاً»
                    // تُقرَأ على أنها حذفُ الألف ومئة إن لم يُقَل إنّ الحذف مشروط
                    'scope' => 'all',
                    'rows' => null,
                    'applies' => true,
                    'note' => null,
                ];

                if ($key === 'dropColumn') {
                    $operation['columns'] = $this->columnsOf($match['args'][0], $body);
                } elseif ($key === 'delete') {
                    $operation['scope'] = preg_match('/->\s*where/i', $match['chain'][0]) === 1
                        ? 'filtered'
                        : 'all';
                } elseif ($key === 'renameColumn') {
                    $operation['columns'] = [$match['from'][0]];
                    $operation['note'] = 'يصير اسمه «'.$match['to'][0].'»';
                } elseif ($key === 'renameTable') {
                    $operation['note'] = 'يصير اسمه «'.$match['to'][0].'»';
                } elseif ($key === 'rawDestroy') {
                    $operation['note'] = trim($match['sql'][0]);
                }

                $operations[] = $this->measure($operation);
            }
        }

        return $operations;
    }

    /**
     * قياسُ أثر العملية على **هذه** القاعدة لا على قاعدةٍ نظرية.
     *
     * الفرق جوهري: ترحيلٌ يحذف عموداً حُذف من قبل، أو جدولاً لم يوجد قطّ في هذا
     * التركيب، لا يُتلف شيئاً هنا — ووسمُه بالخطر يجعل التحذير ضجيجاً يُتجاوَز
     * بالنقر. فيُسأل المخطَّطُ أوّلاً: أموجودٌ الجدولُ والعمود؟ ثم يُسأل الجدول:
     * كم فيه صفّاً؟ وصفرُ صفوفٍ يعني حذفاً بلا خسارة.
     */
    private function measure(array $operation): array
    {
        $table = $operation['table'];

        if ($table === null) {
            return $operation;
        }

        try {
            if (! Schema::hasTable($table)) {
                $operation['applies'] = false;
                $operation['note'] = 'الجدول غير موجود في هذه القاعدة — بلا أثر.';

                return $operation;
            }

            if ($operation['columns'] !== []) {
                $present = array_values(array_filter(
                    $operation['columns'],
                    fn ($column) => Schema::hasColumn($table, $column)
                ));

                if ($present === []) {
                    $operation['applies'] = false;
                    $operation['note'] = 'العمود محذوفٌ أصلاً من هذه القاعدة — بلا أثر.';

                    return $operation;
                }

                $operation['columns'] = $present;
            } elseif ($operation['op'] === 'dropColumn') {
                // اسمُ العمود في متغيّر لم يُقرأ — يُقال ولا يُفترض أنه سليم
                $operation['note'] = 'اسم العمود غير مكتوبٍ نصّاً في الملفّ — راجعه بنفسك.';
            }

            $operation['rows'] = $this->rowCount($table);

            // حذفٌ مشروط لا يُقاس بحجم الجدول: عددُ صفوفه سياقٌ لا مقدارُ فقد
            if ($operation['scope'] === 'filtered') {
                /* صفٌّ مشروطٌ في جدولٍ يملكه النظام: تغييرُ إعدادٍ لا فقدُ
                   بيانات — انظر `SYSTEM_TABLES` أعلاه. يُعرَض ويُحسب
                   «مُعدِّلاً» فيمرّ التحديث، ولا يُخفى عن التقرير. */
                if ($operation['op'] === 'delete' && in_array($table, self::SYSTEM_TABLES, true)) {
                    $operation['level'] = self::ALTERING;
                    $operation['note'] = 'جدولُ إعداداتٍ يملكه النظام — تغييرُ إعدادٍ لا فقدُ بيانات.';
                }

                return $operation;
            }

            if ($operation['rows'] === 0 && $operation['level'] === self::DESTRUCTIVE) {
                $operation['applies'] = false;
                $operation['note'] = 'الجدول فارغ — لا بيانات تُفقَد.';
            }
        } catch (Throwable) {
            // قاعدةٌ لا تُسأل الآن: تبقى العملية معروضةً بلا رقم، ولا يسقط
            // الفحصُ كلُّه بسبب جدولٍ واحد
        }

        return $operation;
    }

    /** أعلى درجةِ خطورةٍ في الملفّ — ولا تُحسب عمليةٌ ثبت أنها بلا أثرٍ هنا. */
    private function levelOf(array $operations): string
    {
        $level = self::SAFE;

        foreach ($operations as $operation) {
            if (! $operation['applies']) {
                continue;
            }

            if ($operation['level'] === self::DESTRUCTIVE) {
                return self::DESTRUCTIVE;
            }

            $level = self::ALTERING;
        }

        return $level;
    }

    /**
     * مواضع `Schema::table('x'` و`Schema::create('x'` في النصّ.
     *
     * تُستعمل لنسبة `->dropColumn(...)` إلى جدولها: الاستدعاء داخل دالّةٍ
     * مجهولة لا يحمل اسم الجدول، والاسمُ في السطر الذي فتح الكتلة. فيؤخذ
     * أقربُ فتحٍ **يسبق** موضعَ العملية.
     *
     * @return array<int, array{offset: int, table: string}>
     */
    private function schemaBlocks(string $body): array
    {
        $blocks = [];

        if (preg_match_all(
            '/Schema::\s*(?:table|create)\s*\(\s*[\'"]([^\'"]+)[\'"]/',
            $body,
            $matches,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE
        )) {
            foreach ($matches as $match) {
                $blocks[] = ['offset' => $match[0][1], 'table' => $match[1][0]];
            }
        }

        return $blocks;
    }

    /** أقرب جدولٍ فُتحت كتلتُه قبل هذا الموضع. */
    private function tableAt(array $blocks, int $offset): ?string
    {
        $table = null;

        foreach ($blocks as $block) {
            if ($block['offset'] > $offset) {
                break;
            }

            $table = $block['table'];
        }

        return $table;
    }

    /**
     * أسماء الأعمدة في استدعاء `dropColumn(...)`.
     *
     * ثلاث كتاباتٍ شائعة في هذا المستودع:
     *   `dropColumn('a')` و`dropColumn(['a','b'])` — الاسمُ حرفيٌّ فيؤخذ كما هو.
     *   `foreach (['a','b'] as $col) { ... dropColumn($col); }` — والوسيطُ متغيّر.
     *
     * والثالثة ليست حالةً نادرة بل النمطَ المفضَّل هنا (الحلقةُ تحرس كل عمودٍ
     * بـ`Schema::hasColumn`). ولولا حلُّها لعُرضت تلك الترحيلات بلا أسماء أعمدة
     * أصلاً — أي بأخطر رسالةٍ ممكنة: «سيُحذف عمودٌ» بلا قول أيُّ عمود.
     *
     * @return array<int, string>
     */
    private function columnsOf(string $arguments, string $body): array
    {
        $literals = $this->literals($arguments);

        if ($literals !== []) {
            return $literals;
        }

        // وسيطٌ متغيّر: يُبحث عن الحلقة التي تُسنده
        if (! preg_match('/\$(\w+)/', $arguments, $variable)) {
            return [];
        }

        $pattern = '/foreach\s*\(\s*(\[[^\]]*\])\s*as\s*\$'.preg_quote($variable[1], '/').'\b/';

        if (! preg_match($pattern, $body, $loop)) {
            return [];
        }

        return $this->literals($loop[1]);
    }

    /**
     * النصوص الحرفية داخل وسائط استدعاء.
     *
     * @return array<int, string>
     */
    private function literals(string $arguments): array
    {
        if (! preg_match_all('/[\'"]([^\'"]+)[\'"]/', $arguments, $matches)) {
            return [];
        }

        return array_values(array_unique($matches[1]));
    }

    /** عدد صفوف جدول، محفوظاً للطلب — الجدول نفسه يتكرّر عبر الترحيلات. */
    private function rowCount(string $table): ?int
    {
        if (array_key_exists($table, $this->rowCounts)) {
            return $this->rowCounts[$table];
        }

        try {
            return $this->rowCounts[$table] = (int) DB::table($table)->count();
        } catch (Throwable) {
            return $this->rowCounts[$table] = null;
        }
    }
}
