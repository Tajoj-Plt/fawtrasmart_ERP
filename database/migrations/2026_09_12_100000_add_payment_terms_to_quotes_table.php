<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * مهلةُ السداد في عروض الأسعار وأوامر البيع.
 *
 * ── ما كان ──
 *
 * `invoices` فيه `payment_terms` منذ إنشائه، و`quotes` بلا نظير. فالمهلةُ
 * التي يتّفق عليها البائعُ في عرض السعر — «صافي ٣٠ يوماً» — لا موضعَ لها
 * في المستند الذي يحملها إلى العميل، ولا تنتقل إلى الفاتورة عند التحويل:
 * يُعيد المستخدمُ كتابتَها يدوياً أو تضيع.
 *
 * ونموذجُ الشاشة كان يحمل حقلاً اسمُه `terms` يُعرَض ويُرسَل ولا يقرؤه
 * المتحكّم — حُذف عند إعادة بناء النموذج وكُتب سببُه في رأسه صراحةً.
 * فالعمودُ هنا هو ما كان ينقصه ليعود حقلاً حيّاً.
 *
 * ── لماذا `string` لا `integer` ──
 *
 * ليطابق `invoices.payment_terms` حرفاً بحرف: عمودان لمعنىً واحد يختلفان
 * في نوعهما يعنيان تحويلاً عند كل مقارنةٍ بينهما، وفلترَ استحقاقٍ يكتب
 * `CAST` في جدولٍ ولا يكتبه في الآخر. والقراءةُ كلُّها تمرّ بـ
 * {@see \App\Support\PaymentTerms} فتُطبَّع في موضعٍ واحد.
 *
 * ولا يُحذف العمود في `down()`: القاعدةُ في هذا المشروع أن يُتقاعد العمودُ
 * ولا يُسقَط — `dropColumn` في ترحيلٍ سابق محا عموداً حيّاً.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('quotes', 'payment_terms')) {
            return;
        }

        Schema::table('quotes', function (Blueprint $table) {
            $table->string('payment_terms', 100)->nullable()->after('expiry_date');
        });
    }

    public function down(): void
    {
        // يُترك كما هو — لا إسقاطَ لعمودٍ قد يحمل بيانات
    }
};
