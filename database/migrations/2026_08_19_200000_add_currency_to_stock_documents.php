<?php

use App\Support\CurrencySettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * عملة مستندات المخزون — تُخزَّن مع المستند لا تُقرأ من الإعدادات وقت العرض.
 *
 * **لماذا عمود لا قراءة من الإعدادات؟**
 *
 * كانت قيود المخزون كلها تكتب `'currency' => 'SAR'` في الشيفرة، ومستندات
 * المخزون نفسها لا تحمل عملةً أصلاً. فمتى بدّلت المنشأة عملتها الافتراضية
 * تغيّرت قراءةُ كل مستند قديم بأثر رجعي — أو بقيت «SAR» أبداً مهما بُدّلت.
 * كلا السلوكين خطأ: عملة المستند خاصّية من خصائصه لحظةَ إنشائه، تُجمَّد معه.
 *
 * الأعمدة تُملأ بالعملة الافتراضية الحالية للمستندات القائمة — أدقّ تقدير
 * متاح لما أُنشئت به فعلاً، وهو الذي كان مفروضاً عليها ضمناً.
 */
return new class extends Migration
{
    /**
     * الجداول التي تحمل مبالغ ويُنشأ عنها قيد أو تقرير مالي.
     *
     * `production_materials` و`work_stations_costs` مضمَّنان لأن تكلفتهما
     * تُرحَّل إلى أوامر التصنيع، فلا يصحّ أن تكون بلا عملة معلومة.
     */
    private const TABLES = [
        'warehouse_permits' => 'grand_total',
        'manufactur_orders' => null,
        'inventory_adjustments' => null,
        'indirect_costs' => null,
        'production_materials' => null,
        'work_stations_costs' => null,
    ];

    public function up(): void
    {
        $default = $this->defaultCode();

        foreach (array_keys(self::TABLES) as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'currency')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($default) {
                $blueprint->string('currency', 10)->nullable()->default($default)
                    ->comment('عملة المستند وقت إنشائه — لا تتغيّر بتغيّر العملة الافتراضية');
            });

            // المستندات القائمة أُنشئت تحت العملة الافتراضية ضمناً
            DB::table($table)->whereNull('currency')->update(['currency' => $default]);
        }
    }

    public function down(): void
    {
        foreach (array_keys(self::TABLES) as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'currency')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn('currency');
            });
        }
    }

    /**
     * العملة الافتراضية — أو `SAR` إن لم يُزرع جدول العملات بعد.
     *
     * الترحيلات تعمل على قاعدة فارغة في الاختبارات، فقراءة الإعدادات هنا
     * يجب ألّا تُسقط الترحيل.
     */
    private function defaultCode(): string
    {
        try {
            return CurrencySettings::code();
        } catch (Throwable $e) {
            return 'SAR';
        }
    }
};
