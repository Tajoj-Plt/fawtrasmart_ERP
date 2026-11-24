<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * مفتاح أساسي وترقيم تلقائي لعمود `purchase_quotations.id`.
 *
 * **العطل**
 *
 * ترحيل الإنشاء كتب العمود هكذا:
 *
 *     $table->unsignedBigInteger('id');   // بدل $table->id()
 *
 * فخرج الجدول بلا مفتاح أساسي وبلا `AUTO_INCREMENT`. النتيجة أن كل حفظ
 * في قسم «طلبات عروض الأسعار» يسقط منذ اليوم الأول:
 *
 *     SQLSTATE[HY000]: 1364 Field 'id' doesn't have a default value
 *
 * ولهذا الجدول فارغ تماماً — لا لأن أحداً لم يستعمل الشاشة، بل لأنها لم
 * تكن قادرة على الحفظ أصلاً. الفهرس الوحيد كان `unique` على `code`.
 *
 * الجدول فارغ فلا خطر على بيانات قائمة؛ ومع ذلك يتحقّق الترحيل من غياب
 * المفتاح قبل أن يلمس شيئاً، فإعادة تشغيله لا تضرّ.
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

        if (! Schema::hasTable('purchase_quotations') || $this->hasPrimaryKey()) {
            return;
        }

        // المفتاح والترقيم في عبارة واحدة: MySQL يرفض `AUTO_INCREMENT`
        // على عمود غير مفهرس، فلا يصحّ فصلهما.
        DB::statement(
            'ALTER TABLE `purchase_quotations` '
            .'ADD PRIMARY KEY (`id`), '
            .'MODIFY `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT'
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

        if (! Schema::hasTable('purchase_quotations') || ! $this->hasPrimaryKey()) {
            return;
        }

        DB::statement('ALTER TABLE `purchase_quotations` MODIFY `id` BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE `purchase_quotations` DROP PRIMARY KEY');
    }

    protected function hasPrimaryKey(): bool
    {
        // Schema::getIndexes بديلٌ محمولٌ عن SHOW INDEX الخاص بـMySQL
        foreach (Schema::getIndexes('purchase_quotations') as $index) {
            if (($index['primary'] ?? false) || ($index['name'] ?? null) === 'PRIMARY') {
                return true;
            }
        }

        return false;
    }
};
