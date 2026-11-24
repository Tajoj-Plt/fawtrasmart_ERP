<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ضريبةُ «عرض سعر الشراء» كانت تُقرَّب إلى ريالٍ صحيح.
 *
 * `purchase_quotations_view.total_tax` كان `decimal(10,0)` — بلا كسور —
 * بينما أخواتُه كلُّها (`subtotal`, `total_discount`, `grand_total`,
 * `shipping_cost`, `shipping_tax`, `discount_amount`) `decimal(15,2)`،
 * وكذلك نظائرُه في `purchase_invoices`.
 *
 * فعرضٌ ضريبتُه ١٤٥٫٥٠ يُحفَظ ١٤٦، والفرقُ لا يقف عند العرض: منه يُنسَخ
 * إلى أمر الشراء، ومن الأمر إلى الاستلام، ومن الاستلام إلى الفاتورة —
 * وإلى قيدها في «ضريبة المدخلات». فيخالف الإجماليُّ المطبوعَ ما رآه
 * المستخدم على الشاشة، ويخالف الإقرارُ الضريبيُّ مجموعَ البنود.
 *
 * والتوسيع آمن: القيم المحفوظة أعدادٌ صحيحة تبقى صالحةً بكسرَين صفرَين،
 * ولا يُفقَد شيء. والعمود يبقى على قبوله الفراغ وافتراضِه كما كان —
 * التغيير في الدقّة وحدها. والمستنداتُ السابقة لا تُصحَّح هنا — لا سبيل إلى
 * استرجاع الكسر الذي قُرِّب، وإعادةُ حسابها من بنودها قرارٌ يخصّ المستخدم.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('purchase_quotations_view')
            || ! Schema::hasColumn('purchase_quotations_view', 'total_tax')) {
            return;
        }

        /*
            كان هنا `ALTER TABLE … MODIFY` — صيغةُ MySQL وحدَها، فتسقط بيئةُ
            التطوير (SQLite) عندها ويعلق `migrate` كلُّه. و`->change()` في
            لارافيل 11 أصليٌّ بلا doctrine/dbal (ذاك شرطُ الإصدارات قبله)،
            ويولّد الجملة الصحيحة للمشغّلَين.
        */
        Schema::table('purchase_quotations_view', function (Blueprint $table) {
            $table->decimal('total_tax', 15, 2)->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('purchase_quotations_view')
            && Schema::hasColumn('purchase_quotations_view', 'total_tax')) {
            Schema::table('purchase_quotations_view', function (Blueprint $table) {
                $table->decimal('total_tax', 10, 0)->nullable()->default(null)->change();
            });
        }
    }
};
