<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * «الحساب الفرعي» في أذون المخزن صار يشير إلى حسابٍ في شجرة الحسابات،
     * وعمود tinyint لا يتّسع لمعرّف حساب (يُقصَّ عند 127 بلا خطأ).
     */
    public function up(): void
    {
        // SQLite (بيئة الاختبارات وحدها عبر phpunit.xml) لا يفهم
        // ALTER TABLE ... MODIFY، وقاعدةُ الاختبارات تُبنى من الصفر في كل مرة
        // فالعمودُ يخرج من هجرة الإنشاء بشكلٍ صالحٍ أصلاً.
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE `warehouse_permits` MODIFY `sub_account` BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        // SQLite (بيئة الاختبارات وحدها عبر phpunit.xml) لا يفهم
        // ALTER TABLE ... MODIFY، وقاعدةُ الاختبارات تُبنى من الصفر في كل مرة
        // فالعمودُ يخرج من هجرة الإنشاء بشكلٍ صالحٍ أصلاً.
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE `warehouse_permits` MODIFY `sub_account` TINYINT(4) NULL');
    }
};
