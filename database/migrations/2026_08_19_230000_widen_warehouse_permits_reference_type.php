<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `warehouse_permits.reference_type`: من enum بقيمتين إلى نصّ.
 *
 * **العلّة:** العمود أُنشئ `enum('normal','purchase_invoice')`، بينما النظام
 * يكتب فيه اليوم ثمانيَ قيم على الأقل من خمس وحدات:
 *
 *     purchase_invoice · purchase_return · debit_note      (المشتريات)
 *     Invoice · SalesReturn · CreditNote                   (المبيعات)
 *     RewardRedemption                                     (المنظومة)
 *
 * وMySQL في الوضع الصارم يردّ ما ليس في القائمة خطأً لا تحذيراً:
 *
 *     SQLSTATE[01000]: Warning: 1265 Data truncated for column 'reference_type'
 *
 * فيسقط حفظ الإشعار المدين ومرتجع الشراء كلّه عند إنشاء الإذن المخزني —
 * بعد أن يكون القيد المحاسبي قد كُتب، فتُلغى المعاملة ويعود المستخدم برسالة
 * قاعدة بيانات لا معنى لها عنده.
 *
 * **العلاج:** نصّ لا قائمةَ مغلقة. الأنواع هنا اسمٌ للجهة التي أصدرت الإذن،
 * وهي تكثر مع كل وحدة جديدة؛ وحصرها في enum يعني ترحيلاً عند كل إضافة —
 * وسقوطاً صامتاً متى نُسي. القيمة الافتراضية `normal` تبقى كما كانت.
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

        if (! Schema::hasTable('warehouse_permits')) {
            return;
        }

        // `change()` عبر Doctrine لا يحتمل enum، فيُكتب الـDDL مباشرةً
        DB::statement("ALTER TABLE `warehouse_permits`
            MODIFY `reference_type` VARCHAR(50) NULL DEFAULT 'normal'");
    }

    public function down(): void
    {
        // SQLite (بيئة الاختبارات وحدها عبر phpunit.xml) لا يفهم
        // ALTER TABLE ... MODIFY، وقاعدةُ الاختبارات تُبنى من الصفر في كل مرة
        // فالعمودُ يخرج من هجرة الإنشاء بشكلٍ صالحٍ أصلاً.
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        if (! Schema::hasTable('warehouse_permits')) {
            return;
        }

        // العودة إلى القائمة المغلقة تُتلف كل قيمة خارجها، فتُردّ إلى
        // `normal` أولاً حتى لا يفشل التحويل أو تضيع الصفوف
        DB::table('warehouse_permits')
            ->whereNotIn('reference_type', ['normal', 'purchase_invoice'])
            ->update(['reference_type' => 'normal']);

        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE `warehouse_permits`
            MODIFY `reference_type` ENUM('normal','purchase_invoice') NULL DEFAULT 'normal'");
        }
    }
};
