<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * إشعاراتُ السداد من بوابة العميل.
 *
 * العميلُ يُعلن أنه سدّد فاتورةً (تحويلاً بنكياً أو شبكةً أو نقداً) ويرفق
 * إيصاله؛ والموظّفُ يعتمد الإشعار فيُفتح له سندُ القبض المعتاد معبّأً.
 *
 * **الإشعار ليس سنداً ولا قيداً.** لا يمسّ رصيدَ العميل ولا الخزينة ولا
 * الفاتورة حتى يعتمده موظّف — فالمالُ لا يدخل النظامَ بقول العميل وحده.
 * وحين يُعتمد، يُنشأ السندُ بمسار المبيعات نفسِه (`payments_process` + القيد)
 * ويُربط رقمُه هنا: قناةٌ واحدةٌ للتحصيل لا قناتان تختلفان.
 *
 * `gateway_*` محفوظةٌ لبوابة الدفع الإلكتروني حين تُربط لاحقاً (طلب
 * المستخدم: «سوّها من دون بوابة دفع، يكون الربط فيما بعد») — يومَها يصير
 * الإشعارُ المدفوعُ بالبطاقة `approved` آلياً بمرجع البوابة بدل يد الموظّف.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_payment_notices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            // مَن أرسله من حسابات البوابة — قد يكون للعميل أكثرُ من حساب
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();

            $table->decimal('amount', 15, 2);
            // bank_transfer | card_network | cash | wallet | other — نصٌّ لا تعداد:
            // طرقُ الدفع في النظام صفوفٌ في جدول، وهذه نيّةُ العميل لا مرجعٌ محاسبي
            $table->string('method', 30)->default('bank_transfer');
            $table->date('paid_at')->nullable();
            $table->string('reference', 120)->nullable();
            $table->string('attachment')->nullable();
            $table->text('note')->nullable();

            // pending | approved | rejected | cancelled
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            // صفُّ `payments_process` الذي أنشأه الاعتماد — بلا قيدٍ أجنبي:
            // الجدولُ قديمٌ ومفاتيحُه غيرُ متّسقة، وحذفُ السند لا يحذف الإشعار
            $table->unsignedBigInteger('payment_id')->nullable();

            // للبوابة الإلكترونية لاحقاً
            $table->string('gateway', 30)->nullable();
            $table->string('gateway_reference', 120)->nullable();

            $table->timestamps();

            $table->index(['client_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_payment_notices');
    }
};
