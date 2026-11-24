<?php

use App\Support\SchemaGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `store_house_id` في بنود الإذن — عمودٌ يكتبه الكود ولا وجود له.
 *
 * ── المشكلة ──────────────────────────────────────────────────────────
 *
 * `BOMController:529` يكتب مستودع البند:
 *
 *     WarehousePermitsProducts::create([
 *         …
 *         'store_house_id' => $line['warehouse_id'],
 *     ]);
 *
 * وهجرة الإنشاء `2025_08_14_140749_create_warehouse_permits_products_table`
 * لا تُنشئ العمود أصلاً — ولا يُنشئه شيءٌ بعدها. فالمستودع الحقيقي للبند
 * يضيع، ويصير رأس الإذن (`warehouse_permits.store_houses_id`) هو المصدر
 * الوحيد — وهو مستودعٌ واحدٌ لكل الإذن.
 *
 * وهذا يكسر الحالة التي وُجد العمود من أجلها: إذنٌ يسحب من مستودعين.
 *
 * ── لماذا هذه الهجرة **إلزامية** الآن ────────────────────────────────
 *
 * أُضيف `store_house_id` إلى `$fillable` في `WarehousePermitsProducts`
 * ضمن هذه الحزمة. وبلا العمود يتحوّل الإهمال الصامت إلى خطأ SQL صريح:
 *
 *     SQLSTATE[42S22]: Unknown column 'store_house_id' in 'field list'
 *
 * فإمّا هذه الهجرة وإمّا إرجاع السطر من `$fillable`. والعمود هو الصواب:
 * الكود يريده، والإذن متعدّد المستودعات حالةٌ حقيقية في الجرد والتحويل.
 *
 * ── الصفوف القائمة ───────────────────────────────────────────────────
 *
 * تُملأ من مستودع رأس الإذن — وهو ما كان يُفترَض ضمناً قبل العمود، فلا
 * يتغيّر تفسير أيّ صفٍّ قديم.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('warehouse_permits_products')) {
            return;
        }

        if (Schema::hasColumn('warehouse_permits_products', 'store_house_id')) {
            echo "\n  · store_house_id موجود سلفاً — لا تغيير\n";

            return;
        }

        Schema::table('warehouse_permits_products', function (Blueprint $table) {
            if (! Schema::hasColumn('warehouse_permits_products', 'store_house_id')) {
                $table->unsignedBigInteger('store_house_id')
                    ->nullable()
                    ->after('product_id')
                    ->comment('مستودع هذا البند — قد يختلف عن مستودع رأس الإذن');
            }

            if (! SchemaGuard::hasIndex('warehouse_permits_products', 'wpp_store_house_idx')) {
                $table->index('store_house_id', 'wpp_store_house_idx');
            }
        });

        echo "\n  ✓ أُضيف العمود store_house_id إلى warehouse_permits_products\n";

        // ── ملء الصفوف القائمة من رأس الإذن ──
        //
        // لا قيد أجنبي: `store_houses` قد يحوي صفوفاً محذوفة ناعماً، وربط
        // البنود القديمة قسراً يُسقط الهجرة على بياناتٍ حيّة.
        // UPDATE ... JOIN صيغةٌ خاصّةٌ بـMySQL؛ ومقابلُها في SQLite جملةٌ فرعية.
        // قاعدةُ الاختبارات تُبنى فارغةً، لكن نكتب المقابل كي تبقى الهجرة
        // صحيحةً على المحرّكين لا مُتخطّاةً على أحدهما.
        $filled = DB::connection()->getDriverName() === 'sqlite'
            ? DB::update('
                UPDATE warehouse_permits_products
                SET store_house_id = (
                    SELECT h.store_houses_id FROM warehouse_permits h
                    WHERE h.id = warehouse_permits_products.warehouse_permits_id
                )
                WHERE store_house_id IS NULL
                  AND EXISTS (
                    SELECT 1 FROM warehouse_permits h
                    WHERE h.id = warehouse_permits_products.warehouse_permits_id
                      AND h.store_houses_id IS NOT NULL
                  )
            ')
            : DB::update('
                UPDATE warehouse_permits_products p
                JOIN warehouse_permits h ON h.id = p.warehouse_permits_id
                SET p.store_house_id = h.store_houses_id
                WHERE p.store_house_id IS NULL
                  AND h.store_houses_id IS NOT NULL
            ');

        if ($filled > 0) {
            echo "  ✓ مُلئ {$filled} بنداً من مستودع رأس إذنه\n";
        }

        echo "\n";
    }

    public function down(): void
    {
        if (! Schema::hasColumn('warehouse_permits_products', 'store_house_id')) {
            return;
        }

        Schema::table('warehouse_permits_products', function (Blueprint $table) {
            try {
                $table->dropIndex('wpp_store_house_idx');
            } catch (\Throwable $e) {
            }

            $table->dropColumn('store_house_id');
        });
    }
};
