<?php

use App\Support\SchemaGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * عمودان مفقودان في `manufactur_orders` — وحالتان خارج الـ ENUM.
 *
 * ── (١) الحالة ───────────────────────────────────────────────────────
 *
 * الهجرة `2026_01_11_164812` تضبط العمود:
 *
 *     ENUM('pending', 'materials_received', 'completed', 'cancelled')
 *
 * لكنّ المتحكّم يكتب حالتين خارج القائمة:
 *
 *     closeOrder:876   'status' => 'closed'
 *     المتحكّم والقوالب تشيران إلى 'in_progress' في اثني عشر موضعاً
 *
 * في MySQL غير الصارم القيمة خارج الـENUM تُخزَّن **سلسلةً فارغة** بلا
 * خطأ — فالأمر يفقد حالته عند الإغلاق، ويسقط من كل مرشِّح، ويُعرض
 * «غير محدد». وفي الوضع الصارم يسقط الطلب بخطأ 1265.
 *
 * إمّا أن تُضاف الحالتان وإمّا أن يُمنع كتابتهما. الشاشات تستعملهما
 * (`action-buttons.blade.php`، `finish-order-modal.blade.php`،
 * `orders/index.blade.php`) فالإضافة هي الصواب.
 *
 * ── (٢) `warehouse_permit_id` ────────────────────────────────────────
 *
 * `completeOrder` تكتبه:
 *
 *     $order->update([... 'warehouse_permit_id' => $permit->id]);
 *
 * وهو **غير موجود لا في `$fillable` ولا في الجدول**. فيُهمَل مرتين
 * صامتاً — ثم يبحث `undoCompletion` عن الإذن فلا يجده، فيُثبّت بنجاح
 * دون أن يعكس شيئاً، ويبقى المنتج في المخزون بلا أمر يقابله.
 *
 * ── (٣) الكميات الكسرية ──────────────────────────────────────────────
 *
 * هذه الهجرة **لا تعالجها** — انظر الهجرة التالية (`..._000006`).
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── الحالة ──
        if (Schema::hasTable('manufactur_orders')) {
            try {
                if (DB::connection()->getDriverName() !== 'sqlite') {
                    DB::statement(
                    "ALTER TABLE manufactur_orders MODIFY status
                     ENUM('pending','materials_received','in_progress','completed','closed','cancelled')
                     NOT NULL DEFAULT 'pending'
                     COMMENT 'حالة أمر التصنيع — انظر App\\\\Enums\\\\ManufacturOrderStatus'"
                );
                }

                echo "\n  ✓ وُسِّع ENUM الحالة ليشمل in_progress و closed\n";
            } catch (\Throwable $e) {
                echo "\n  ⚠️  تعذّر توسيع ENUM الحالة: ".$e->getMessage()."\n";
                echo "      إن كان العمود VARCHAR سلفاً فلا حاجة — تجاهل.\n";
            }

            // صفوف فقدت حالتها بالكتابة خارج الـENUM
            $orphans = DB::table('manufactur_orders')
                ->where(function ($q) {
                    $q->whereNull('status')->orWhere('status', '');
                })
                ->count();

            if ($orphans > 0) {
                echo "\n  ⚠️  {$orphans} أمراً بحالة فارغة — الأرجح أنها أُغلقت فكُتبت 'closed'\n";
                echo "      خارج الـENUM. راجعها يدوياً قبل تعيين حالة:\n";
                echo "      SELECT id, code, name, completed_at, finished_at\n";
                echo "      FROM manufactur_orders WHERE status = '' OR status IS NULL;\n\n";
            }
        }

        // ── warehouse_permit_id ──
        if (Schema::hasTable('manufactur_orders') && ! Schema::hasColumn('manufactur_orders', 'warehouse_permit_id')) {
            Schema::table('manufactur_orders', function (Blueprint $table) {
                if (! Schema::hasColumn('manufactur_orders', 'warehouse_permit_id')) {
                    $table->unsignedBigInteger('warehouse_permit_id')
                        ->nullable()
                        ->after('completed_at')
                        ->comment('إذن إضافة المنتج التام — يُعكس به التراجع عن الإتمام');
                }

                if (! SchemaGuard::hasIndex('manufactur_orders', 'mo_warehouse_permit_idx')) {
                    $table->index('warehouse_permit_id', 'mo_warehouse_permit_idx');
                }
            });

            echo "  ✓ أُضيف العمود warehouse_permit_id\n";
        }

        // ── ربط أذونات الإتمام القائمة بأوامرها ──
        //
        // أوامر أُتمّت قبل هذا الإصلاح لا تحمل معرّف إذنها. نستنتجه من
        // الإذن نفسه: كل إذن إضافة يحمل `reference_id` للأمر.
        // UPDATE ... JOIN صيغةٌ خاصّةٌ بـMySQL. قاعدةُ الاختبارات (SQLite) تُبنى
        // فارغةً فلا توجد أوامرُ قديمةٌ تُستدرك أصلاً.
        if (Schema::hasColumn('manufactur_orders', 'warehouse_permit_id')
            && DB::connection()->getDriverName() !== 'sqlite') {
            $linked = DB::update("
                UPDATE manufactur_orders o
                JOIN (
                    SELECT reference_id, MAX(id) AS permit_id
                    FROM warehouse_permits
                    WHERE permission_type = 1
                      AND permission_source_id IN (3, 15)
                      AND reference_id IS NOT NULL
                    GROUP BY reference_id
                ) p ON p.reference_id = o.id
                SET o.warehouse_permit_id = p.permit_id
                WHERE o.warehouse_permit_id IS NULL
                  AND o.status IN ('completed', 'closed')
            ");

            if ($linked > 0) {
                echo "  ✓ رُبط {$linked} أمراً مكتملاً بإذنه المخزني\n";
            }
        }

        echo "\n";
    }

    public function down(): void
    {
        if (Schema::hasColumn('manufactur_orders', 'warehouse_permit_id')) {
            Schema::table('manufactur_orders', function (Blueprint $table) {
                try {
                    $table->dropIndex('mo_warehouse_permit_idx');
                } catch (\Throwable $e) {
                }
                $table->dropColumn('warehouse_permit_id');
            });
        }

        try {
            if (DB::connection()->getDriverName() !== 'sqlite') {
                DB::statement(
                "ALTER TABLE manufactur_orders MODIFY status
                 ENUM('pending','materials_received','completed','cancelled')
                 DEFAULT 'pending'"
            );
            }
        } catch (\Throwable $e) {
        }
    }
};
