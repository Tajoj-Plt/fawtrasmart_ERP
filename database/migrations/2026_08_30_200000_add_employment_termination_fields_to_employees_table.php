<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * بياناتُ إنهاء الخدمة — متى وكيف ولماذا.
 *
 * **ما كان**
 *
 * `employees.status` قيمتان: `1 نشط` و`2 غير نشط`. فلا يُعرف أبداً من ترك
 * المنشأة ومتى ولماذا: الموظفُ المستقيلُ والموقوفُ تأديبياً ومن أُنهيت
 * خدمتُه — كلُّهم «غير نشط» واحد. ولا تاريخَ يُبنى عليه حسابُ مكافأةِ
 * نهاية الخدمة، ولا سببٌ يُرجَع إليه في نزاعٍ عمّالي، ولا يومُ عملٍ أخير
 * يُقطع عنده الراتب.
 *
 * **الأعمدة**
 *
 *   • `termination_date`   تاريخُ قرار الإنهاء أو الاستقالة.
 *   • `last_working_day`   آخرُ يومِ عملٍ فعلي — قد يتأخّر عن التاريخ
 *                          أعلاه بمدّة الإشعار، وهو ما يُحتسب عليه الراتب.
 *   • `termination_reason` السببُ نصّاً.
 *
 * كلُّها `nullable`: الموظفون على رأس العمل لا إنهاءَ لهم، ومن أُنهيت
 * خدمتُهم قبل هذه المهاجرة لا تاريخَ محفوظاً لهم — ولا يُخترع.
 *
 * **ولا يُمَسّ العمود `status` ولا مجالُه القائم**: الحالاتُ الجديدة
 * (`4` موقوف، `5` مستقيل، `6` منتهي الخدمة) قيمٌ تُكتب فيه، وكلُّها
 * ليست `1` — فكلُّ فحصٍ قائمٍ في النظام يقرؤها «غير نشط»، وهو الصواب.
 * انظر [\App\Enums\EmploymentStatus].
 */
return new class extends Migration
{
    /** @var array<int, string> */
    private array $columns = ['termination_date', 'last_working_day', 'termination_reason'];

    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (! Schema::hasColumn('employees', 'termination_date')) {
                $table->date('termination_date')->nullable()->after('hire_date');
            }

            if (! Schema::hasColumn('employees', 'last_working_day')) {
                $table->date('last_working_day')->nullable()->after('termination_date');
            }

            if (! Schema::hasColumn('employees', 'termination_reason')) {
                $table->text('termination_reason')->nullable()->after('last_working_day');
            }
        });
    }

    public function down(): void
    {
        $existing = array_filter(
            $this->columns,
            fn (string $column) => Schema::hasColumn('employees', $column)
        );

        if ($existing === []) {
            return;
        }

        Schema::table('employees', function (Blueprint $table) use ($existing) {
            $table->dropColumn(array_values($existing));
        });
    }
};
