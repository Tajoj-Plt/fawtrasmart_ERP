<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * جداول قاعدة البيانات المتاحة للاختيار في النسخة الاحتياطية.
 *
 * هذا الصنف هو حدّ الأمان في ميزة «جداول معيّنة»: `spatie/db-dumper` يبني
 * أمر `mysqldump` نصّاً ويشغّله عبر `Process::fromShellCommandline`، أي أن
 * اسم الجدول يصل إلى الصدفة كما هو. لذلك لا يُمرَّر اسم جاء من الواجهة
 * إلا بعد مطابقته حرفياً على أسماء الجداول الموجودة فعلاً في القاعدة —
 * لا بتنقيته بتعبير نمطي، بل بحصره في قائمة مغلقة يولّدها الخادم.
 *
 * التصفية تُطبَّق عند الحفظ وعند التنفيذ معاً: جدول حُذف من القاعدة بعد
 * حفظ الإعدادات يجب ألا يصل إلى أمر النسخ فيُفشله كلّه.
 */
class BackupTableRegistry
{
    private const CACHE_KEY = 'backup:tables';

    private const CACHE_SECONDS = 300;

    /**
     * كل الجداول مع حجمها وعدد صفوفها التقريبي.
     *
     * `SHOW TABLE STATUS` يعطي الثلاثة في استعلام واحد — أرخص بكثير من
     * `COUNT(*)` على كل جدول، وتقريبه كافٍ لشاشة اختيار.
     *
     * @return array<int, array{name:string,rows:int,bytes:int}>
     */
    public function all(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_SECONDS, function () {
            try {
                $rows = DB::select('SHOW TABLE STATUS');
            } catch (\Throwable $e) {
                // قاعدة غير MySQL أو صلاحيات ناقصة: تُرجَع قائمة فارغة
                // فتعرض الشاشة «لا جداول» بدل أن تنهار
                Log::warning('تعذّرت قراءة قائمة الجداول', ['error' => $e->getMessage()]);

                return [];
            }

            $tables = [];

            foreach ($rows as $row) {
                $row = (array) $row;
                $name = $row['Name'] ?? null;

                if (! is_string($name) || $name === '') {
                    continue;
                }

                $tables[] = [
                    'name' => $name,
                    'rows' => (int) ($row['Rows'] ?? 0),
                    'bytes' => (int) ($row['Data_length'] ?? 0) + (int) ($row['Index_length'] ?? 0),
                ];
            }

            usort($tables, fn ($a, $b) => strcmp($a['name'], $b['name']));

            return $tables;
        });
    }

    /** @return array<int, string> */
    public function names(): array
    {
        return array_column($this->all(), 'name');
    }

    /**
     * تصفية أسماء واردة من الواجهة إلى ما هو موجود فعلاً.
     *
     * ما لا يطابق اسم جدول قائم يُسقَط صامتاً — الأسماء ليست مدخلاً حرّاً
     * يُصحَّح للمستخدم، بل اختياراً من قائمة عرضناها له. وصولُ اسم خارجها
     * يعني إمّا جدولاً حُذف أو طلباً معدَّلاً، وكلاهما يُسقَط.
     *
     * @param  array<int, mixed>|null  $names
     * @return array<int, string>
     */
    public function filter(?array $names): array
    {
        if (empty($names)) {
            return [];
        }

        $known = $this->names();

        $clean = array_values(array_unique(array_filter(
            array_map(fn ($name) => is_string($name) ? trim($name) : null, $names),
            fn ($name) => $name !== null && $name !== '' && in_array($name, $known, true)
        )));

        sort($clean);

        return $clean;
    }

    /** هل كل الأسماء المعطاة موجودة؟ يُستعمل في التحقّق من الاستمارة. */
    public function allExist(?array $names): bool
    {
        if (empty($names)) {
            return true;
        }

        return count($this->filter($names)) === count(array_unique($names));
    }

    /**
     * الجداول التي ستُنسَخ فعلاً حسب النمط والاختيار.
     *
     * تُحسَب هنا لا في خدمة النسخ لأن الشاشة تحتاجها أيضاً — «سيُنسَخ 42
     * جدولاً من 118» رقمٌ يجب أن يراه المسؤول قبل الحفظ لا بعد أول تشغيل.
     *
     * @param  array<int, string>|null  $included
     * @param  array<int, string>|null  $excluded
     * @return array<int, string>
     */
    public function resolve(string $mode, ?array $included = null, ?array $excluded = null): array
    {
        $all = $this->names();

        return match ($mode) {
            'include' => $this->filter($included),
            'exclude' => array_values(array_diff($all, $this->filter($excluded))),
            default => $all,
        };
    }

    /**
     * أعمدة جدول واحد مع أنواعها وتعليقاتها.
     *
     * اسم الجدول يُطابَق على القائمة المغلقة أولاً — بعدها فقط يجوز إقحامه
     * في `SHOW FULL COLUMNS`، إذ لا سبيل لربطه كمعامل في هذه الجملة.
     *
     * @return array<int, array{name:string,type:string,kind:string,comment:string,nullable:bool}>
     */
    public function columns(string $table): array
    {
        if (! in_array($table, $this->names(), true)) {
            return [];
        }

        return Cache::remember(self::CACHE_KEY.':cols:'.$table, self::CACHE_SECONDS, function () use ($table) {
            try {
                $rows = DB::select('SHOW FULL COLUMNS FROM `'.str_replace('`', '', $table).'`');
            } catch (\Throwable $e) {
                Log::warning('تعذّرت قراءة أعمدة جدول', ['table' => $table, 'error' => $e->getMessage()]);

                return [];
            }

            return array_map(function ($row) {
                $row = (array) $row;
                $type = (string) ($row['Type'] ?? '');

                return [
                    'name' => (string) ($row['Field'] ?? ''),
                    'type' => $type,
                    // التصنيف يحدّد المعاملات المعروضة للمستخدم: النطاق
                    // للأرقام والتواريخ، و«يحتوي» للنصوص
                    'kind' => $this->classify($type),
                    'comment' => (string) ($row['Comment'] ?? ''),
                    'nullable' => strtoupper((string) ($row['Null'] ?? 'NO')) === 'YES',
                ];
            }, $rows);
        });
    }

    /** @return array<int, string> */
    public function columnNames(string $table): array
    {
        return array_column($this->columns($table), 'name');
    }

    /**
     * تصفية أسماء أعمدة واردة من الواجهة على أعمدة الجدول الفعلية.
     *
     * @param  array<int, mixed>|null  $columns
     * @return array<int, string>
     */
    public function filterColumns(string $table, ?array $columns): array
    {
        $known = $this->columnNames($table);

        if (empty($columns)) {
            return $known; // بلا اختيار = كل الأعمدة
        }

        return array_values(array_filter(
            array_map(fn ($column) => is_string($column) ? trim($column) : null, $columns),
            fn ($column) => $column !== null && in_array($column, $known, true)
        ));
    }

    /** تصنيف نوع العمود إلى: number | date | text | binary */
    protected function classify(string $type): string
    {
        $type = strtolower($type);

        return match (true) {
            (bool) preg_match('/^(tinyint|smallint|mediumint|int|bigint|decimal|numeric|float|double|bit)/', $type) => 'number',
            (bool) preg_match('/^(date|datetime|timestamp|time|year)/', $type) => 'date',
            (bool) preg_match('/(blob|binary)/', $type) => 'binary',
            default => 'text',
        };
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
