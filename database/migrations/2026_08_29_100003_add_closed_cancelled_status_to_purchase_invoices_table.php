<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * حالتا «مغلق» و«ملغى» لأمر الشراء — دورة الاستلام الجديدة تحتاج طرفاً
 * نهائياً غير «تحويل إلى فاتورة»: أمرٌ استُلم بالكامل ولا فاتورة أخرى
 * تُنتظَر منه يُغلَق، وأمرٌ لم يُستلم منه شيء بعد يُلغى، بلا أن يبقيا
 * معلَّقين في «معتمد» إلى الأبد.
 *
 * الصفوف القائمة لا تتغيّر: التعداد يتّسع فحسب — نفس نمط
 * `2026_08_27_180000_add_expired_status_to_quotes.php`.
 */
return new class extends Migration
{
    private const STATUSES = "'disagree','approval','Under Review','convert invoice','closed','cancelled'";

    private const STATUSES_BEFORE = "'disagree','approval','Under Review','convert invoice'";

    public function up(): void
    {
        // SQLite (بيئة الاختبارات وحدها عبر phpunit.xml) لا يفهم
        // ALTER TABLE ... MODIFY، وقاعدةُ الاختبارات تُبنى من الصفر في كل مرة
        // فالعمودُ يخرج من هجرة الإنشاء بشكلٍ صالحٍ أصلاً.
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        if (Schema::hasTable('purchase_invoices')) {
            DB::statement('ALTER TABLE `purchase_invoices` MODIFY `status` ENUM('.self::STATUSES.") NULL DEFAULT 'Under Review'");
        }
    }

    public function down(): void
    {
        // SQLite (بيئة الاختبارات وحدها عبر phpunit.xml) لا يفهم
        // ALTER TABLE ... MODIFY، وقاعدةُ الاختبارات تُبنى من الصفر في كل مرة
        // فالعمودُ يخرج من هجرة الإنشاء بشكلٍ صالحٍ أصلاً.
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        if (Schema::hasTable('purchase_invoices')) {
            DB::table('purchase_invoices')->whereIn('status', ['closed', 'cancelled'])->update(['status' => 'approval']);

            DB::statement('ALTER TABLE `purchase_invoices` MODIFY `status` ENUM('.self::STATUSES_BEFORE.") NULL DEFAULT 'Under Review'");
        }
    }
};
