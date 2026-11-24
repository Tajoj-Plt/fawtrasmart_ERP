<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * مدفوعاتُ فاتورة نقطة البيع — الجدولُ الذي لم يكن.
 *
 * ─────────────────────── ما كان ───────────────────────
 *
 *     private function processPayments($invoiceId, $payments)
 *     {
 *         $totalPaid = collect($payments)->sum('amount');
 *         // ... فحصٌ ...
 *         Log::info('تم معالجة المدفوعات للفاتورة: ' . $invoiceId, [...]);
 *     }
 *
 * دالّةٌ اسمها «معالجة المدفوعات» **تتحقّق وتكتب في اللوغ ولا تحفظ شيئاً**.
 * فالأثرُ الوحيد الباقي للمدفوعات كان:
 *
 *   • عمود `invoices.payment_method` — رقمٌ إن كانت طريقةً واحدة،
 *     و**نصُّ JSON مضغوط** إن كانت متعدّدة. عمودٌ واحدٌ بنوعين، لا يُستعلَم
 *     عنه ولا يُجمَع ولا يُفهرَس.
 *
 *   • عمودا `cash_amount`/`card_amount` في تفاصيل الجلسة — تجميعٌ إلى
 *     خانتين اثنتين: كلُّ ما ليس «أونلاين» نقدٌ، وكلُّ ما هو أونلاين بطاقة.
 *     فمحفظةٌ إلكترونية وتحويلٌ بنكي وشبكةٌ تصير شيئاً واحداً.
 *
 * ─────────────────────── ما يفتحه هذا الجدول ───────────────────────
 *
 * تقريرُ «التحصيل حسب طريقة الدفع» — وهو أوّلُ ما يُطلب في أي نقطة بيع —
 * كان مستحيلاً لا صعباً. ومطابقةُ إشعارات البنك بمدفوعات الشبكة
 * (Settlement) كذلك، ولذلك `settled_at` و`gateway_reference` هنا من أول يوم.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pos_order_payments')) {
            return;
        }

        Schema::create('pos_order_payments', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('invoice_id');
            $table->unsignedBigInteger('session_id')->nullable();
            $table->unsignedBigInteger('payment_method_id');

            $table->decimal('amount', 15, 2);
            $table->string('currency', 10)->nullable();

            /*
                المبلغُ المستلَم والباقي — للنقد وحده.

                الكاشير يستلم ٥٠ لفاتورة ٤٣ فيردّ ٧. وكان «الباقي» يُحسب في
                الاستجابة ولا يُحفظ، فلا يظهر على الإيصال المُعاد طبعه ولا
                في أي تدقيق.
            */
            $table->decimal('tendered_amount', 15, 2)->nullable();
            $table->decimal('change_amount', 15, 2)->default(0);

            /** مرجعُ العملية لدى المزوّد — للمطابقة مع كشف البنك. */
            $table->string('gateway_reference')->nullable();
            $table->string('card_last4', 4)->nullable();

            /*
                `pending` ← عمليةٌ بدأت ولم تُؤكَّد بعد (بوابةُ دفعٍ حقيقية).
                `completed` ← مؤكَّدة.
                `failed` / `refunded` ← لأثرِ المرتجع.
            */
            $table->string('status')->default('completed');

            /** لحظةُ المطابقة مع كشف الحساب — تبقى فارغةً حتى تُسوّى. */
            $table->datetime('settled_at')->nullable();

            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->unsignedBigInteger('accounting_period_id')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();

            $table->text('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();

            // الاستعلامُ الغالب: مدفوعاتُ فاتورة، ومدفوعاتُ جلسة مجموعةً بالطريقة
            $table->index('invoice_id', 'pos_payments_invoice_idx');
            $table->index(['session_id', 'payment_method_id'], 'pos_payments_session_method_idx');
            $table->index(['branch_id', 'created_at'], 'pos_payments_branch_date_idx');
            $table->index('accounting_period_id', 'pos_payments_period_idx');
            $table->index('settled_at', 'pos_payments_settled_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_order_payments');
    }
};
