<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * أذونُ تبديل البضاعة تُصبح موافَقاً عليها.
 *
 * `ProductExchangeController::applyExchangeEffects()` يُحرّك المخزون ويُرحّل
 * القيدين مع الإذنين في اللحظة نفسها، لكنّه كان يُنشئهما بلا `status` فيأخذان
 * «معلَّق» من النموذج. و`JournalEntryObserver` لا يعتمدهما لأنّ قيد التبديل
 * (`PEX`) ليس من مراجع المخزن. فكلُّ تبديلٍ بعد `2026_08_20` ترك إذنين في
 * طابور الموافقة، واعتمادُ أيٍّ منهما من شاشة الأذون يُحرّك الكمّية مرّةً ثانية.
 *
 * النطاق: مصدرا التبديل وحدهما (27 صادر، 28 وارد) وما زال `pending`. نفسُ
 * سابقة `2026_08_22_130000_approve_document_issued_warehouse_permits`.
 */
return new class extends Migration
{
    /** `PermissionSources::PRODUCT_EXCHANGE` و`PRODUCT_EXCHANGE_RETURN`. */
    private array $sources = [27, 28];

    public function up(): void
    {
        if (! Schema::hasTable('warehouse_permits') || ! Schema::hasColumn('warehouse_permits', 'status')) {
            return;
        }

        DB::table('warehouse_permits')
            ->whereIn('permission_source_id', $this->sources)
            ->where('status', 'pending')
            ->update(['status' => 'apporod']);
    }

    /**
     * لا تراجُع: لا سبيل إلى معرفة أيُّ إذنٍ كان معلَّقاً قبل الترحيل.
     */
    public function down(): void
    {
        // متعمَّد: لا شيء.
    }
};
