<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * إضافة النوع «رقمي» إلى `products.type`.
 *
 * كان الـenum ثلاثة: `products` و`services` و`compiled`. والمنتج الرقمي
 * (اشتراك، ترخيص، ملفّ يُنزَّل) لا يوافق أيّاً منها: ليس خدمةً تُؤدّى، وليس
 * سلعةً لها رصيد. فكان يُسجَّل «منتجاً» فيدخل في أرصدة المستودعات وتقارير
 * الجرد بكمّياتٍ لا معنى لها.
 *
 * القدرات نفسها معرَّفة في `App\Enums\ProductType` — هذه الترحيلة تفتح
 * الباب في القاعدة فقط.
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

        if (! Schema::hasTable('products')) {
            return;
        }

        DB::statement(
            "ALTER TABLE `products` MODIFY `type`
             ENUM('products','services','compiled','digital') NOT NULL DEFAULT 'products'"
        );
    }

    public function down(): void
    {
        // SQLite (بيئة الاختبارات وحدها عبر phpunit.xml) لا يفهم
        // ALTER TABLE ... MODIFY، وقاعدةُ الاختبارات تُبنى من الصفر في كل مرة
        // فالعمودُ يخرج من هجرة الإنشاء بشكلٍ صالحٍ أصلاً.
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        if (! Schema::hasTable('products')) {
            return;
        }

        // الرقميّ أقرب إلى الخدمة منه إلى السلعة: كلاهما بلا رصيد.
        DB::table('products')->where('type', 'digital')->update(['type' => 'services']);

        DB::statement(
            "ALTER TABLE `products` MODIFY `type`
             ENUM('products','services','compiled') NOT NULL DEFAULT 'products'"
        );
    }
};
