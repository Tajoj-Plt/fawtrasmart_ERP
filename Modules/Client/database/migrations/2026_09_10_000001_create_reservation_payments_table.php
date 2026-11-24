<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * دفترُ حركات المال على الحجز — قبضٌ وردٌّ ورسمُ إلغاءٍ وتسويةٌ على الفاتورة.
 *
 * ═══════════ لماذا جدولٌ مستقلّ؟ ═══════════
 *
 * كان الحجزُ يحمل رقمين مجمَّعين (`paid_amount`، `cancellation_fee`) ولا
 * يحمل **الحركات** نفسها. وسندُ القبض في `receipts` لا يعرف حجزَه إلا من
 * نصّ الوصف، والردُّ لا مستندَ له أصلاً سوى قيد. فكان مستحيلاً:
 *
 *   • أن يُربط الردُّ بالدفعة الأصلية فلا يتجاوزها،
 *   • أن يُعرض سجلُّ مدفوعات الحجز في شاشته،
 *   • أن يُبنى تقريرُ المدفوعات على **تاريخ الدفع** لا تاريخ الحجز.
 *
 * والصفُّ هنا لا يكرّر القيد: هو فهرسٌ يشير إلى المستندات القائمة
 * (`receipt_id`، `journal_entry_id`، `payments_process_id`) ولا يحمل
 * أرصدةً — الرصيدُ يبقى في دفتر الأستاذ وحده.
 *
 * بلا مفاتيح أجنبية عمداً، على نهج بقية جداول الوحدة: قواعدُ الإنتاج
 * المستوردة تحمل أنواعَ أعمدةٍ قديمة لا يجتمع معها المفتاح.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('reservation_payments')) {
            return;
        }

        Schema::create('reservation_payments', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('reservation_id');

            /** payment | refund | cancellation_fee | invoice_settlement */
            $table->string('type', 30);

            $table->decimal('amount', 15, 2);
            $table->date('date');
            $table->string('currency', 10)->nullable();

            /** حسابُ الخزينة أو البنك في شجرة الحسابات (`accounts.id`) — لا `treasuries.id` */
            $table->unsignedBigInteger('treasury_account_id')->nullable();
            $table->unsignedBigInteger('payment_method_id')->nullable();

            // المستنداتُ التي تحمل الحركة فعلاً
            $table->unsignedBigInteger('receipt_id')->nullable();
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->unsignedBigInteger('payments_process_id')->nullable();

            /** الدفعةُ الأصلية التي يردّ هذا الصفُّ منها */
            $table->unsignedBigInteger('refund_of_id')->nullable();

            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('notes', 500)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();

            $table->timestamps();

            $table->index(['reservation_id', 'type'], 'reservation_payments_reservation_type_index');
            $table->index('date', 'reservation_payments_date_index');
            $table->index('refund_of_id', 'reservation_payments_refund_of_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_payments');
    }
};
