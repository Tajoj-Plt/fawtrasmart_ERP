<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * الراتب الأساسي في العقد: `integer` ← `decimal(15,2)`.
 *
 * **العطل**
 *
 * عمودُ `contracts.amount` عددٌ صحيح، وقاعدةُ التحقّق في
 * `ContractsController` هي `'amount' => 'required|numeric'` — تقبل الكسور.
 * فمن كتب راتباً أساسياً `7,500.50` مرّ إدخالُه بلا خطأ ولا تحذير.
 *
 * و**MySQL لا يقصّ الكسر بل يقرّبه**: `7500.50` تُخزَّن `7501` (جُرِّبت).
 * فالراتبُ لا ينقص نصفَ ريالٍ فحسب، بل قد **يزيد** ريالاً — والفرقُ يسري
 * إلى `salary_slips.basic_amount` ومنه إلى قيد الاستحقاق وقسيمة الراتب
 * وسند الصرف.
 *
 * فالعطلُ لم يكن في التحقّق — كان صحيحاً — بل في العمود الذي يكذّبه.
 *
 * وبقيّةُ الأعمدة المالية في هذا النطاق `decimal` أصلاً —
 * `salary_slips.basic_amount` و`payroll_runs.net_salary` و
 * `salary_items.amount` — فالعقدُ وحده كان يكسر الدقّة ثم تُبنى عليه.
 *
 * **لماذا `15,2` لا غيرها؟** هي دقّةُ المال في هذا النظام:
 * `payroll_runs` و`payroll_run_items` و`deductions` كلُّها عليها.
 *
 * **التحويل موسِّعٌ لا هادم**: كل قيمةٍ صحيحة تنتقل بلا فقد (`7500` تصير
 * `7500.00`)، ولا تُقصّ قيمةٌ قائمة ولا يُحذف عمود. والعمود يبقى
 * `nullable` كما كان.
 *
 * **ولماذا `->change()` لا `DB::statement`؟** لأن `ALTER TABLE … MODIFY`
 * صيغةُ MySQL وحدها، ولا يفهمها sqlite الذي يفرضه `phpunit.xml` —
 * ومهاجرةٌ واحدة بتلك الصيغة (`payment_methods`) تُسقط حزمةَ الاختبارات
 * كلَّها عند الترحيل. وباني المخطَّط في لارافل ١١ يعدّل العمود أصلياً
 * فيولّد لكل محرّكٍ صيغتَه.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('contracts', 'amount')) {
            return;
        }

        Schema::table('contracts', function (Blueprint $table) {
            $table->decimal('amount', 15, 2)->nullable()->default(null)->change();
        });
    }

    /**
     * الرجوع يُقصّ الكسور — وهو ما كان عليه العمود.
     *
     * لا يُنفَّذ إلا بطلبٍ صريح، ومن رجع بعد أن أُدخلت كسورٌ يفقدها. ولا
     * سبيل إلى غير ذلك: النوعُ الأضيق لا يسع ما وسّعه الأوسع.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('contracts', 'amount')) {
            return;
        }

        Schema::table('contracts', function (Blueprint $table) {
            $table->integer('amount')->nullable()->default(null)->change();
        });
    }
};
