<?php

use App\Support\SchemaGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('inventory_adjustments', function (Blueprint $table) {
            // إضافة نوع الجرد
            if (! Schema::hasColumn('inventory_adjustments', 'type')) {
                $table->enum('type', ['full', 'partial', 'spot', 'category'])->default('full')
                      ->comment('full: جرد شامل, partial: جرد دوري, spot: جرد مفاجئ, category: جرد حسب الصنف');
            }

            // إضافة حقول التتبع
            if (! Schema::hasColumn('inventory_adjustments', 'created_by')) {
                $table->unsignedBigInteger('created_by')->nullable();
            }
            if (! Schema::hasColumn('inventory_adjustments', 'adjusted_by')) {
                $table->unsignedBigInteger('adjusted_by')->nullable();
            }
            if (! Schema::hasColumn('inventory_adjustments', 'adjusted_at')) {
                $table->timestamp('adjusted_at')->nullable();
            }

            // إضافة حقول إضافية
            if (! Schema::hasColumn('inventory_adjustments', 'adjustment_reason')) {
                $table->text('adjustment_reason')->nullable()->comment('سبب التسوية');
            }
            if (! Schema::hasColumn('inventory_adjustments', 'reference_number')) {
                $table->string('reference_number')->nullable()->comment('رقم مرجعي للجرد');
            }
            if (! Schema::hasColumn('inventory_adjustments', 'categories')) {
                $table->json('categories')->nullable()->comment('فئات المنتجات المشمولة في الجرد');
            }

            /*
                توسيعُ حالات الجرد — لا استبدالُها بحذفٍ وإعادةِ إنشاء.

                كان هنا `$table->dropColumn('status')` يتلوه إنشاءٌ محروسٌ
                بـ`Schema::hasColumn(...)`. والحارس يُقيَّم **فوراً** على
                القاعدة، بينما `dropColumn` أمرٌ يُصفّ في الـBlueprint ولا
                يُنفَّذ إلا بعد انتهاء الإغلاق. فترتيبُ ما يقع فعلاً:

                    1. يُسأل الحارس: أموجودٌ `status`؟ — نعم (لم يُحذف بعد).
                    2. فلا يُضاف شيء.
                    3. ثم يُنفَّذ الحذف وحده.

                فالعمود يذهب ببياناته ولا يعود، وتسقط شاشة الجرد كلُّها
                بـ«Unknown column 'status'» عند أول إنشاء أو تصفية —
                `InventoryManagementController::store()` يكتب `'status' =>
                'draft'`، و`index()` يُصفّي به.

                والصواب: `->change()` تُوسِّع قائمة الحالات في مكانها. لا
                حذف، ولا صفٌّ يفقد حالته — القيمتان القديمتان (`draft`،
                `adjusted`) من ضمن الجديدة فتبقيان كما هما.

                وللقواعد التي فقدت العمود قبل هذا الإصلاح ترحيلُ استعادةٍ
                مستقل: `2026_09_09_120000_restore_inventory_adjustments_status`.
            */
            $states = [
                'draft',       // مسودة
                'in_progress', // قيد التنفيذ
                'completed',   // مكتمل
                'adjusted',    // تمت التسوية
                'cancelled',   // ملغي
            ];

            if (! Schema::hasColumn('inventory_adjustments', 'status')) {
                $table->enum('status', $states)->default('draft');
            } else {
                $table->enum('status', $states)->default('draft')->change();
            }

            // إضافة المفاتيح الأجنبية
            if (! SchemaGuard::hasForeignKey('inventory_adjustments', 'inventory_adjustments_created_by_foreign')) {
                $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            }
            if (! SchemaGuard::hasForeignKey('inventory_adjustments', 'inventory_adjustments_adjusted_by_foreign')) {
                $table->foreign('adjusted_by')->references('id')->on('users')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_adjustments', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropForeign(['adjusted_by']);
            $table->dropColumn([
                'type',
                'created_by',
                'adjusted_by',
                'adjusted_at',
                'adjustment_reason',
                'reference_number',
                'categories'
            ]);

            // إعادة قائمة الحالات إلى شكلها السابق — بـ`change()` لا بحذفٍ
            // وإعادةِ إنشاء، للسبب نفسه المشروح في `up()`
            $table->enum('status', ['draft', 'adjusted'])->default('draft')->change();
        });
    }
};
