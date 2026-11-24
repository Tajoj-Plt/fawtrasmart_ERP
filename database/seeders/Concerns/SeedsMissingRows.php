<?php

namespace Database\Seeders\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * زرعُ الصفوف الناقصة وحدها — لبذورٍ تُشغَّل على قاعدةٍ عامرة.
 *
 * شاشة «تحديث النظام» تعرض البذور كخطواتٍ يضغطها صاحبُ النظام على قاعدةٍ
 * حيّة، لا كأمرِ تنصيبٍ يُشغَّل مرّةً على قاعدةٍ فارغة. وبذرةٌ تُدرج صفوفها
 * إدراجاً مباشراً تنتهي إلى واحدةٍ من اثنتين:
 *
 *   • جدولٌ فيه فهرسٌ فريد ⇦ الخطوة تسقط كلُّها عند أوّل صفٍّ موجود
 *     («Duplicate entry 'image' for key `general_client_settings_key_unique`»)
 *   • جدولٌ بلا فهرسٍ فريد ⇦ الصفوف تتضاعف صامتةً في كلّ ضغطة، فتظهر
 *     الشاشة قائمتين متطابقتين ولا خطأَ يشي بالسبب
 *
 * فالزرعُ هنا يقرأ الموجود أوّلاً بمفتاحٍ طبيعيّ ويُدرج ما نقص وحده. وما
 * كُتب في صفٍّ قائم لا يُمسّ: صاحبُ النظام يعدّل هذه الصفوف من شاشاتها
 * (يُطفئ قالباً، يغيّر نقاطَ مستوى)، وزرعٌ يعيد كتابتها يمحو قراراته.
 */
trait SeedsMissingRows
{
    /**
     * @param  string  $table  الجدول
     * @param  array<int, array<string, mixed>>  $rows  الصفوف المطلوب وجودها
     * @param  array<int, string>|string  $keyColumns  المفتاح الطبيعي المميِّز للصف
     * @param  bool  $timestamps  إضافة `created_at`/`updated_at` للصفوف الجديدة
     * @return int عدد الصفوف المزروعة فعلاً
     */
    protected function seedMissingRows(string $table, array $rows, array|string $keyColumns, bool $timestamps = true): int
    {
        if ($rows === [] || ! Schema::hasTable($table)) {
            return 0;
        }

        $keyColumns = (array) $keyColumns;

        $existing = DB::table($table)
            ->get($keyColumns)
            ->map(fn ($row) => $this->rowSignature((array) $row, $keyColumns))
            ->flip();

        $missing = [];
        $now = now();

        foreach ($rows as $row) {
            $signature = $this->rowSignature($row, $keyColumns);

            if ($existing->has($signature)) {
                continue;
            }

            // صفٌّ مكرَّر داخل قائمة البذرة نفسها لا يُزرع مرّتين
            $existing->put($signature, true);

            if ($timestamps) {
                $row += ['created_at' => $now, 'updated_at' => $now];
            }

            $missing[] = $row;
        }

        if ($missing === []) {
            return 0;
        }

        DB::table($table)->insert($missing);

        return count($missing);
    }

    /**
     * تقريرُ الحصيلة على الشاشة — «لا جديد» رسالةُ نجاحٍ لا صمت.
     */
    protected function reportSeeded(int $seeded, string $label): void
    {
        $this->command?->info($seeded > 0
            ? "تمّ زرع {$seeded} من {$label}."
            : "{$label}: مزروعةٌ مسبقاً — لا جديد.");
    }

    /**
     * بصمةُ الصفّ بمفتاحه الطبيعي. `null` يُميَّز عن السلسلة الفارغة كي لا
     * يتساوى صفٌّ بلا فرعٍ مع صفٍّ فرعُه ''.
     *
     * @param  array<string, mixed>  $row
     * @param  array<int, string>  $keyColumns
     */
    private function rowSignature(array $row, array $keyColumns): string
    {
        $parts = [];

        foreach ($keyColumns as $column) {
            $value = $row[$column] ?? null;
            $parts[] = $value === null ? "\0null" : (string) $value;
        }

        return implode("\0", $parts);
    }
}
