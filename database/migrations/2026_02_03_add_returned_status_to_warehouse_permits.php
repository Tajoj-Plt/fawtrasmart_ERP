<?php

use App\Support\SchemaGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // SQLite (بيئة الاختبارات وحدها عبر phpunit.xml) لا يفهم
        // ALTER TABLE ... MODIFY، وقاعدةُ الاختبارات تُبنى من الصفر في كل مرة
        // فالعمودُ يخرج من هجرة الإنشاء بشكلٍ صالحٍ أصلاً.
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        // الهجرةُ `2026_05_02_195659` توسّع العمودَ لاحقاً إلى VARCHAR(50).
        // فعلى قاعدةٍ بلغت تلك الحالةَ سلفاً — كلُّ تركيبٍ قائم — لا معنى
        // لإعادته ENUM: تضييقٌ يليه توسيعٌ في الهجرة التالية، وبينهما خطرُ
        // بترِ كل قيمةٍ خارج القائمة. نتخطّاها ما لم يكن العمودُ ENUM بعد.
        $type = SchemaGuard::columnType('warehouse_permits', 'status');

        if ($type !== null && ! str_starts_with($type, 'enum(')) {
            return;
        }

        // تحديث حقل status لإضافة قيمة 'returned'
        //
        // ⚠️ `apporod` قيمةُ الموافقة الفعلية في هذا النظام — بخطأٍ إملائي
        // متعمَّد، موثَّقٍ في `ManufacturingSources::STATUS_APPROVED` ومطابَقٍ
        // حرفياً في أكثر من ثلاثين موضعاً. إسقاطُها من القائمة يبتر كلَّ
        // أذون الموافقة إلى '' بلا إنذار — MySQL يعدّه تحذيراً لا خطأ.
        DB::statement("ALTER TABLE warehouse_permits MODIFY COLUMN status ENUM('pending', 'apporod', 'approved', 'rejected', 'returned', '') DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // SQLite (بيئة الاختبارات وحدها عبر phpunit.xml) لا يفهم
        // ALTER TABLE ... MODIFY، وقاعدةُ الاختبارات تُبنى من الصفر في كل مرة
        // فالعمودُ يخرج من هجرة الإنشاء بشكلٍ صالحٍ أصلاً.
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        // إرجاع الحقل للقيم الأصلية — بإبقاء `apporod` لنفس السبب أعلاه.
        DB::statement("ALTER TABLE warehouse_permits MODIFY COLUMN status ENUM('pending', 'apporod', 'approved', 'rejected', '') DEFAULT 'pending'");
    }
};
