<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * تمييز الخزائن والمخازن التابعة للمتجر الإلكتروني.
 *
 * كانت شاشات المتجر (StoreTreasuryController/StoreStorehouseController وقوائم
 * الاختيار في StorePermitsController/StorePurchaseInvoiceController/إعدادات
 * المتجر) تعرض **كل** خزائن النظام ومخازنه — بما فيها خزائن الموظفين
 * الشخصية ومخازن الفروع الأخرى التي لا علاقة لها بالمتجر. هذان العَلَمان
 * يحصران ما يظهر في شاشات المتجر بما هو تابعٌ له فعلاً.
 *
 * نفس اصطلاح `clients.is_store_client` (2026_01_05_154257): بوليان بسيط
 * افتراضه false، لا مفهوم فرعٍ أو تعدّد.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('accounts') && ! Schema::hasColumn('accounts', 'is_store_treasury')) {
            Schema::table('accounts', function (Blueprint $table) {
                if (! Schema::hasColumn('accounts', 'is_store_treasury')) {
                    $table->boolean('is_store_treasury')->default(false)->after('is_active')
                        ->comment('خزينة/حساب بنكي تابع للمتجر الإلكتروني — يظهر في قوائم اختيار المتجر فقط');
                }
            });
        }

        if (Schema::hasTable('store_houses') && ! Schema::hasColumn('store_houses', 'is_store_storehouse')) {
            Schema::table('store_houses', function (Blueprint $table) {
                if (! Schema::hasColumn('store_houses', 'is_store_storehouse')) {
                    $table->boolean('is_store_storehouse')->default(false)->after('branch_id')
                        ->comment('مخزن تابع للمتجر الإلكتروني — يظهر في قوائم اختيار المتجر فقط');
                }
            });
        }

        $this->backfillCurrentDefaults();
    }

    /**
     * تعليم المخزن/الخزينة المُعيَّنين حالياً كافتراضيَين في إعدادات المتجر
     * (`default_storehouse_id`/`default_treasury_id`) — وإلا يفقد المتجر
     * افتراضاته الحالية فور إضافة العَلَم، فتظهر قوائم الاختيار فارغة تماماً
     * ولا يستطيع أحد إتمام طلبٍ حتى يُعلَّم شيءٌ يدوياً من الإعدادات.
     */
    private function backfillCurrentDefaults(): void
    {
        if (! Schema::hasTable('online_store_settings')) {
            return;
        }

        $storehouseId = DB::table('online_store_settings')->where('key', 'default_storehouse_id')->value('value');
        if ($storehouseId) {
            DB::table('store_houses')->where('id', (int) $storehouseId)->update(['is_store_storehouse' => true]);
        }

        $treasuryId = DB::table('online_store_settings')->where('key', 'default_treasury_id')->value('value');
        if ($treasuryId) {
            DB::table('accounts')->where('id', (int) $treasuryId)->update(['is_store_treasury' => true]);
        }

        // القيمة القديمة غير المسمّاة في الإعدادات — انظر التعليق في OnlineOrderController
        $safeId = DB::table('online_store_settings')->where('key', 'default_safe_id')->value('value');
        if ($safeId) {
            DB::table('accounts')->where('id', (int) $safeId)->update(['is_store_treasury' => true]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('accounts', 'is_store_treasury')) {
            Schema::table('accounts', function (Blueprint $table) {
                $table->dropColumn('is_store_treasury');
            });
        }

        if (Schema::hasColumn('store_houses', 'is_store_storehouse')) {
            Schema::table('store_houses', function (Blueprint $table) {
                $table->dropColumn('is_store_storehouse');
            });
        }
    }
};
