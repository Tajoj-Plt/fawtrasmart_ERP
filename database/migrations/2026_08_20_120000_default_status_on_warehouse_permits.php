<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * `status` عمودٌ إلزامي بلا قيمة افتراضية، ومعظم مواضع إنشاء أذون المخزن
     * في النظام لا تملؤه — فيسقط الحفظ بخطأ SQL خام:
     * Field 'status' doesn't have a default value.
     *
     * الإذن الجديد «معلَّق» حتى يُعتمَد، فهذه هي القيمة الافتراضية الصحيحة.
     */
    public function up(): void
    {
        // SQLite (بيئة الاختبارات وحدها عبر phpunit.xml) لا يفهم
        // ALTER TABLE ... MODIFY، وقاعدةُ الاختبارات تُبنى من الصفر في كل مرة
        // فالعمودُ يخرج من هجرة الإنشاء بشكلٍ صالحٍ أصلاً.
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE `warehouse_permits` MODIFY `status` VARCHAR(50) NOT NULL DEFAULT 'pending'");
        DB::table('warehouse_permits')->whereNull('status')->orWhere('status', '')->update(['status' => 'pending']);
    }

    public function down(): void
    {
        // SQLite (بيئة الاختبارات وحدها عبر phpunit.xml) لا يفهم
        // ALTER TABLE ... MODIFY، وقاعدةُ الاختبارات تُبنى من الصفر في كل مرة
        // فالعمودُ يخرج من هجرة الإنشاء بشكلٍ صالحٍ أصلاً.
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE `warehouse_permits` MODIFY `status` VARCHAR(50) NOT NULL');
    }
};
