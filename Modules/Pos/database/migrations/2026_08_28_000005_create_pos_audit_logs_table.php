<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * سجلُّ تدقيق نقاط البيع — من فعل ماذا، ومن أذن له.
 *
 * ─────────────────────── لماذا سجلٌّ خاصّ؟ ───────────────────────
 *
 * النظام يملك `App\Support\OperationLog` وجدولَ `logs` العام، وتستعملهما
 * الوحداتُ الأخرى. لكنّ نقاط البيع تحتاج ما لا يحتاجه غيرها: **من أذن**.
 *
 * الأفعالُ الحسّاسة هنا ليست «تعديلَ سجل» بل تنازلاتٍ عن ضوابط:
 *
 *   • خصمٌ يتجاوز الحدّ المسموح.
 *   • تعديلُ سعرٍ في الشاشة.
 *   • إلغاءُ فاتورةٍ بعد إصدارها.
 *   • مرتجعٌ يتجاوز حدّ الفرع.
 *   • سحبُ نقدٍ من الدرج.
 *   • إقفالُ جلسةٍ بفرقٍ كبير.
 *
 * وكلٌّ منها يُفترض أن يمرّ بموافقة مشرف. فبلا عمودٍ يحمل `approved_by`
 * تصير الموافقةُ كلاماً: تُطلب على الشاشة، ولا يبقى منها أثر يُراجَع.
 *
 * ─────────────────────── وقيمتان: قبل وبعد ───────────────────────
 *
 * «خُفّض السعر» ليست معلومة. «خُفّض من ٤٥ إلى ٣٠ على الصنف كذا في الفاتورة
 * كذا بموافقة فلان» هي المعلومة. ولذلك `before`/`after` هنا لا وصفٌ نصّي.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pos_audit_logs')) {
            return;
        }

        Schema::create('pos_audit_logs', function (Blueprint $table) {
            $table->id();

            /*
                `discount_override` | `price_override` | `void_invoice` |
                `refund_over_limit` | `cash_pay_out` | `cash_drop` |
                `session_close_variance` | `session_confirm` | `reprint`
            */
            $table->string('action');

            $table->unsignedBigInteger('session_id')->nullable();
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->unsignedBigInteger('device_id')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable();

            $table->unsignedBigInteger('user_id');

            /** المشرفُ الذي أذن — فارغٌ لما لا يحتاج إذناً. */
            $table->unsignedBigInteger('approved_by')->nullable();

            $table->decimal('amount', 15, 2)->nullable();
            $table->string('currency', 10)->nullable();

            $table->json('before')->nullable();
            $table->json('after')->nullable();

            $table->text('reason')->nullable();

            /** عنوانُ الطلب — يميّز جهازاً عن جهاز حين يتشارك اثنان حساباً. */
            $table->string('ip_address', 45)->nullable();

            $table->unsignedBigInteger('accounting_period_id')->nullable();

            $table->timestamps();

            $table->index(['action', 'created_at'], 'pos_audit_action_date_idx');
            $table->index(['session_id', 'action'], 'pos_audit_session_action_idx');
            $table->index(['branch_id', 'created_at'], 'pos_audit_branch_date_idx');
            $table->index('user_id', 'pos_audit_user_idx');
            $table->index('invoice_id', 'pos_audit_invoice_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_audit_logs');
    }
};
