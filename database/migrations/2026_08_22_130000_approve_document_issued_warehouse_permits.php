<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * الأذون الصادرة عن مستندات المبيعات تُصبح موافَقاً عليها.
 *
 * **لماذا؟**
 *
 * إذنُ المخزن الصادر عن فاتورة أو مرتجع أو إشعارٍ دائن ليس طلباً ينتظر
 * موافقة: الحركة وقعت فعلاً — خُصمت الكمّية أو أُضيفت — وقُيِّدت محاسبياً
 * مع المستند نفسه. تركُه `pending` يجعل شاشة الأذون تعرض طابوراً من
 * «طلبات» لا يملك أحدٌ ردّها، ويُخرج الحركة من تقارير الأذون الموافَق
 * عليها بينما المخزون تحرّك بها فعلاً.
 *
 * والسابقة قائمة: `2026_08_20_160000_approve_permits_that_have_journal_entries`
 * فعلت هذا نفسه للأذون التي لها قيدٌ محاسبي.
 *
 * **النطاق:** أذون المبيعات وحدها (`Invoice`, `SalesReturn`, `CreditNote`)
 * وما زال `pending`. أذونُ المخزن اليدوية — التحويل والجرد والتالف — تبقى
 * في دورة موافقتها، فهي طلباتٌ حقيقية يُراجعها مسؤول المخزن.
 *
 * `apporod` هي قيمة الموافقة المعتمدة في هذا النظام (كذا تُكتب في الجدول
 * وفي أكثر من ثلاثين موضعاً في الكود) — تُطابَق ولا تُصحَّح هنا، فتصحيحها
 * يتطلّب ترحيلاً شاملاً لا مكان له في هجرة بيانات.
 */
return new class extends Migration
{
    /** أنواع المراجع الصادرة عن مستندات المبيعات. */
    private array $documentTypes = ['Invoice', 'SalesReturn', 'CreditNote'];

    public function up(): void
    {
        if (! Schema::hasTable('warehouse_permits') || ! Schema::hasColumn('warehouse_permits', 'status')) {
            return;
        }

        DB::table('warehouse_permits')
            ->whereIn('reference_type', $this->documentTypes)
            ->where('status', 'pending')
            ->update(['status' => 'apporod']);
    }

    /**
     * لا تراجُع: لا سبيل إلى معرفة أيُّ إذنٍ كان `pending` قبل الترحيل،
     * وإرجاعُها كلّها إلى الانتظار يُدخل أذوناً موافَقاً عليها أصلاً في
     * طابور مراجعةٍ لا معنى له.
     */
    public function down(): void
    {
        // متعمَّد: لا شيء.
    }
};
