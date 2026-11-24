<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * تصنيف المنتج اختياريّ للخدمات والمنتجات الرقمية.
 *
 * كانت `products.category_id` عموداً إلزامياً (NOT NULL) والشاشة تفرضه على
 * كل الأنواع — بينما تصنيفات الشجرة مبنيّة للأصناف المخزنية/التصنيعية
 * (خام/نصف مصنّع/نهائي ووحدات القياس المرتبطة بالتصنيف) ولا تنطبق
 * بالضرورة على خدمةٍ تُؤدّى أو منتجٍ رقميّ يُسلَّم تنزيلاً.
 *
 * العمود يصير قابلاً للفراغ، وقاعدة التحقق في `ProductsRequest` تبقيه
 * **إلزامياً للمخزني والتجميعي** وتحرّره للخدمة والرقمي — فلا يتغيّر شيء
 * على الأصناف المخزنية.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('products') || ! Schema::hasColumn('products', 'category_id')) {
            return;
        }

        $this->repairBrokenStatusEnum();

        Schema::table('products', function (Blueprint $table) {
            $table->unsignedBigInteger('category_id')->nullable()->change();
        });
    }

    /**
     * قواعدُ قائمةٌ تحمل تعريفاً معطوباً للعمود `status`:
     *
     *     enum('active','unactive','','')
     *
     * قيمةٌ فارغة مكرَّرة — تعريفٌ لا تقبله MySQL أصلاً في وضعها الصارم،
     * دخل يوماً من DDL خام. وهو قنبلةٌ ساكنة: **أيُّ** `ALTER TABLE products`
     * — كتعديل `category_id` أدناه — يُعيد التحقق من الأعمدة كلِّها فيفشل
     * بـ«Column 'status' has duplicated value». يُعاد التعريف على قيم
     * `ProductStatus` الفعلية وحدها، ولا يُمَسّ إن كان سليماً.
     */
    private function repairBrokenStatusEnum(): void
    {
        if (DB::getDriverName() !== 'mysql' || ! Schema::hasColumn('products', 'status')) {
            return;
        }

        $type = (string) (DB::selectOne("show columns from `products` like 'status'")->Type ?? '');

        if (! str_contains($type, "''")) {
            return;     // التعريف سليم
        }

        // قيمةٌ خارج active/unactive (فارغة مثلاً) تُصحَّح قبل تضييق التعريف
        DB::table('products')
            ->whereNotIn('status', ['active', 'unactive'])
            ->update(['status' => 'active']);

        DB::statement("alter table `products` modify `status` enum('active','unactive') not null default 'active'");
    }

    public function down(): void
    {
        if (! Schema::hasTable('products') || ! Schema::hasColumn('products', 'category_id')) {
            return;
        }

        // لا يُعاد الإلزام وثمّة صفوفٌ فارغة — وإلا فشل الترحيل نفسه
        if (DB::table('products')->whereNull('category_id')->exists()) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->unsignedBigInteger('category_id')->nullable(false)->change();
        });
    }
};
