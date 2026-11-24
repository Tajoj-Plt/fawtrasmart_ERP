<?php

namespace App\Services\Database;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * أحجام جداول قاعدة البيانات — لمعرفة أين تذهب المساحة.
 *
 * تُقرأ من `information_schema.TABLES` في استعلام واحد: `COUNT(*)` على
 * كل جدول يعني مئات الاستعلامات على قاعدةٍ فيها مئة جدول، وبعضها بعشرات
 * الآلاف من الصفوف. عدد الصفوف هنا **تقديري** — تُقدّره InnoDB من إحصاءات
 * الفهرس ولا يطابق العدّ الفعلي، وهو كافٍ لترتيب الجداول بالحجم.
 *
 * `data_free` هو المساحة المحجوزة داخل الملفّ ولم تعد مستعملة: صفوف
 * حُذفت ولم يُستردّ مكانها. جدولٌ حجمه ضخم و`data_free` فيه كبير يعني
 * مساحةً تُستردّ بإعادة بناء الجدول لا بيانات فعلية.
 */
class DatabaseSizeInspector
{
    /**
     * كل الجداول مرتّبةً بالحجم تنازلياً.
     *
     * @return array<int, array{name:string,rows:int,data_bytes:int,index_bytes:int,free_bytes:int,total_bytes:int,engine:?string,collation:?string}>
     */
    public function tables(): array
    {
        try {
            $rows = DB::select(
                'SELECT TABLE_NAME AS name,
                        TABLE_ROWS AS row_count,
                        DATA_LENGTH AS data_bytes,
                        INDEX_LENGTH AS index_bytes,
                        DATA_FREE AS free_bytes,
                        ENGINE AS engine,
                        TABLE_COLLATION AS collation
                   FROM information_schema.TABLES
                  WHERE TABLE_SCHEMA = ?
                    AND TABLE_TYPE = ?',
                [DB::getDatabaseName(), 'BASE TABLE']
            );
        } catch (\Throwable $e) {
            // قاعدة غير MySQL أو صلاحيات ناقصة: تُعرض الشاشة فارغة بدل أن تنهار
            Log::warning('تعذّرت قراءة أحجام الجداول', ['error' => $e->getMessage()]);

            return [];
        }

        $tables = [];

        foreach ($rows as $row) {
            $data = (int) ($row->data_bytes ?? 0);
            $index = (int) ($row->index_bytes ?? 0);

            $tables[] = [
                'name' => (string) $row->name,
                'rows' => (int) ($row->row_count ?? 0),
                'data_bytes' => $data,
                'index_bytes' => $index,
                'free_bytes' => (int) ($row->free_bytes ?? 0),
                'total_bytes' => $data + $index,
                'engine' => $row->engine,
                'collation' => $row->collation,
            ];
        }

        usort($tables, fn ($a, $b) => $b['total_bytes'] <=> $a['total_bytes']);

        return $tables;
    }

    /**
     * إجماليات القاعدة.
     *
     * @param  array<int, array<string, mixed>>  $tables
     * @return array{tables:int,rows:int,data_bytes:int,index_bytes:int,free_bytes:int,total_bytes:int,largest:?array<string,mixed>}
     */
    public function summary(array $tables): array
    {
        return [
            'tables' => count($tables),
            'rows' => (int) array_sum(array_column($tables, 'rows')),
            'data_bytes' => (int) array_sum(array_column($tables, 'data_bytes')),
            'index_bytes' => (int) array_sum(array_column($tables, 'index_bytes')),
            'free_bytes' => (int) array_sum(array_column($tables, 'free_bytes')),
            'total_bytes' => (int) array_sum(array_column($tables, 'total_bytes')),
            'largest' => $tables[0] ?? null,
        ];
    }

    /**
     * حجم مقروء — الوحدة تُختار حسب المقدار لا ثابتةً.
     *
     * ميغابايت لكل شيء يعرض «0.00 م.ب» لجدول صغير و«12480.00 م.ب» لكبير،
     * وكلاهما أصعب قراءةً من الوحدة المناسبة.
     */
    public static function humanBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = min((int) floor(log($bytes, 1024)), count($units) - 1);
        $value = $bytes / (1024 ** $power);

        return number_format($value, $power === 0 ? 0 : ($value >= 100 ? 0 : 1)).' '.$units[$power];
    }
}
