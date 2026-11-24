<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * حساباتُ نقاط البيع في إعداداتها — لا في «الحسابات المحاسبية» المشتركة.
 *
 * ═══════════════ لماذا ═══════════════
 *
 * المشتركةُ تخدم المنشأةَ كلَّها، ومنشأةٌ قد تريد لنقاط البيع إيراداً
 * وخزينةً وحسابَ خصمٍ خاصّاً بها — لتقرأ أداءها من ميزان المراجعة مباشرةً
 * بلا تقرير. وربطُها بالمشترك يُجبرها على خلط قناتين في حسابٍ واحد.
 *
 * ═══════════════ وما لا عمودَ له عمداً ═══════════════
 *
 * **الخزينةُ والمخزون** ليسا إعداداً بل **عهدةُ الموظّف**:
 *
 *   • الخزينة تُحلّ من `treasury_employees` — لكل كاشيرٍ خزينتُه، وقبضُه
 *     يدخلها هي لا خزينةً عامّة. وثابتٌ واحدٌ يجمع نقدَ الجميع في عهدةٍ لا
 *     صاحبَ لها، فلا يُعرف على مَن الفرق.
 *
 *   • والمخزون يُحلّ من مستودع الجهاز (`accounts.storehouse_id`) — والبيعُ
 *     يُخصم من مستودعٍ بعينه، فحسابُه يتبعه.
 *
 * فحقلٌ لهما في الشاشة يوهم بخيارٍ ليس خياراً، ويُغري بضبطٍ يُبطل التتبّع.
 *
 * **والبنكُ الافتراضي له عمود**: تحصيلُ البطاقات لا يتبع موظّفاً بل يورَّد
 * إلى بنكٍ تختاره المنشأة — وحسابُ طريقة الدفع يتقدّم عليه إن رُبط.
 *
 * ═══════════════ والترتيب ═══════════════
 *
 *   إعدادُ الفرع  ←  إعدادُ نقاط البيع العام  ←  الحساب المشترك  ←  الثابت
 *
 * فمن لم يضبط شيئاً يبقى نظامُه عاملاً بما كان، ومن ضبط يسري ضبطُه فوراً.
 */
return new class extends Migration
{
    /** العمود ⇒ تعليقُه. */
    private const COLUMNS = [
        'sales_account_id' => 'إيراد المبيعات — الطرف الدائن في قيد الفاتورة',
        'sales_returns_account_id' => 'مردودات المبيعات — الطرف المدين في قيد المرتجع',
        'cost_of_sales_account_id' => 'تكلفة البضاعة المباعة',
        'discount_account_id' => 'الخصم المسموح به',
        'vat_account_id' => 'ضريبة القيمة المضافة المحصَّلة',
        'cash_over_account_id' => 'فائض درج الكاشير — إيراد عارض',
        'cash_short_account_id' => 'عجز درج الكاشير — مصروف',
    ];

    public function up(): void
    {
        Schema::table('pos_general_settings', function (Blueprint $table) {
            foreach (self::COLUMNS as $column => $comment) {
                if (! Schema::hasColumn('pos_general_settings', $column)) {
                    // بلا مفتاحٍ أجنبيّ: حذفُ حسابٍ من الشجرة يجب أن يُفرغ
                    // الربط لا أن يمنع الحذف — والحلُّ يسقط إلى المشترك
                    $table->unsignedBigInteger($column)->nullable()->comment($comment);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('pos_general_settings', function (Blueprint $table) {
            $table->dropColumn(array_keys(self::COLUMNS));
        });
    }
};
