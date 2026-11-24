<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * غرامة التأخير — من رقمٍ يُعرَض إلى مبلغٍ يُحتسب ويُقيَّد.
 *
 * ══════════════════════════════════════════════════════════════════════
 *  ما كان
 * ══════════════════════════════════════════════════════════════════════
 *
 * `InstallmentSchedule::lateFeeFor()` تحسب رقماً وتعرضه في عمودٍ واحد ثمّ
 * ينتهي أثرُه عند حافّة الشاشة: لا عمودَ يحفظه، ولا قيدَ يُرحّله، ولا
 * تفرقةَ بين قسطٍ تأخّر يوماً وآخر تأخّر تسعين — كلاهما يعطي المبلغَ
 * نفسَه لأنّ الحساب لا يعرف عدد الأيام أصلاً، إنّما يعرف «مضت مدّة
 * السماح أم لا».
 *
 * ══════════════════════════════════════════════════════════════════════
 *  ما تضيفه الهجرة
 * ══════════════════════════════════════════════════════════════════════
 *
 * **على الاتفاقية** — سياسة الغرامة:
 *
 *   `late_fee_basis`   `once` مرّةً واحدة بعد السماح، أو `daily` عن كلّ
 *                      يوم تأخير. وهو ما يجعل «الاحتساب بناءً على
 *                      التأخير» ممكناً أصلاً.
 *   `late_fee_max`     سقفٌ للغرامة على القسط الواحد. الاحتسابُ اليومي
 *                      بلا سقفٍ يجعل غرامةَ قسطٍ مهجورٍ تتجاوز أصلَه.
 *                      فارغاً يعني بلا سقف.
 *   `tax_id`           الضريبة المختارة من «إعدادات الضريبة»، تُجمَّد مع
 *                      الاتفاقية. النسبةُ تُشتقّ منها ولا تُكتب بيدٍ —
 *                      انظر `InstallmentRequest::taxRate()`.
 *
 * **على القسط** — الغرامة المحمَّلة عليه هو:
 *
 *   `late_fee_amount`     ما حُمِّل فعلاً وقُيِّد — لا ما قد يُحمَّل.
 *   `late_fee_paid`       ما سُدِّد منها.
 *   `late_fee_days`       أيام التأخّر التي احتُسبت عليها.
 *   `late_fee_posted_at`  آخر ترحيلٍ للغرامة — بها يُعرف الفرقُ المستحقّ
 *                         في التشغيل التالي فلا تُقيَّد الغرامة مرّتين.
 *   `late_fee_journal_id` آخر قيدٍ رُحِّلت به.
 *   `due_notified_at`     تاريخ آخر تنبيهٍ باستحقاق هذا القسط.
 *   `overdue_notified_at` تاريخ آخر تنبيهٍ بتأخّره.
 *
 * وتاريخا التنبيه عمودان لا سجلّ: التنبيه يُرسَل مرّةً في اليوم للقسط
 * الواحد، ومهمّةٌ تعمل كلّ ساعة بلا هذين العمودين تملأ جرس الإشعارات
 * أربعاً وعشرين مرّة بالقسط نفسه.
 *
 * لا عمودَ يُحذف ولا يُعاد تعريفه: الهجرة إضافةٌ خالصة، وتشغيلُها ثانيةً
 * بلا أثر.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('installments', function (Blueprint $table) {
            if (! Schema::hasColumn('installments', 'late_fee_basis')) {
                $table->string('late_fee_basis', 10)
                    ->nullable()
                    ->default('once')
                    ->after('late_fee_value')
                    ->comment('once|daily — مرّةً واحدة أم عن كلّ يوم تأخير');
            }

            if (! Schema::hasColumn('installments', 'late_fee_max')) {
                $table->decimal('late_fee_max', 15, 2)
                    ->nullable()
                    ->comment('سقف الغرامة على القسط الواحد — فارغاً يعني بلا سقف');
            }

            if (! Schema::hasColumn('installments', 'tax_id')) {
                $table->unsignedBigInteger('tax_id')
                    ->nullable()
                    ->after('tax_rate')
                    ->comment('الضريبة المختارة من «إعدادات الضريبة» — النسبة تُشتقّ منها');
            }
        });

        Schema::table('installments_details', function (Blueprint $table) {
            foreach ([
                'late_fee_amount' => 'الغرامة المحمَّلة على هذا القسط والمقيَّدة',
                'late_fee_paid' => 'ما سُدِّد من الغرامة',
            ] as $column => $comment) {
                if (! Schema::hasColumn('installments_details', $column)) {
                    $table->decimal($column, 15, 2)->nullable()->default(0)->comment($comment);
                }
            }

            if (! Schema::hasColumn('installments_details', 'late_fee_days')) {
                $table->unsignedSmallInteger('late_fee_days')->nullable()->default(0);
            }

            if (! Schema::hasColumn('installments_details', 'late_fee_posted_at')) {
                $table->dateTime('late_fee_posted_at')->nullable();
            }

            if (! Schema::hasColumn('installments_details', 'late_fee_journal_id')) {
                $table->unsignedBigInteger('late_fee_journal_id')->nullable();
            }

            foreach (['due_notified_at', 'overdue_notified_at'] as $column) {
                if (! Schema::hasColumn('installments_details', $column)) {
                    $table->date($column)->nullable();
                }
            }
        });
    }

    /**
     * التراجع يُبقي ما حُمِّل من غرامات.
     *
     * حذفُ `late_fee_amount` يمحو مبالغَ قُيِّدت في دفتر الأستاذ ويبقى
     * قيدُها — فيصير الدفترُ يقول ما لا يقوله المستند. تُحذف أعمدة
     * السياسة والتنبيه وحدها، وهي لا أثرَ محاسبياً لها.
     */
    public function down(): void
    {
        Schema::table('installments', function (Blueprint $table) {
            foreach (['late_fee_basis', 'late_fee_max', 'tax_id'] as $column) {
                if (Schema::hasColumn('installments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('installments_details', function (Blueprint $table) {
            foreach (['due_notified_at', 'overdue_notified_at'] as $column) {
                if (Schema::hasColumn('installments_details', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
