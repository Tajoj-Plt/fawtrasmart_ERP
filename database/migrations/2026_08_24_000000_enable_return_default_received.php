<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * يرفع `return_default_received` إلى مفعَّل لمن ثبَّته الترحيل السابق مطفأً.
 *
 * القرار تغيّر: المرتجع كان يبقى «معلَّقاً» في المخزون حتى يزور الموظّف
 * شاشة العرض ويضغط «تأكيد الاستلام» يدوياً — الآن يخرج البضاعة من المخزن
 * فور الحفظ افتراضياً، بنفس سلوك `default_received_invoices` لفاتورة
 * الشراء. مفتاح `notice_default_received` الجديد (نظيره للإشعار المدين)
 * تكفي `PurchaseSettings::sync()` لزرعه — يُستدعى من شاشة الإعدادات.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('purchase_invoice_settings')) {
            return;
        }

        DB::table('purchase_invoice_settings')
            ->where('setting_key', 'return_default_received')
            ->update(['is_active' => true, 'updated_at' => now()]);

        Cache::forget('purchase_settings.map');
    }

    public function down(): void
    {
        if (! Schema::hasTable('purchase_invoice_settings')) {
            return;
        }

        DB::table('purchase_invoice_settings')
            ->where('setting_key', 'return_default_received')
            ->update(['is_active' => false, 'updated_at' => now()]);

        Cache::forget('purchase_settings.map');
    }
};
