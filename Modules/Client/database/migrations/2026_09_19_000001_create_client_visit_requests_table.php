<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * طلباتُ الزيارة الميدانية من بوابة العميل.
 *
 * العميلُ يطلب أن يزوره مندوبٌ ويقترح يوماً وفترةً؛ والموظّفُ يوافق أو
 * يرفض. **الطلبُ ليس زيارةً ولا صفّاً في خطّ السير**: لا يُحجز وقتُ مندوبٍ
 * ولا يظهر في يومه حتى يعتمده موظّفٌ ويضيف العميلَ إلى الخطّ بنفسه — وهي
 * قاعدةُ إشعار السداد ذاتُها: ما يطلبه العميلُ إعلانُ رغبةٍ لا قرارُ تنفيذ.
 *
 * `preferred_date` **مقترحٌ لا موعد**: قد يُزار العميلُ في غيره، فالجدولةُ
 * تبقى بيد من يعرف خطَّ المندوب وحمولةَ يومه.
 *
 * `visit_id` بلا قيدٍ أجنبي عمداً: الزيارةُ (`employee_client_visits`) قد
 * تُنشأ لاحقاً من شاشةٍ أخرى أو لا تُنشأ أصلاً، وقيدٌ أجنبيٌّ على جدولٍ
 * قديمٍ مفاتيحُه غيرُ متّسقة يُسقط الترحيل على قواعدَ قائمة — نفسُ ما فُعل
 * بـ`payment_id` في `client_payment_notices`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_visit_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            // مَن أرسله من حسابات البوابة — قد يكون للعميل أكثرُ من حساب
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->date('preferred_date')->nullable();
            // morning | afternoon | any — نصٌّ لا تعداد: فترةٌ يقترحها العميل
            // لا مرجعٌ يقرأه محرّك الجدولة
            $table->string('time_window', 20)->default('any');
            $table->string('reason', 150);
            $table->text('note')->nullable();

            // pending | approved | rejected | cancelled
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();

            // الزيارةُ التي نشأت عن الطلب إن رُبطت — انظر ترويسة الملف
            $table->unsignedBigInteger('visit_id')->nullable();

            $table->timestamps();

            // الاستعلامان الوحيدان: طلباتُ عميلٍ في بوابته، وقائمةُ الموظّف
            // بحالةٍ وتاريخ
            $table->index(['client_id', 'status']);
            $table->index(['status', 'preferred_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_visit_requests');
    }
};
