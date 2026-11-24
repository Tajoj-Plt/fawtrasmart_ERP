<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * الكميات الكسرية — `integer` لا يكفي مصنعاً.
 *
 * ── المشكلة ──────────────────────────────────────────────────────────
 *
 * الهجرتان الأصليتان تعرّفان الكمية عدداً صحيحاً:
 *
 *     // create_product_details_table
 *     $table->integer('quantity');
 *
 *     // create_warehouse_permits_products_table
 *     $table->integer('quantity');
 *     $table->integer('stock_before')->default(0);
 *     $table->integer('stock_after')->default(0);
 *
 * فكل كمية كسرية تُبتر عند الحفظ: 2.5 كجم تصير 2، و0.75 لتر تصير 0.
 * وفي التصنيع هذا هو الوضع الغالب لا الاستثناء — المواد الخام تُصرف
 * بالكيلو والمتر واللتر.
 *
 * والأثر مركّب: البتر يقع في المخزون **وفي بند الإذن معاً**، بينما
 * `total` عمود عشري — فينتج سطرٌ كميته 2 وإجماليه محسوبٌ على 2.5،
 * ولا يطابق `quantity × unit_price` أبداً.
 *
 * ── ⚠️ قبل التشغيل ───────────────────────────────────────────────────
 *
 * هذه هجرة **تغيّر نوع عمود** في جدولين قد يكونان كبيرين:
 *
 *   · خذ نسخة احتياطية أولاً — `mysqldump`
 *   · شغّلها في وقت هدوء: MySQL يقفل الجدول أثناء `ALTER`
 *   · جدول `product_details` بحجم عدد (المنتجات × المستودعات)
 *
 * التحويل من `integer` إلى `decimal` **لا يفقد بيانات**: كل عدد صحيح
 * يُمثَّل تماماً. البتر الذي وقع سابقاً لا يُستعاد — لكنه يتوقف.
 *
 * ── لماذا (18,4) ─────────────────────────────────────────────────────
 *
 * أربع خانات عشرية تكفي الغرام في الكيلو والمليلتر في اللتر، وهي دقة
 * `unit_price` المعتمدة في `WarehousePermitsProducts::unitPricePerInventoryQuantity`
 * (`round($lineTotal / $inventoryQuantity, 4)`) فتتّسق معها.
 *
 * ── هذه الهجرة اختيارية ──────────────────────────────────────────────
 *
 * إن كانت كل موادك تُصرف بالحبة فلا تحتاجها. الباقي يعمل بدونها.
 */
return new class extends Migration
{
    public function up(): void
    {
        // SQLite (بيئة الاختبارات وحدها عبر phpunit.xml) لا يفهم
        // ALTER TABLE ... MODIFY، وقاعدةُ الاختبارات تُبنى من الصفر في كل مرة
        // فالعمودُ يخرج من هجرة الإنشاء بشكلٍ صالحٍ أصلاً.
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        // ── تشخيص قبل التغيير ──
        $this->reportTruncation();

        if (Schema::hasTable('product_details')) {
            try {
                DB::statement('ALTER TABLE product_details MODIFY quantity DECIMAL(18,4) NOT NULL DEFAULT 0');
                echo "  ✓ product_details.quantity → DECIMAL(18,4)\n";
            } catch (\Throwable $e) {
                echo '  ⚠️  product_details.quantity: '.$e->getMessage()."\n";
            }
        }

        if (Schema::hasTable('warehouse_permits_products')) {
            foreach (['quantity', 'stock_before', 'stock_after'] as $col) {
                if (! Schema::hasColumn('warehouse_permits_products', $col)) {
                    continue;
                }

                try {
                    if (DB::connection()->getDriverName() !== 'sqlite') {
                        DB::statement("ALTER TABLE warehouse_permits_products MODIFY `$col` DECIMAL(18,4) NOT NULL DEFAULT 0");
                    }
                    echo "  ✓ warehouse_permits_products.$col → DECIMAL(18,4)\n";
                } catch (\Throwable $e) {
                    echo "  ⚠️  warehouse_permits_products.$col: ".$e->getMessage()."\n";
                }
            }
        }

        // ── بنود أوامر التصنيع وقوائم المواد ──
        //
        // قد تكون عشرية سلفاً — الهجرة `make_manufactur_orders_items_fields_nullable`
        // تلمسها. نتحقّق بدل الافتراض.
        foreach ([
            'manufactur_orders_items' => ['raw_quantity', 'end_life_quantity', 'operating_time'],
            'production_materials_items' => ['raw_quantity', 'end_life_quantity', 'operating_time'],
        ] as $table => $cols) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($cols as $col) {
                if (! Schema::hasColumn($table, $col)) {
                    continue;
                }

                $type = $this->columnType($table, $col);

                if (! str_starts_with(strtolower($type), 'int') && ! str_starts_with(strtolower($type), 'bigint')) {
                    continue;   // عشري سلفاً
                }

                try {
                    if (DB::connection()->getDriverName() !== 'sqlite') {
                        DB::statement("ALTER TABLE `$table` MODIFY `$col` DECIMAL(18,4) NULL");
                    }
                    echo "  ✓ $table.$col → DECIMAL(18,4)\n";
                } catch (\Throwable $e) {
                    echo "  ⚠️  $table.$col: ".$e->getMessage()."\n";
                }
            }
        }

        echo "\n";
    }

    public function down(): void
    {
        // SQLite (بيئة الاختبارات وحدها عبر phpunit.xml) لا يفهم
        // ALTER TABLE ... MODIFY، وقاعدةُ الاختبارات تُبنى من الصفر في كل مرة
        // فالعمودُ يخرج من هجرة الإنشاء بشكلٍ صالحٍ أصلاً.
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        // ⚠️ العودة إلى integer **تبتر** كل كسرٍ كُتب بعد الترقية.
        //    لا نعكس تلقائياً — أعِدها يدوياً إن أردت، بعد نسخة احتياطية.
        echo "\n  · لم يُعكس شيء: العودة إلى integer تبتر الكميات الكسرية.\n";
        echo "    أعِدها يدوياً إن كنت متأكداً.\n\n";
    }

    /** كم صفاً يحمل كمية بُترت فعلاً؟ */
    private function reportTruncation(): void
    {
        if (! Schema::hasTable('warehouse_permits_products')) {
            return;
        }

        try {
            // سطرٌ إجماليه لا يساوي الكمية × السعر يشي ببترٍ في الكمية
            $suspect = DB::table('warehouse_permits_products')
                ->whereRaw('quantity > 0 AND total > 0')
                ->whereRaw('ABS(total - (quantity * unit_price)) > GREATEST(0.05, total * 0.02)')
                ->count();

            if ($suspect > 0) {
                echo "\n  ℹ️  {$suspect} بند إذن إجماليه لا يطابق الكمية × السعر —\n";
                echo "      الأرجح أنها كميات كسرية بُترت قبل هذه الهجرة.\n";
                echo "      لا تُصحَّح تلقائياً: الكسر المفقود لا يُستعاد.\n";
            }
        } catch (\Throwable $e) {
        }
    }

    private function columnType(string $table, string $column): string
    {
        try {
            $row = DB::selectOne("SHOW COLUMNS FROM `$table` LIKE ?", [$column]);

            return $row->Type ?? '';
        } catch (\Throwable $e) {
            return '';
        }
    }
};
