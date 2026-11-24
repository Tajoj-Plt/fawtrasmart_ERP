<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * مفتاح «كشف الحساب» في بوابة العميل.
 *
 * `client_permissions` جدولُ مفاتيحِ البوابة، و`ClientPortalPermissions`
 * يعدّ **كلَّ مفتاحٍ لا صفَّ له في الجدول مطفأً** — فمفتاحٌ يُعرَّف في الكود
 * بلا صفٍّ هنا يظهر في شاشة الإعدادات موسوماً «غير مزروع في القاعدة»
 * ومعطَّلاً، ولا يمكن تفعيله. الزرعُ إذن شرطُ وجوده لا تحسيناً.
 *
 * **مطفأٌ عند الزرع** (`is_active = 0`): كشف الحساب يعرض حركة الحساب
 * المحاسبي كاملةً — أرصدةً افتتاحيةً وتسويات. فتحُه لكلّ العملاء بمجرّد رفع
 * تحديثٍ قرارٌ لا يُتّخذ نيابةً عن صاحب النظام؛ يفعّله من شاشة «صلاحيات
 * بوابة العميل» حين يريد.
 */
return new class extends Migration
{
    private const KEY = 'view_account_statement';

    public function up(): void
    {
        if (! Schema::hasTable('client_permissions')) {
            return;
        }

        if (DB::table('client_permissions')->where('key', self::KEY)->exists()) {
            return;
        }

        DB::table('client_permissions')->insert([
            'key' => self::KEY,
            'name' => 'مشاهدة كشف حسابه',
            'is_active' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->forgetPortalCache();
    }

    public function down(): void
    {
        if (! Schema::hasTable('client_permissions')) {
            return;
        }

        DB::table('client_permissions')->where('key', self::KEY)->delete();

        $this->forgetPortalCache();
    }

    private function forgetPortalCache(): void
    {
        try {
            \App\Support\ClientPortalPermissions::forget();
        } catch (\Throwable $e) {
            // كاشٌ غير مهيّأ في هذا السياق — ينتهي بنفسه خلال ساعة
        }
    }
};
