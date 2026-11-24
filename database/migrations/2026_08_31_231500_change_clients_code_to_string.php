<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * كود العميل كان `int(11)`، ونظام الترقيم الموحّد (`next_document_number('customer')`)
 * ينتج صيغة القسم المضبوطة فعلاً في `serial_settings` (بادئة CUST- + أرقام) — نصٌّ لا
 * يدخل في عمودٍ رقمي. الترقية إلى `varchar` تتيح توحيد توليد الكود مع بقية النظام
 * دون فقد فهرس `idx_clients_code` (يبقى قائماً على نفس العمود بعد `MODIFY`).
 *
 * `MODIFY COLUMN` مباشرة لا `->change()`: لا `doctrine/dbal` في هذا التثبيت.
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

        DB::statement('ALTER TABLE clients MODIFY code VARCHAR(20) NULL');
    }

    public function down(): void
    {
        // SQLite (بيئة الاختبارات وحدها عبر phpunit.xml) لا يفهم
        // ALTER TABLE ... MODIFY، وقاعدةُ الاختبارات تُبنى من الصفر في كل مرة
        // فالعمودُ يخرج من هجرة الإنشاء بشكلٍ صالحٍ أصلاً.
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        // تحويلٌ فقدانيّ عمداً: أكواد نصّية (مثل CUST-00001) لا تعود رقماً صحيحاً.
        DB::statement("UPDATE clients SET code = NULL WHERE code IS NOT NULL AND code NOT REGEXP '^[0-9]+$'");
        DB::statement('ALTER TABLE clients MODIFY code INT(11) NULL');
    }
};
