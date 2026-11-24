<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * يهبط بـ`return_default_received` و`notice_default_received` إلى مطفَأين —
 * عكس ما رفعهما إليه ترحيل `enable_return_default_received`.
 *
 * القرار تغيّر مرّة أخرى: تفعيلهما كان يُخرج البضاعة من المخزن فور حفظ
 * المرتجع أو الإشعار المدين بلا شرط، حتى لفاتورةٍ أصليةٍ لم تُستلَم بعد —
 * أي بلا بضاعةٍ في المخزون أصلاً لتخرج منه. الإخراج الآن لا يحدث إلا
 * بتأشيرٍ صريح عند الحفظ (خانة «تم الاستلام») أو لاحقاً من زرّ «تأكيد
 * الاستلام» في شاشة العرض — نفس ما تفعله فاتورة الشراء بمفتاحها
 * `default_received_invoices` (الذي لم يُمَسّ هنا).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('purchase_invoice_settings')) {
            return;
        }

        DB::table('purchase_invoice_settings')
            ->whereIn('setting_key', ['return_default_received', 'notice_default_received'])
            ->update(['is_active' => false, 'updated_at' => now()]);

        Cache::forget('purchase_settings.map');
    }

    public function down(): void
    {
        if (! Schema::hasTable('purchase_invoice_settings')) {
            return;
        }

        DB::table('purchase_invoice_settings')
            ->whereIn('setting_key', ['return_default_received', 'notice_default_received'])
            ->update(['is_active' => true, 'updated_at' => now()]);

        Cache::forget('purchase_settings.map');
    }
};
