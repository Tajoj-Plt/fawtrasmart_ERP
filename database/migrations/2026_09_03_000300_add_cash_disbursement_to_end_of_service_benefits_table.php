<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * فصلُ تسويةِ المكافأة عن صرفها نقداً.
 *
 * كان `EndOfServiceBenefitController::pay()` يرحّل قيداً واحداً:
 *
 *     مدين  مخصص نهاية الخدمة
 *         دائن  مستحقات الموظف
 *
 * ثمّ يضبط `status = paid`. ولا خزينةَ في الأمر ولا سندَ صرفٍ ولا تغيُّرَ في
 * أيِّ رصيدٍ نقدي: المكافأةُ «مدفوعة» في شاشة الموارد البشرية، والالتزامُ
 * للموظف المنتهية خدمتُه قائمٌ في الدفتر — ولا مسارَ في الوحدة يُخرجه نقداً.
 *
 * والقيدُ نفسُه صحيحٌ محاسبياً: هو تسويةُ مخصَّصٍ إلى ذمّة. الخطأُ في الكلمة
 * وفي غياب الخطوة التي بعدها. فصارت حالتان:
 *
 *   • `settled` — سُوِّيت إلى ذمّة الموظف (القيدُ القائم، بلا نقد)
 *   • `paid`    — صُرفت نقداً من خزينة (سندُ صرفٍ وقيدٌ يُنقص الرصيد)
 *
 * والصفوفُ القائمة بحالة `paid` تُنقل إلى `settled`: هي في الحقيقة مسوّاةٌ لا
 * مدفوعة — لم يخرج عنها ريال. وإبقاؤها `paid` يعني الكذبَ على من يقرأ
 * الشاشة، ونقلُها يُظهر الالتزامَ القائم فعلاً فيُصرف.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('end_of_service_benefits')) {
            return;
        }

        Schema::table('end_of_service_benefits', function (Blueprint $table) {
            if (! Schema::hasColumn('end_of_service_benefits', 'treasury_id')) {
                $table->unsignedBigInteger('treasury_id')->nullable()->after('settlement_journal_entry_id');
            }
            if (! Schema::hasColumn('end_of_service_benefits', 'expense_id')) {
                $table->unsignedBigInteger('expense_id')->nullable()->after('treasury_id');
            }
            if (! Schema::hasColumn('end_of_service_benefits', 'payment_journal_entry_id')) {
                $table->unsignedBigInteger('payment_journal_entry_id')->nullable()->after('expense_id');
            }
            if (! Schema::hasColumn('end_of_service_benefits', 'settled_at')) {
                $table->timestamp('settled_at')->nullable();
            }
            if (! Schema::hasColumn('end_of_service_benefits', 'settled_by')) {
                $table->unsignedBigInteger('settled_by')->nullable();
            }
        });

        /*
            ما كان `paid` هو في الحقيقة `settled`: قيدُ التسوية وحدَه رُحِّل،
            ولا سندَ صرفٍ ولا خزينة. ويُنقل `paid_at`/`paid_by` إلى عمودَي
            التسوية لأنهما يؤرّخان لها هي.
        */
        DB::table('end_of_service_benefits')
            ->where('status', 'paid')
            ->update([
                'status' => 'settled',
                'settled_at' => DB::raw('paid_at'),
                'settled_by' => DB::raw('paid_by'),
                'paid_at' => null,
                'paid_by' => null,
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('end_of_service_benefits')) {
            return;
        }

        DB::table('end_of_service_benefits')
            ->where('status', 'settled')
            ->update([
                'status' => 'paid',
                'paid_at' => DB::raw('settled_at'),
                'paid_by' => DB::raw('settled_by'),
            ]);

        foreach (['treasury_id', 'expense_id', 'payment_journal_entry_id', 'settled_at', 'settled_by'] as $column) {
            if (Schema::hasColumn('end_of_service_benefits', $column)) {
                Schema::table('end_of_service_benefits', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
