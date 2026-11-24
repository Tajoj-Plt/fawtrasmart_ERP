<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ربطُ المستندات المالية بالفترة المحاسبية — علاقةً لا تاريخاً.
 *
 * **ما كان**
 *
 * كلُّ شاشةٍ تسأل عن الفترة تسألها بالتاريخ: `whereBetween('date', [حدّ، حدّ])`.
 * وذاك يعمل ما دام التقويمُ متّصلاً، لكنّه ليس علاقة:
 *
 *   • **الحدُّ يُستنتَج في كلّ شاشة**، فتختلف الشاشتان في الحدّ نفسِه —
 *     واحدةٌ تقرأ `from_date` وأخرى `date_from`، وثالثةٌ تقصّ عند اليوم.
 *   • **إعادةُ ترسيم الفترات تُعيد تصنيف الماضي**: مستندٌ رُحِّل في الربع
 *     الأول يصير في الثاني لأنّ حدود الفترة تحرّكت — والدفترُ المُقفَل
 *     لا يُعاد تصنيفه.
 *   • **لا يُعرف لمستندٍ فترتُه** إلّا بمطابقةِ تاريخه على جدول الفترات في
 *     كلّ مرّة — فلا فهرسَ ولا انضمامَ ولا إحصاءَ يُبنى عليها.
 *
 * **ما صار**
 *
 * `accounting_period_id` عمودٌ في كلّ جدولٍ يحمل عمليةً محاسبية، يُختَم
 * لحظةَ الإنشاء من تاريخ المستند (انظر `BelongsToAccountingPeriod`). فالفترةُ
 * صفةٌ ثابتةٌ في المستند لا استنتاجٌ يُعاد.
 *
 * **والعمود يقبل الفراغ عمداً.** مستندٌ تاريخُه خارج كلّ فترةٍ معرَّفة —
 * وهو واقعٌ في قواعد لم تُولَّد فتراتُها بعد، أو في تقويمٍ فيه فجوة — يُحفَظ
 * بلا فترة ويُرى في «بلا فترة» ليُصحَّح. ومنعُ الحفظ شأنُ ضابط الإقفال
 * (`enforce_lock` و`block_undefined_dates`) لا شأنُ بنية الجدول.
 *
 * والإلحاقُ يجري للقائم: كلُّ مستندٍ يقع تاريخُه في فترةٍ يُنسَب إليها.
 */
return new class extends Migration
{
    /**
     * الجدول ← العمود الذي يحكم فترتَه.
     *
     * التاريخُ الحاكم هو تاريخُ **الحدث المحاسبي** لا أيّ تاريخٍ في السجلّ:
     * الشيكُ يُنسَب إلى فترة **إصداره** لا استحقاقه، فاستحقاقُه قد يقع بعد
     * سنة — والقيدُ الذي عنه كُتب يوم أُصدر.
     */
    private const TABLES = [
        'journal_entries' => 'date',
        'expenses' => 'date',
        'receipts' => 'date',
        'payable_cheques' => 'issue_date',
        'received_cheques' => 'issue_date',
        'cheque_books' => 'created_at',
        'asset_dep' => 'last_dep_date',
        'payments_process' => 'payment_date',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table => $dateColumn) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'accounting_period_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->unsignedBigInteger('accounting_period_id')->nullable()->index();

                /*
                    حذفُ فترةٍ يترك مستنداتها بلا فترة لا يحذفها: المستندُ
                    المالي لا يُحذف تبعاً لتصنيفٍ زمنيّ.
                */
                $blueprint->foreign('accounting_period_id')
                    ->references('id')->on('accounting_periods')
                    ->nullOnDelete();
            });

            $this->backfill($table, $dateColumn);
        }
    }

    /**
     * إلحاقُ القائم: كلُّ مستندٍ يقع تاريخُه في فترةٍ يُنسَب إليها.
     *
     * تحديثٌ واحدٌ لكلّ فترة لا استعلامٌ لكلّ مستند — والفترات عشراتٌ بينما
     * المستندات قد تكون مئات الآلاف.
     */
    private function backfill(string $table, string $dateColumn): void
    {
        if (! Schema::hasColumn($table, $dateColumn)) {
            return;
        }

        foreach (DB::table('accounting_periods')->select('id', 'start_date', 'end_date')->get() as $period) {
            DB::table($table)
                ->whereNull('accounting_period_id')
                ->whereDate($dateColumn, '>=', $period->start_date)
                ->whereDate($dateColumn, '<=', $period->end_date)
                ->update(['accounting_period_id' => $period->id]);
        }
    }

    public function down(): void
    {
        foreach (array_keys(self::TABLES) as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'accounting_period_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->dropForeign([$table.'_accounting_period_id_foreign']);
                $blueprint->dropColumn('accounting_period_id');
            });
        }
    }
};
